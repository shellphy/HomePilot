const matters = require('../../utils/api/matters');
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
      this.applyReport(await matters.getCensusReport(this.data.censusId));
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
