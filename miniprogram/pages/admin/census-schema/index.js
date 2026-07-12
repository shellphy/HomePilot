// 问卷编辑：模块列表（题目按模块组织，业主端按模块分步作答）。
// 走统一 /matters 接口；模块数据在 matter.payload.modules（目前后端只对管理员下发 payload）。
const matters = require('../../../utils/api/matters');
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
      const res = await matters.getMatter(this.data.id);
      // 后端目前只对管理员下发原始 payload（含 modules）。拿不到就无法安全读改题目，
      // 先挡住，避免非管理员发起人误编导致丢字段（详见交付报告的后端后续项）。
      if (res.data.payload === undefined) {
        wx.showModal({
          title: '暂不可编辑问卷',
          content: '问卷题目的编辑目前仅对管理员开放，其他发起人的编辑入口待后端支持。',
          showCancel: false,
          success: () => wx.navigateBack(),
        });
        return;
      }
      this.setData({
        title: res.data.title,
        modules: (res.data.payload || {}).modules || [],
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
