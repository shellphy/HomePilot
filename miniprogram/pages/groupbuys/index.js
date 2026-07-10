const { request } = require('../../utils/request');
const { getMe } = require('../../utils/me');
const { pillClass, signupPercent } = require('../../utils/constants');

Page({
  data: {
    projects: [],
    isMerchant: false,
    loaded: false,
    loadError: '',
  },

  onShow() {
    this.loadProjects();
  },

  // 首次进入展示加载态；之后 onShow 静默刷新，失败只 toast 不打断浏览
  async loadProjects() {
    try {
      const [res, me] = await Promise.all([request('/projects'), getMe()]);
      const projects = res.data.map((project) => ({
        ...project,
        pillClass: pillClass(project.status),
        percent: signupPercent(project),
      }));
      this.setData({ projects, isMerchant: me.role === 'merchant', loaded: true, loadError: '' });
    } catch (error) {
      if (this.data.loaded) {
        wx.showToast({ title: error.message, icon: 'none' });
      } else {
        this.setData({ loadError: error.message });
      }
    }
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/project/index?id=${event.currentTarget.dataset.id}` });
  },

  goCreate() {
    wx.navigateTo({ url: '/pages/leader-project/index' });
  },
});
