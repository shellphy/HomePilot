// 管理端 · 问卷编辑：模块列表（题目按模块组织，业主端按模块分步作答）
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    title: '',
    modules: [],
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.getMatter(this.data.id);
      this.setData({
        title: res.data.title,
        modules: res.data.payload.modules || [],
      });
      wx.setNavigationBarTitle({ title: '问卷题目' });
    });
  },

  goModule(event) {
    wx.navigateTo({ url: `/pages/admin/census-module/index?id=${this.data.id}&mi=${event.currentTarget.dataset.mi}` });
  },

  addModule() {
    wx.navigateTo({ url: `/pages/admin/census-module/index?id=${this.data.id}&mi=-1` });
  },
});
