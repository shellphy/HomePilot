// 我的：我家在小区里的档案（标准布局：头像区 + 分组单元格）
const matters = require('../../utils/api/matters');
const admin = require('../../utils/api/admin');
const { getMe } = require('../../utils/me');

Page({
  data: {
    me: null,
    joinedCount: 0,
    mineCount: 0,
    censusCount: 0,
    pendingCount: 0,
    loadError: '',
  },

  onShow() {
    this.loadMe();
  },

  async loadMe() {
    try {
      const [me, mineRes, joinedRes] = await Promise.all([
        getMe(),
        matters.listMine(),
        matters.listJoined(),
      ]);
      const pendingCount = me.is_admin
        ? (await admin.listMatters(true)).pending_count
        : 0;


      // 身份行：业主 · 楼栋 · 房号（空项不显示）；相关方则是 类型 · 名称
      const identityLine = me.party
        ? [me.party.label, me.party.name].filter(Boolean).join(' · ')
        : ['业主', me.unit_label, me.room_label].filter(Boolean).join(' · ');

      this.setData({
        me,
        identityLine,
        censusCount: (me.censuses || []).length,
        mineCount: mineRes.data.length,
        joinedCount: joinedRes.data.length,
        pendingCount,
        loadError: '',
      });
    } catch (error) {
      if (this.data.me) {
        wx.showToast({ title: error.message, icon: 'none' });
      } else {
        this.setData({ loadError: error.message });
      }
    }
  },

  goProfile() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  // 打开小区数据 tab（各期征集都在里面）
  goCensus() {
    wx.switchTab({ url: '/pages/insights/index' });
  },

  goAdminMatters() {
    wx.navigateTo({ url: '/pages/admin/matters/index' });
  },

  goAdminParties() {
    wx.navigateTo({ url: '/pages/admin/parties/index' });
  },

  goAdminSettings() {
    wx.navigateTo({ url: '/pages/admin/settings/index' });
  },

  goJoined() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=joined' });
  },

  goMine() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=mine' });
  },
});
