// /me 会话缓存；写操作同步更新或主动失效。
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

async function verifyOwner(inviteCode) {
  const res = await request('/me/verify-owner', { method: 'POST', data: { invite_code: inviteCode } });
  cached = Promise.resolve(res.data);
  return res.data;
}

function invalidateMe() {
  cached = null;
}

// kind: mine | joined
async function markSeen(kind) {
  const res = await request('/me/seen', { method: 'POST', data: { kind } });
  cached = Promise.resolve(res.data);
  return res.data;
}

async function resolvePhone(code) {
  const res = await request('/me/phone', { method: 'POST', data: { code } });
  return res.data.phone;
}

function getTodos() {
  return request('/me/todos').then((res) => res.data);
}

async function bindParty(type, profile) {
  const res = await request('/me/party', { method: 'POST', data: { type, ...profile } });
  cached = Promise.resolve(res.data);
  return res.data;
}

async function unbindParty() {
  const res = await request('/me/party', { method: 'DELETE' });
  cached = Promise.resolve(res.data);
  return res.data;
}

module.exports = {
  getMe,
  updateMe,
  verifyOwner,
  invalidateMe,
  markSeen,
  getTodos,
  resolvePhone,
  bindParty,
  unbindParty,
};
