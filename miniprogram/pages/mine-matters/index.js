// 我参与的 / 我牵头的事项列表
const matters = require('../../utils/api/matters');
const { markSeen } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    kind: 'joined', // joined 我参与的 / mine 我牵头的
    matters: [],
  },

  onLoad(query) {
    const kind = query.kind === 'mine' ? 'mine' : 'joined';
    this.setData({ kind });
    wx.setNavigationBarTitle({ title: kind === 'mine' ? '我牵头的' : '我参与的' });
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = this.data.kind === 'mine' ? await matters.listMine() : await matters.listJoined();
      // mine 列表的胶囊按 review_status 渲染（见 wxml），不再预算 pillClass
      this.setData({ matters: res.data });
      // 看过列表即已读：清掉「我的」页对应红点（失败不影响浏览）
      markSeen(this.data.kind).catch(() => {});
    });
  },

  goMatter(event) {
    wx.navigateTo({ url: `/pages/matter/index?id=${event.currentTarget.dataset.id}` });
  },

  goCommunity() {
    wx.switchTab({ url: '/pages/community/index' });
  },
});
