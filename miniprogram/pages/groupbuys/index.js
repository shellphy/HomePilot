const { request } = require('../../utils/request');

const PILL_CLASS = {
  open: 'pill-open',
  negotiating: 'pill-negotiating',
  seeking: 'pill-seeking',
  done: 'pill-done',
};

Page({
  data: {
    projects: [],
    isMerchant: false,
    loading: true,
    loadError: '',
  },

  onShow() {
    this.loadProjects();
  },

  async loadProjects() {
    this.setData({ loading: true, loadError: '' });
    try {
      const [res, meRes] = await Promise.all([request('/projects'), request('/me')]);
      const projects = res.data.map((project) => ({
        ...project,
        pillClass: PILL_CLASS[project.status] || 'pill-seeking',
        percent: project.target_households
          ? Math.min(100, Math.round((project.signups_count / project.target_households) * 100))
          : 0,
      }));
      this.setData({ projects, isMerchant: meRes.data.role === 'merchant', loading: false });
    } catch (error) {
      this.setData({ loading: false, loadError: error.message });
    }
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/project/index?id=${event.currentTarget.dataset.id}` });
  },

  goCreate() {
    wx.navigateTo({ url: '/pages/leader-project/index' });
  },
});
