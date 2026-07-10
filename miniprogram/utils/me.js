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

module.exports = { getMe, updateMe, invalidateMe };
