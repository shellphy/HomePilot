const { apiBase } = require('../config');

let loginPromise = null;

function wxLogin() {
  return new Promise((resolve, reject) => {
    wx.login({
      success: (res) => resolve(res.code),
      fail: () => reject(new Error('微信登录失败，请重试')),
    });
  });
}

function rawRequest(path, { method = 'GET', data, token } = {}) {
  return new Promise((resolve, reject) => {
    wx.request({
      url: apiBase + path,
      method,
      data,
      header: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      success: resolve,
      // 微信的 fail 对象只有 errMsg 没有 message，包成 Error 让上层的
      // error.message 兜底逻辑（load 骨架屏重试、toast）能拿到人话
      fail: (err) =>
        reject(
          new Error(
            err && err.errMsg && err.errMsg.includes('timeout') ? '网络超时，请稍后重试' : '网络异常，请检查网络后重试',
          ),
        ),
    });
  });
}

// 静默登录：wx.login 的 code 换后端 token，存本地
function ensureLogin(force = false) {
  if (!force) {
    const token = wx.getStorageSync('token');
    if (token) return Promise.resolve(token);
    if (loginPromise) return loginPromise;
  }

  loginPromise = wxLogin()
    .then((code) => rawRequest('/login', { method: 'POST', data: { code } }))
    .then((res) => {
      if (res.statusCode !== 200 || !res.data.token) {
        throw new Error((res.data && res.data.message) || '登录失败，请重试');
      }
      wx.setStorageSync('token', res.data.token);
      return res.data.token;
    })
    .finally(() => {
      loginPromise = null;
    });

  return loginPromise;
}

// 带认证的请求；401 时自动重新登录重试一次
async function request(path, options = {}) {
  let token = await ensureLogin();
  let res = await rawRequest(path, { ...options, token });

  if (res.statusCode === 401) {
    token = await ensureLogin(true);
    res = await rawRequest(path, { ...options, token });
  }

  if (res.statusCode >= 200 && res.statusCode < 300) {
    return res.data;
  }

  const error = new Error((res.data && res.data.message) || '请求失败，请重试');
  error.response = res;
  throw error;
}

// 把一段完整的 UTF-8 字节序列解成字符串。只在按 \n\n 切出的完整帧上调用，
// 所以不会遇到跨帧被截断的半个汉字（换行是单字节 ASCII，切点必然落在字符边界）。
/* eslint-disable no-bitwise */
function utf8Decode(bytes) {
  let result = '';
  let i = 0;
  const len = bytes.length;
  while (i < len) {
    const b = bytes[i];
    if (b < 0x80) {
      result += String.fromCharCode(b);
      i += 1;
    } else if (b < 0xe0) {
      result += String.fromCharCode(((b & 0x1f) << 6) | (bytes[i + 1] & 0x3f));
      i += 2;
    } else if (b < 0xf0) {
      result += String.fromCharCode(((b & 0x0f) << 12) | ((bytes[i + 1] & 0x3f) << 6) | (bytes[i + 2] & 0x3f));
      i += 3;
    } else {
      const cp =
        ((b & 0x07) << 18) | ((bytes[i + 1] & 0x3f) << 12) | ((bytes[i + 2] & 0x3f) << 6) | (bytes[i + 3] & 0x3f);
      const c = cp - 0x10000;
      result += String.fromCharCode(0xd800 + (c >> 10), 0xdc00 + (c & 0x3ff));
      i += 4;
    }
  }
  return result;
}

/* eslint-enable no-bitwise */

// SSE 帧以空行（\n\n）分隔，返回第一处分隔的字节下标
function indexOfFrameBoundary(bytes) {
  for (let i = 0; i + 1 < bytes.length; i += 1) {
    if (bytes[i] === 0x0a && bytes[i + 1] === 0x0a) return i;
  }
  return -1;
}

// SSE 流式请求：后端逐帧下发 `data: {json}`，这里边收边解，
// 每个 delta 交给 onDelta 回调（打字机式渲染）。返回 { abort, promise }：
// - abort() 中断请求（配合前端的停止按钮）；
// - promise 成功时解析为 { conversationId, remaining, aborted }，出错时 reject。
function streamRequest(path, { method = 'POST', data, onDelta, onSearching, onSource } = {}) {
  const state = { task: null, aborted: false };

  const abort = () => {
    state.aborted = true;
    if (state.task) state.task.abort();
  };

  const run = (token) =>
    new Promise((resolve, reject) => {
      let buffer = new Uint8Array(0);
      const result = { conversationId: null, remaining: null };
      let streamError = null;

      const handleFrame = (frameBytes) => {
        utf8Decode(frameBytes)
          .split('\n')
          .forEach((line) => {
            const trimmed = line.trim();
            if (!trimmed.startsWith('data:')) return;
            const raw = trimmed.slice(5).trim();
            if (!raw) return;
            let event;
            try {
              event = JSON.parse(raw);
            } catch (e) {
              return;
            }
            if (event.error) {
              streamError = new Error(event.error);
            } else if (event.delta && onDelta) {
              onDelta(event.delta);
            } else if (event.searching && onSearching) {
              onSearching(event.searching);
            } else if (event.source && onSource) {
              onSource(event.source);
            } else if (event.done) {
              result.conversationId = event.conversation_id || null;
              result.remaining = event.remaining_today === undefined ? null : event.remaining_today;
            }
          });
      };

      const onChunk = (arrayBuffer) => {
        const incoming = new Uint8Array(arrayBuffer);
        const merged = new Uint8Array(buffer.length + incoming.length);
        merged.set(buffer, 0);
        merged.set(incoming, buffer.length);
        buffer = merged;

        let idx = indexOfFrameBoundary(buffer);
        while (idx !== -1) {
          handleFrame(buffer.subarray(0, idx));
          buffer = buffer.slice(idx + 2);
          idx = indexOfFrameBoundary(buffer);
        }
      };

      state.task = wx.request({
        url: apiBase + path,
        method,
        data,
        enableChunked: true,
        header: {
          Accept: 'text/event-stream',
          Authorization: `Bearer ${token}`,
        },
        success: (res) => {
          if (res.statusCode === 401) {
            reject(Object.assign(new Error('登录已过期，请重试'), { unauthorized: true }));
            return;
          }
          // 非流式错误（如 429 超限）：整段 buffer 就是后端的 JSON 错误体，取其 message 显示
          if (res.statusCode < 200 || res.statusCode >= 300) {
            let message = 'AI 暂时不可用，请稍后再试';
            try {
              const body = JSON.parse(utf8Decode(buffer));
              if (body && body.message) message = body.message;
            } catch (e) {
              // 非 JSON 就用兜底文案
            }
            reject(new Error(message));
            return;
          }
          if (buffer.length) handleFrame(buffer); // 兜底：末帧若无空行收尾
          if (streamError) {
            reject(streamError);
            return;
          }
          resolve({ ...result, aborted: false });
        },
        // abort 触发的也是 fail，按「用户主动停止」处理，保留已收到的文字
        fail: (err) => {
          const errMsg = (err && err.errMsg) || '';
          if (state.aborted || errMsg.includes('abort')) {
            resolve({ ...result, aborted: true });
            return;
          }
          reject(new Error(errMsg.includes('timeout') ? '网络超时，请稍后重试' : '网络异常，请检查网络后重试'));
        },
      });
      state.task.onChunkReceived((res) => onChunk(res.data));
    });

  const promise = ensureLogin()
    .then((token) => run(token))
    .catch((error) => {
      if (error && error.unauthorized && !state.aborted) {
        return ensureLogin(true).then((token) => run(token));
      }
      throw error;
    });

  return { abort, promise };
}

// 图片上传（团长发进度用），返回可访问的 url
async function uploadImage(filePath) {
  const token = await ensureLogin();

  return new Promise((resolve, reject) => {
    wx.uploadFile({
      url: `${apiBase}/uploads`,
      filePath,
      name: 'image',
      header: { Authorization: `Bearer ${token}` },
      success: (res) => {
        try {
          const data = JSON.parse(res.data);
          if (res.statusCode >= 200 && res.statusCode < 300 && data.url) {
            resolve(data.url);
          } else {
            reject(new Error(data.message || '上传失败，请重试'));
          }
        } catch (error) {
          reject(new Error('上传失败，请重试'));
        }
      },
      fail: (err) => reject(new Error(`上传失败：${(err && err.errMsg) || '请检查网络'}`)),
    });
  });
}

module.exports = { request, streamRequest, ensureLogin, uploadImage };
