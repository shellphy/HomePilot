// 我的问卷：我参与过的征集列表，点进去看个人问卷与 AI 总结
const { getMe } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    censuses: [],
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  reload() {
    return this.runLoad(async () => {
      const me = await getMe(true);
      this.setData({ censuses: me.censuses || [] });
    });
  },

  goAnswers(event) {
    wx.navigateTo({ url: `/pages/census-answers/index?id=${event.currentTarget.dataset.id}` });
  },

  goInsights() {
    wx.switchTab({ url: '/pages/insights/index' });
  },
});
