// 事项与表态的服务层：页面不拼 URL，只表达意图
const { request } = require('../request');

function listMatters() {
  return request('/matters');
}

function listMine() {
  return request('/matters/mine');
}

function listJoined() {
  return request('/matters/joined');
}

function getMatter(id) {
  return request(`/matters/${id}`);
}

function createGroupbuy(data) {
  return request('/matters', { method: 'POST', data: { type: 'groupbuy', ...data } });
}

function updateGroupbuy(id, data) {
  return request(`/matters/${id}`, { method: 'PUT', data });
}

// 通用事项（活动/互助/维权）的发起与编辑
function createMatter(type, data) {
  return request('/matters', { method: 'POST', data: { type, ...data } });
}

function updateMatter(id, data) {
  return request(`/matters/${id}`, { method: 'PUT', data });
}

function flipState(id, state) {
  return request(`/matters/${id}/state`, { method: 'PUT', data: { state } });
}

function publishDeal(id, finalTerms, finalNote) {
  return request(`/matters/${id}/deal`, {
    method: 'PUT',
    data: { final_terms: finalTerms, final_note: finalNote },
  });
}

// shareContact = 成团等互通阶段愿意与牵头人互通手机号
function join(id, shareContact = true) {
  return request(`/matters/${id}/join`, { method: 'POST', data: { share_contact: shareContact } });
}

function leave(id) {
  return request(`/matters/${id}/join`, { method: 'DELETE' });
}

function review(id, rating, content) {
  return request(`/matters/${id}/review`, { method: 'PUT', data: { rating, content } });
}

function postUpdate(id, data) {
  return request(`/matters/${id}/updates`, { method: 'POST', data });
}

// 征集：schema 下发与表态提交（通用，装修摸底只是第一份 schema）
function getCensus(id) {
  return request(`/matters/${id}/census`);
}

function saveCensus(id, data) {
  return request(`/matters/${id}/census`, { method: 'PUT', data });
}

module.exports = {
  listMatters,
  listMine,
  listJoined,
  getMatter,
  createGroupbuy,
  updateGroupbuy,
  createMatter,
  updateMatter,
  flipState,
  publishDeal,
  join,
  leave,
  review,
  postUpdate,
  getCensus,
  saveCensus,
};
