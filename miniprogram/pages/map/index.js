const { request } = require('../../utils/request');

function toBars(counts) {
  const entries = Object.entries(counts || {});
  const max = Math.max(1, ...entries.map(([, count]) => count));
  return entries.map(([label, count]) => ({
    label,
    count,
    percent: Math.round((count / max) * 100),
  }));
}

Page({
  data: {
    registered: 0,
    totalHouseholds: 0,
    registeredPercent: 0,
    layouts: [],
    decorationModes: [],
    interests: [],
    loaded: false,
  },

  onShow() {
    this.loadStats();
  },

  async loadStats() {
    try {
      const stats = await request('/stats');
      this.setData({
        registered: stats.registered,
        totalHouseholds: stats.total_households,
        registeredPercent: stats.total_households
          ? Math.round((stats.registered / stats.total_households) * 1000) / 10
          : 0,
        layouts: toBars(stats.layouts),
        decorationModes: toBars(stats.decoration_modes),
        interests: toBars(stats.interests),
        loaded: true,
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  goRegistration() {
    wx.navigateTo({ url: '/pages/registration/index' });
  },
});
