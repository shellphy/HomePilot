const { request } = require('../../utils/request');
const { getMe } = require('../../utils/me');
const { STATUS_FLOW, pillClass, signupPercent } = require('../../utils/constants');

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
    loadError: '',
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    if (this.data.id) this.loadProject();
  },

  async loadProject() {
    try {
      const [projectRes, me] = await Promise.all([
        request(`/projects/${this.data.id}`),
        getMe(),
      ]);
      this.applyProject(projectRes.data, projectRes.joined);
      this.setData({
        isInitiator: projectRes.data.initiator_id === me.id,
        isMerchant: me.role === 'merchant',
        loadError: '',
      });
    } catch (error) {
      if (this.data.project) {
        wx.showToast({ title: error.message, icon: 'none' });
      } else {
        this.setData({ loadError: error.message });
      }
    }
  },

  applyProject(project, joined) {
    this.setData({
      project,
      joined,
      pillClass: pillClass(project.status),
      percent: signupPercent(project),
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
        try {
          await request(`/projects/${this.data.id}/status`, {
            method: 'PUT',
            data: { status: options[tapIndex].value },
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
