// 管理端服务层：事项审核、相关方认证、社区设置
const { request } = require('../request');

// 事项 CRUD（创建/详情/编辑/删除）走统一 /matters 接口（见 utils/api/matters）。
// 管理端审核类动作：待审列表、审核、相关方认证、社区设置。
function listMatters(pendingOnly) {
  return request(`/admin/matters${pendingOnly ? '?pending=1' : ''}`);
}

// reason 仅驳回时有意义：展示给发起人，编辑后即重新提交
function approveMatter(id, approved, reason = '') {
  return request(`/admin/matters/${id}/approve`, { method: 'PUT', data: { is_approved: approved, reason } });
}

function listParties() {
  return request('/admin/parties');
}

// 认证通过 / 驳回（reason 仅驳回时有意义：归属人在详情页看到，改资料后重新提交）
function reviewParty(id, approved, reason = '') {
  return request(`/admin/parties/${id}`, { method: 'PUT', data: { is_approved: approved, reason } });
}

function getSettings() {
  return request('/admin/settings');
}

function saveSettings(data) {
  return request('/admin/settings', { method: 'PUT', data });
}

module.exports = {
  listMatters,
  approveMatter,
  listParties,
  reviewParty,
  getSettings,
  saveSettings,
};
