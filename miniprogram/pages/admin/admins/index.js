// 超级管理端 · 管理员：查看当前管理员、按授权手机号增补、收回（仅超级管理员可见）
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    admins: [],
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.listAdmins();
      this.setData({ admins: res.data });
    });
  },

  // 按手机号查出成员，确认身份后再授权：手机号只用于查人，落权前人眼核一次
  grant() {
    wx.showModal({
      title: '授权新管理员',
      editable: true,
      placeholderText: '输入 TA 授权过的手机号',
      confirmText: '查找',
      success: async ({ confirm, content }) => {
        if (!confirm) return;
        const phone = (content || '').trim();
        if (!phone) return wx.showToast({ title: '请输入手机号', icon: 'none' });
        let candidate;
        try {
          candidate = (await admin.lookupAdminCandidate(phone)).data;
        } catch (error) {
          return wx.showToast({ title: error.message, icon: 'none' });
        }
        this.confirmGrant(candidate);
      },
    });
  },

  confirmGrant(candidate) {
    wx.showModal({
      title: '确认授权',
      content: `将「${candidate.name}」设为管理员？`,
      confirmText: '授权',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.grantAdmin(candidate.id);
          wx.showToast({ title: '已授权', icon: 'success' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },

  revoke(event) {
    const { id, name } = event.currentTarget.dataset;
    wx.showModal({
      title: '收回管理员？',
      content: `「${name}」将失去管理员权限`,
      confirmText: '收回',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.revokeAdmin(id);
          wx.showToast({ title: '已收回', icon: 'none' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
