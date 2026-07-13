// 小区数据 tab：全部征集的总览。
// 进行中的征集渲染成带进度与头牌数据的大卡，已结束的收进「往期数据」列表；
// 单期完整聚合在 pages/census-insights。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    openCards: [], // 进行中的征集大卡
    pastItems: [], // 已结束的往期列表
    communityName: '',
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  onShareAppMessage() {
    const first = this.data.openCards[0];
    return {
      title: first
        ? `${first.title}｜${first.registered} 人已参与`
        : `${this.data.communityName || '小区'} · 小区数据`,
      path: '/pages/insights/index',
    };
  },

  onShareTimeline() {
    return { title: `${this.data.communityName || '小区'} · 小区数据` };
  },

  reload() {
    return this.runLoad(async () => {
      const [stats, options, overview] = await Promise.all([
        profile.getStats(),
        profile.getOptions(),
        matters.listCensusOverview(),
      ]);
      const censuses = overview.data;

      // 进行中的通常只有一两期，逐期拉聚合做头牌数据；往期直接用事项流字段
      const openCards = censuses
        .filter((matter) => matter.state === 'open')
        .map((matter) => ({
          id: matter.id,
          title: matter.title,
          pitch: matter.pitch,
          registered: matter.registered,
          myAnswered: matter.my_answered,
          percent: stats.residents
            ? Math.min(100, Math.round((matter.registered / stats.residents) * 100))
            : 0,
          top: matter.top,
          aggregatesVisible: matter.aggregates_visible,
        }));
      const pastItems = censuses
        .filter((matter) => matter.state !== 'open')
        .map((matter) => ({
          id: matter.id,
          title: matter.title,
          note: `${matter.state_label} · ${matter.registered} 人参与`,
        }));

      this.setData({
        openCards,
        pastItems,
        communityName: (options.community && options.community.name) || '',
      });
    });
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${event.currentTarget.dataset.id}` });
  },

  goRegistration(event) {
    const { id, answered } = event.currentTarget.dataset;
    const page = answered ? 'census-report' : 'census-form';
    wx.navigateTo({ url: `/pages/${page}/index?id=${id}` });
  },
});
