// 当前用户信息的内存缓存：多个页面 onShow 都要 /me，没必要每次切 tab 都打接口。
// 任何会改动 /me 返回内容的写操作，都必须走 updateMe() 或调用 invalidateMe()。
const { request } = require('./request');

let cached = null;

function getMe(force = false) {
  if (force || !cached) {
    cached = request('/me')
      .then((res) => res.data)
      .catch((error) => {
        cached = null;
        throw error;
      });
  }
  return cached;
}

async function updateMe(data) {
  const res = await request('/me', { method: 'PUT', data });
  cached = Promise.resolve(res.data);
  return res.data;
}

function invalidateMe() {
  cached = null;
}

// 相关方入驻：创建并绑定相关方（可入驻类型由 /options 的 party_types 下发）
async function bindParty(type, name, category) {
  const res = await request('/me/party', { method: 'POST', data: { type, name, category } });
  cached = Promise.resolve(res.data);
  return res.data;
}

// 切回业主身份
async function unbindParty() {
  const res = await request('/me/party', { method: 'DELETE' });
  cached = Promise.resolve(res.data);
  return res.data;
}

module.exports = { getMe, updateMe, invalidateMe, bindParty, unbindParty };
