// 征集详情页：单期征集的完整聚合公示面（从征集卡片、小区数据 tab、答完题跳转进来）。
const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');

// 条形按总参与人数归一：100% = 全员都选了这项，百分比可直读
function toBars(counts, total) {
  const denom = Math.max(1, total || 0);
  return Object.entries(counts || {}).map(([label, count]) => ({
    label,
    count,
    percent: Math.min(100, Math.round((count / denom) * 100)),
  }));
}

Page({
  behaviors: [load],

  data: {
    censusId: null,
    block: null,
    aiChatShow: false,
  },

  // 快捷提问组件经 getCurrentPages 调到这里：半屏弹出 AI 面板
  openAiChat(options) {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open(options);
  },

  onAiChatLeave() {
    if (!this.data.aiChatShow) return;
    // 关闭面板顺手中断在途的流式回答，别让它在后台继续跑
    const panel = this.selectComponent('#aiChat');
    if (panel) panel.close();
    this.setData({ aiChatShow: false });
  },

  goMyRegistration() {
    wx.navigateTo({ url: `/pages/census-answers/index?id=${this.data.censusId}` });
  },

  toggleSection(event) {
    const index = Number(event.currentTarget.dataset.index);
    const sections = this.data.block.sections.map((section, sectionIndex) =>
      sectionIndex === index ? { ...section, expanded: !section.expanded } : section,
    );
    this.setData({ 'block.sections': sections });
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
      title: block ? `${block.title}｜${block.registered} 人已参与` : '小区数据',
      path: `/pages/census-insights/index?id=${censusId}`,
    };
  },

  onShareTimeline() {
    const { block } = this.data;
    return { title: block ? block.title : '小区数据' };
  },

  reload() {
    return this.runLoad(async () => {
      const census = await matters.getCensus(this.data.censusId);
      const expandedSections = Object.fromEntries(
        ((this.data.block && this.data.block.sections) || []).map((section) => [section.title, section.expanded]),
      );
      wx.setNavigationBarTitle({ title: census.title });
      const sections = census.aggregates
        .map((module) => ({
          title: module.title,
          questions: module.questions
            .map((question) => ({
              text: question.text,
              bars: question.counts ? toBars(question.counts, census.registered_count) : [],
            }))
            .filter((question) => question.bars.length),
        }))
        .filter((section) => section.questions.length)
        .map((section, index) => ({
          ...section,
          expanded: expandedSections[section.title] === undefined ? index === 0 : expandedSections[section.title],
        }));
      this.setData({
        block: {
          title: census.title,
          state: census.state,
          body: census.body,
          purpose: census.purpose || '',
          reportPresentation: census.report_presentation || {},
          initiatorParty: census.initiator_party || null,
          isInitiator: !!census.is_initiator, // 我是发起者本人 → 露出「邻居授权给你看的问卷」入口
          registered: census.registered_count,
          myAnswered: Object.keys(census.answers || {}).length,
          aggregatesVisible: census.aggregates_visible,
          aggregatesMinimum: census.aggregates_minimum,
          sections,
        },
      });
    });
  },

  goCensusForm() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },

  goConsented() {
    wx.navigateTo({ url: `/pages/census-consented/index?id=${this.data.censusId}` });
  },
});
