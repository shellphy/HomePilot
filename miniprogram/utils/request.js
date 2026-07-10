const { apiBase } = require('../config');

let loginPromise = null;

function wxLogin() {
  return new Promise((resolve, reject) => {
    wx.login({
      success: (res) => resolve(res.code),
      fail: reject,
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
      fail: reject,
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

module.exports = { request, ensureLogin, uploadImage };
