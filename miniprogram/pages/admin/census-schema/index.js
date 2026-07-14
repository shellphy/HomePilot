// 问卷编辑：模块列表（题目按模块组织，业主端按模块分步作答）。
const matters = require('../../../utils/api/matters');
const load = require('../../../behaviors/load');
const { requestSubscribe } = require('../../../utils/subscribe');

Page({
  behaviors: [load],

  data: {
    id: null,
    title: '',
    modules: [],
    reviewStatus: '',
    locked: false, // 已公示/已有作答：只能加题，已有题目不可改动或删除
    questionCount: 0,
    hasQuestions: false,
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getMatter(this.data.id);
      const modules = (res.data.payload || {}).modules || [];
      const questionCount = modules.reduce((count, module) => count + (module.questions || []).length, 0);
      this.setData({
        title: res.data.title,
        modules,
        reviewStatus: res.data.review_status,
        locked: !!res.data.census_schema_locked,
        questionCount,
        hasQuestions: questionCount > 0,
      });
      wx.setNavigationBarTitle({ title: '问卷题目' });
    });
  },

  goModule(event) {
    wx.navigateTo({ url: `/pages/admin/census-module/index?id=${this.data.id}&mi=${event.currentTarget.dataset.mi}` });
  },

  editBasics() {
    wx.navigateTo({ url: `/pages/admin/matter-form/index?id=${this.data.id}` });
  },

  addModule() {
    wx.navigateTo({ url: `/pages/admin/census-module/index?id=${this.data.id}&mi=-1` });
  },

  // 已公示的征集看聚合数据（发起者从这里进整体数据面）
  goInsights() {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${this.data.id}` });
  },

  async submitReview() {
    if (!this.data.hasQuestions || this.data.submitting) return;

    this.setData({ submitting: true });
    try {
      await requestSubscribe();
      const res = await matters.submitMatterReview(this.data.id);
      this.setData({ reviewStatus: res.data.review_status });
      wx.showToast({ title: '已提交审核', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
