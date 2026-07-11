// 征集详情页：单期征集的完整聚合公示面（从征集卡片、小区数据 tab、答完题跳转进来）。
const matters = require('../../utils/api/matters');
const { getMe } = require('../../utils/me');
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
    quickQuestions: ['题目里的选项是什么意思？', '我家该按什么标准选？'],
    showTextAdmin: false, // 管理员且问卷含填空题：露出「文本题明细与归纳」入口
    aiChatShow: false,    // AI 答疑半屏面板（ai-quick-ask 通过页面方法呼出）
  },

  // 快捷提问组件经 getCurrentPages 调到这里：半屏弹出 AI 面板
  openAiChat(options) {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open(options);
  },

  onAiChatLeave() {
    if (this.data.aiChatShow) this.setData({ aiChatShow: false });
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
      const [census, me] = await Promise.all([
        matters.getCensus(this.data.censusId),
        getMe(),
      ]);
      wx.setNavigationBarTitle({ title: census.title });
      const hasTextQuestions = (census.modules || [])
        .some((module) => (module.questions || []).some((question) => question.type === 'text'));
      this.setData({
        block: {
          title: census.title,
          state: census.state,
          pitch: census.pitch,
          relatedMatter: census.related_matter || null,
          initiatorParty: census.initiator_party || null,
          registered: census.registered_count,
          myAnswered: Object.keys(census.answers || {}).length,
          sections: census.aggregates.map((module) => ({
            title: module.title,
            questions: module.questions
              .map((question) => ({
                text: question.text,
                bars: question.counts ? toBars(question.counts) : [],
                themes: question.themes || [],
              }))
              .filter((question) => question.bars.length || question.themes.length),
          })),
        },
        showTextAdmin: !!me.is_admin && hasTextQuestions,
      });
    });
  },

  goTextAdmin() {
    wx.navigateTo({ url: `/pages/admin/census-text/index?id=${this.data.censusId}` });
  },

  goCensusForm() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },

  // 配套征集的回链：跳回它服务的那个团购
  goRelatedMatter() {
    const related = this.data.block && this.data.block.relatedMatter;
    if (related) wx.navigateTo({ url: `/pages/matter/index?id=${related.id}` });
  },
});
