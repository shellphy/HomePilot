const { request } = require('../../utils/request');

const PILL_CLASS = {
  open: 'pill-open',
  negotiating: 'pill-negotiating',
  seeking: 'pill-seeking',
  done: 'pill-done',
};

const STATUS_FLOW = [
  { value: 'seeking', label: '意向征集' },
  { value: 'negotiating', label: '谈判中' },
  { value: 'open', label: '接龙中' },
  { value: 'done', label: '已成团' },
];

Page({
  data: {
    id: null,
    project: null,
    joined: false,
    isInitiator: false,
    isMerchant: false,
    pillClass: '',
    percent: 0,
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    if (this.data.id) this.loadProject();
  },

  async loadProject() {
    try {
      const [projectRes, meRes] = await Promise.all([
        request(`/projects/${this.data.id}`),
        request('/me'),
      ]);
      this.applyProject(projectRes.data, projectRes.joined);
      this.setData({
        isInitiator: projectRes.data.initiator_id === meRes.data.id,
        isMerchant: meRes.data.role === 'merchant',
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  applyProject(project, joined) {
    this.setData({
      project,
      joined,
      pillClass: PILL_CLASS[project.status] || 'pill-seeking',
      percent: project.target_households
        ? Math.min(100, Math.round((project.signups_count / project.target_households) * 100))
        : 0,
    });
    wx.setNavigationBarTitle({ title: project.title });
  },

  async toggleSignup() {
    if (this.data.submitting) return;
    this.setData({ submitting: true });
    try {
      const res = await request(`/projects/${this.data.id}/signup`, {
        method: this.data.joined ? 'DELETE' : 'POST',
      });
      const { project } = this.data;
      project.signups_count = res.signups_count;
      this.applyProject(project, res.joined);
      wx.showToast({ title: res.joined ? '报名成功' : '已取消报名', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },

  previewImage(event) {
    const { urls, current } = event.currentTarget.dataset;
    wx.previewImage({ urls, current });
  },

  // ---- 以下仅本项目发起人可见的操作 ----

  goEdit() {
    wx.navigateTo({ url: `/pages/leader-project/index?id=${this.data.id}` });
  },

  goProgress() {
    wx.navigateTo({ url: `/pages/leader-progress/index?id=${this.data.id}` });
  },

  flipStatus() {
    const current = this.data.project.status;
    const options = STATUS_FLOW.filter((status) => status.value !== current);

    wx.showActionSheet({
      itemList: options.map((status) => `流转为「${status.label}」`),
      success: async ({ tapIndex }) => {
        const { project } = this.data;
        try {
          await request(`/projects/${this.data.id}`, {
            method: 'PUT',
            data: {
              category: project.category,
              title: project.title,
              status: options[tapIndex].value,
              target_households: project.target_households,
              pitch: project.pitch,
              perk: project.perk,
              terms: project.terms,
              glossary: project.glossary,
            },
          });
          wx.showToast({ title: '状态已更新', icon: 'success' });
          this.loadProject();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
