// 小区数据页：全部征集的聚合总览。
// 不带 id：罗列事项流里所有征集，各自一个板块；带 id（从征集卡片进来）：只看那一期。
// 新增一期征集，这里自动多出一个板块，无需任何改动。
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
    censusId: null, // 带 id = 单期详情，不带 = 总览列表
    residents: 0,
    blocks: [], // 详情模式：这一期的完整聚合
    items: [],  // 总览模式：各期征集的概况列表
    communityName: '',
    dataFootnote: '',
  },

  onLoad(query) {
    if (query.id) this.setData({ censusId: Number(query.id) });
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  onShareAppMessage() {
    const first = this.data.blocks[0];
    return {
      title: first ? `${first.title}｜${first.registered} 人已登记` : `${this.data.communityName || '小区'} · 小区数据`,
      path: `/pages/insights/index${this.data.censusId ? '?id=' + this.data.censusId : ''}`,
    };
  },

  onShareTimeline() {
    return { title: `${this.data.communityName || '小区'} · 小区数据` };
  },

  reload() {
    return this.runLoad(async () => {
      const [stats, options] = await Promise.all([
        profile.getStats(),
        profile.getOptions(),
      ]);

      // 详情模式：只看这一期的完整聚合
      let blocks = [];
      let items = [];
      if (this.data.censusId) {
        const census = await matters.getCensus(this.data.censusId);
        blocks = [{
          id: this.data.censusId,
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
        }];
        wx.setNavigationBarTitle({ title: census.title });
      } else {
        // 总览模式：只列各期征集的概况，点进去看详情（数据都在事项流里，无需逐期请求）
        const feed = await matters.listMatters();
        items = feed.data
          .filter((matter) => matter.type === 'census')
          .map((matter) => ({
            id: matter.id,
            title: matter.title,
            note: `${matter.state_label} · ${matter.register_count} 人已登记`,
          }));
      }

      this.setData({
        residents: stats.residents,
        blocks,
        items,
        communityName: (options.community && options.community.name) || '',
        dataFootnote: (options.community && options.community.data_footnote) || '',
      });
    });
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/insights/index?id=${event.currentTarget.dataset.id}` });
  },

  goCensusForm(event) {
    wx.navigateTo({ url: `/pages/census-form/index?id=${event.currentTarget.dataset.id}` });
  },
});
