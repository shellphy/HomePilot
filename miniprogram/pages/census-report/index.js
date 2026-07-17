const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');
const { mdToHtml } = require('../../utils/markdown');

Page({
  behaviors: [load],

  data: {
    censusId: null,
    title: '',
    report: '',
    reportNodes: '',
    generating: false,
    generationStatus: 'idle',
    generationError: '',
    aiChatEnabled: false, // AI 答疑开关，由 /options 下发
    aiReportEnabled: false, // AI 征集报告开关，由 /options 下发
    aiChatShow: false,
    presentation: {},
  },

  onLoad(query) {
    this.setData({ censusId: Number(query.id || 0) });
  },

  onShow() {
    this.reload();
  },

  onPullDownRefresh() {
    this.reload().finally(() => wx.stopPullDownRefresh());
  },

  reload() {
    return this.runLoad(async () => {
      const [report, ai] = await Promise.all([
        matters.getCensusReport(this.data.censusId),
        profile.getAiFeatures(),
      ]);
      this.setData({ aiChatEnabled: !!ai.chat, aiReportEnabled: !!ai.census_report });
      this.applyReport(report);
    });
  },

  applyReport(data) {
    this.setData({
      title: data.title || '',
      report: data.report || '',
      reportNodes: data.report ? mdToHtml(data.report) : '',
      presentation: data.presentation || {},
      generationStatus: data.generation_status || (data.report ? 'completed' : 'idle'),
      generationError: data.generation_error || '',
    });
  },

  // 生成/重新生成后退回「我的问卷」，进度在那颗按钮上跟进；已有报告即强制重跑（force）
  async generate() {
    if (this.data.generating) return;
    this.setData({ generating: true });
    try {
      await matters.generateCensusReport(this.data.censusId, !!this.data.report);
      wx.navigateBack();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ generating: false });
    }
  },

  // 继续问 AI：打开空面板让业主自己问，不预填问题
  askAi() {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open({
      matterId: this.data.censusId,
      matterTitle: this.data.title,
    });
  },

  onAiChatLeave() {
    const panel = this.selectComponent('#aiChat');
    if (panel) panel.close();
    this.setData({ aiChatShow: false });
  },
});
