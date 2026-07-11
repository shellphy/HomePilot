// 征集详情页：单期征集的完整聚合公示面（从征集卡片、小区数据 tab、答完题跳转进来）。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

function toBars(counts) {
  const entries = Object.entries(counts || {});
  const max = Math.max(1, ...entries.map(([, count]) => count));
  return entries.map(([label, count]) => ({
    label,
    count,
    percent: Math.round((count / max) * 100),
  }));
}

Page({
  behaviors: [load],

  data: {
    censusId: null,
    block: null,
    dataFootnote: '',
  },

  onLoad(query) {
    this.setData({ censusId: Number(query.id) });
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  onShareAppMessage() {
    const { block, censusId } = this.data;
    return {
      title: block ? `${block.title}｜${block.registered} 人已登记` : '小区数据',
      path: `/pages/census-insights/index?id=${censusId}`,
    };
  },

  onShareTimeline() {
    const { block } = this.data;
    return { title: block ? block.title : '小区数据' };
  },

  reload() {
    return this.runLoad(async () => {
      const [census, options] = await Promise.all([
        matters.getCensus(this.data.censusId),
        profile.getOptions(),
      ]);
      wx.setNavigationBarTitle({ title: census.title });
      this.setData({
        block: {
          title: census.title,
          state: census.state,
          pitch: census.pitch,
          registered: census.registered_count,
          myAnswered: Object.keys(census.answers || {}).length,
          sections: census.aggregates.map((module) => ({
            title: module.title,
            questions: module.questions
              .map((question) => ({ text: question.text, bars: toBars(question.counts) }))
              .filter((question) => question.bars.length),
          })),
        },
        dataFootnote: (options.community && options.community.data_footnote) || '',
      });
    });
  },

  goCensusForm() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },
});
