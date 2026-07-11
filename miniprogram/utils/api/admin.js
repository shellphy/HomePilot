// 管理端服务层：审核、发布、问卷、明细、认证、社区设置（原 PC 后台的全部能力）
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
  listParties,
  certifyParty,
  getSettings,
  saveSettings,
};
