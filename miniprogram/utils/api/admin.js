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

// 超级管理端：增减管理员（仅超级管理员）
function listAdmins() {
  return request('/admin/admins');
}

// 先按手机号查出待授权的成员，供超管确认身份
function lookupAdminCandidate(phone) {
  return request(`/admin/admins/candidate?phone=${encodeURIComponent(phone)}`);
}

// 确认身份后按 id 授权
function grantAdmin(residentId) {
  return request('/admin/admins', { method: 'POST', data: { resident_id: residentId } });
}

function revokeAdmin(id) {
  return request(`/admin/admins/${id}`, { method: 'DELETE' });
}

module.exports = {
  listMatters,
  approveMatter,
  listParties,
  reviewParty,
  getSettings,
  saveSettings,
  listAdmins,
  lookupAdminCandidate,
  grantAdmin,
  revokeAdmin,
};
