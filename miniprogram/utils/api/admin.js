// 管理端服务层：审核、发布、问卷、明细、认证、社区设置
const { request } = require('../request');

function listMatters(pendingOnly) {
  return request(`/admin/matters${pendingOnly ? '?pending=1' : ''}`);
}

function getMatter(id) {
  return request(`/admin/matters/${id}`);
}

function createMatter(data) {
  return request('/admin/matters', { method: 'POST', data });
}

function updateMatter(id, data) {
  return request(`/admin/matters/${id}`, { method: 'PUT', data });
}

// reason 仅驳回时有意义：展示给发起人，编辑后即重新提交
function approveMatter(id, approved, reason = '') {
  return request(`/admin/matters/${id}/approve`, { method: 'PUT', data: { is_approved: approved, reason } });
}

function deleteMatter(id) {
  return request(`/admin/matters/${id}`, { method: 'DELETE' });
}

function listRegistrations(matterId) {
  return request(`/admin/matters/${matterId}/registrations`);
}

// 征集文本题：匿名明细 + 已有归纳
function getCensusText(matterId) {
  return request(`/admin/matters/${matterId}/census-text`);
}

// 保存某道文本题的归纳（published=false 草稿 / true 公示）
function saveCensusSummary(matterId, data) {
  return request(`/admin/matters/${matterId}/census-summary`, { method: 'PUT', data });
}

function listParties() {
  return request('/admin/parties');
}

function certifyParty(id, listed) {
  return request(`/admin/parties/${id}`, { method: 'PUT', data: { is_listed: listed } });
}

function getSettings() {
  return request('/admin/settings');
}

function saveSettings(data) {
  return request('/admin/settings', { method: 'PUT', data });
}

module.exports = {
  listMatters,
  getMatter,
  createMatter,
  updateMatter,
  approveMatter,
  deleteMatter,
  listRegistrations,
  getCensusText,
  saveCensusSummary,
  listParties,
  certifyParty,
  getSettings,
  saveSettings,
};
