const { request } = require('../request');

function listMatters(pendingOnly) {
  return request(`/admin/matters${pendingOnly ? '?pending=1' : ''}`);
}

function approveMatter(id, approved, reason = '') {
  return request(`/admin/matters/${id}/approve`, { method: 'PUT', data: { is_approved: approved, reason } });
}

function listParties() {
  return request('/admin/parties');
}

function reviewParty(id, approved, reason = '') {
  return request(`/admin/parties/${id}`, { method: 'PUT', data: { is_approved: approved, reason } });
}

function getSettings() {
  return request('/admin/settings');
}

function saveSettings(data) {
  return request('/admin/settings', { method: 'PUT', data });
}

function getInvitationCode() {
  return request('/admin/invitation-code', { method: 'POST' });
}

function listAdmins() {
  return request('/admin/admins');
}

function lookupAdminCandidate(phone) {
  return request(`/admin/admins/candidate?phone=${encodeURIComponent(phone)}`);
}

function grantAdmin(residentId) {
  return request('/admin/admins', { method: 'POST', data: { resident_id: residentId } });
}

function revokeAdmin(id) {
  return request(`/admin/admins/${id}`, { method: 'DELETE' });
}

function listBlocks() {
  return request('/admin/blocks');
}

function blockResident(residentId) {
  return request('/admin/blocks', { method: 'POST', data: { resident_id: residentId } });
}

function unblockResident(id) {
  return request(`/admin/blocks/${id}`, { method: 'DELETE' });
}

module.exports = {
  listMatters,
  approveMatter,
  listParties,
  reviewParty,
  getSettings,
  saveSettings,
  getInvitationCode,
  listAdmins,
  lookupAdminCandidate,
  grantAdmin,
  revokeAdmin,
  listBlocks,
  blockResident,
  unblockResident,
};
