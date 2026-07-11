// 我参与的 / 我牵头的事项列表
const matters = require('../../utils/api/matters');
const { pillClass } = require('../../utils/constants');
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
      this.setData({
        matters: res.data.map((matter) => ({ ...matter, pillClass: pillClass(matter.state) })),
      });
    });
  },

  goMatter(event) {
    wx.navigateTo({ url: `/pages/matter/index?id=${event.currentTarget.dataset.id}` });
  },

  goCommunity() {
    wx.switchTab({ url: '/pages/community/index' });
  },
});
