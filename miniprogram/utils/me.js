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

// 标记「我牵头的(mine) / 我参与的(joined)」列表已读：打开列表页时调用，
// 清掉「我的」页与 tab 上的未读红点（has_mine_updates / has_joined_updates）
async function markSeen(kind) {
  const res = await request('/me/seen', { method: 'POST', data: { kind } });
  cached = Promise.resolve(res.data);
  return res.data;
}

// 手机号授权：官方组件（open-type="getPhoneNumber"）拿到的 code 换微信绑定号码，
// 这是手机号唯一的写入途径（不接受手填）
async function authPhone(code) {
  const res = await request('/me/phone', { method: 'POST', data: { code } });
  cached = Promise.resolve(res.data);
  return res.data;
}

// 相关方入驻：创建并绑定相关方（可入驻类型由 /options 的 party_types 下发）
// profile = { name, category, intro, description, images }
async function bindParty(type, profile) {
  const res = await request('/me/party', { method: 'POST', data: { type, ...profile } });
  cached = Promise.resolve(res.data);
  return res.data;
}

// 切回业主身份
async function unbindParty() {
  const res = await request('/me/party', { method: 'DELETE' });
  cached = Promise.resolve(res.data);
  return res.data;
}

module.exports = { getMe, updateMe, invalidateMe, markSeen, authPhone, bindParty, unbindParty };
