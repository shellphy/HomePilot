// 小区数据 tab：全部征集的总览。
// 进行中的征集渲染成带进度与头牌数据的大卡，已结束的收进「往期数据」列表；
// 单期完整聚合在 pages/census-insights。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

/** @returns {{question: string, label: string, count: number}|null} 第一道已有答案的题里票数最高的选项 */
function topAnswer(aggregates) {
  const question = (aggregates || [])
    .flatMap((module) => module.questions || [])
    .find((item) => Object.keys(item.counts || {}).length);
  if (!question) return null;

  const [label, count] = Object.entries(question.counts).sort((a, b) => b[1] - a[1])[0];
  return { question: question.text, label, count };
}

Page({
  behaviors: [load],

  data: {
    residents: 0,
    openCards: [], // 进行中的征集大卡
    pastItems: [], // 已结束的往期列表
    communityName: '',
    dataFootnote: '',
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
        ? `${first.title}｜${first.registered} 人已登记`
        : `${this.data.communityName || '小区'} · 小区数据`,
      path: '/pages/insights/index',
    };
  },

  onShareTimeline() {
    return { title: `${this.data.communityName || '小区'} · 小区数据` };
  },

  reload() {
    return this.runLoad(async () => {
      const [stats, options, feed] = await Promise.all([
        profile.getStats(),
        profile.getOptions(),
        matters.listMatters(),
      ]);
      const censuses = feed.data.filter((matter) => matter.type === 'census');

      // 进行中的通常只有一两期，逐期拉聚合做头牌数据；往期直接用事项流字段
      const openCards = await Promise.all(censuses
        .filter((matter) => matter.state === 'open')
        .map(async (matter) => {
          const census = await matters.getCensus(matter.id);
          return {
            id: matter.id,
            title: matter.title,
            pitch: census.pitch,
            registered: census.registered_count,
            myAnswered: Object.keys(census.answers || {}).length,
            percent: stats.residents
              ? Math.min(100, Math.round((census.registered_count / stats.residents) * 100))
              : 0,
            top: topAnswer(census.aggregates),
          };
        }));
      const pastItems = censuses
        .filter((matter) => matter.state !== 'open')
        .map((matter) => ({
          id: matter.id,
          title: matter.title,
          note: `${matter.state_label} · ${matter.register_count} 人已登记`,
        }));

      this.setData({
        residents: stats.residents,
        openCards,
        pastItems,
        communityName: (options.community && options.community.name) || '',
        dataFootnote: (options.community && options.community.data_footnote) || '',
      });
    });
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${event.currentTarget.dataset.id}` });
  },

  goCensusForm(event) {
    wx.navigateTo({ url: `/pages/census-form/index?id=${event.currentTarget.dataset.id}` });
  },
});
