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
    this.pageActive = true;
    this.setData({ censusId: Number(query.id || 0) });
  },

  onShow() {
    this.reload();
  },

  onPullDownRefresh() {
    this.reload().finally(() => wx.stopPullDownRefresh());
  },

  onUnload() {
    this.pageActive = false;
    this.stopPolling();
  },

  reload() {
    return this.runLoad(async () => {
      const data = await matters.getCensusReport(this.data.censusId);
      this.applyReport(data);
      if (data.generation_status === 'pending') this.startPolling();
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
      generating: data.generation_status === 'pending',
    });
  },

  async generate() {
    if (this.data.generating) return;
    this.setData({ generating: true });
    try {
      const data = await matters.generateCensusReport(this.data.censusId);
      this.applyReport(data);
      if (data.generation_status === 'pending') this.startPolling();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ generating: false });
    }
  },

  startPolling() {
    if (!this.pageActive) return;
    this.stopPolling();
    this.pollTimer = setTimeout(() => this.pollReport(), 2000);
  },

  stopPolling() {
    if (this.pollTimer) {
      clearTimeout(this.pollTimer);
      this.pollTimer = null;
    }
  },

  async pollReport() {
    try {
      const data = await matters.getCensusReport(this.data.censusId);
      this.applyReport(data);
      if (data.generation_status === 'pending') {
        this.startPolling();
      } else if (data.generation_status === 'failed') {
        wx.showToast({ title: data.generation_error || '生成失败，请稍后重试', icon: 'none' });
      }
    } catch {
      if (this.pageActive) this.startPolling();
    }
  },

  askAi() {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open({
      matterId: this.data.censusId,
      matterTitle: this.data.title,
      question: '结合我的问卷总结，先指出最需要我尽快确认的一件事，并告诉我怎么确认。',
    });
  },

  onAiChatLeave() {
    const panel = this.selectComponent('#aiChat');
    if (panel) panel.close();
    this.setData({ aiChatShow: false });
  },
});
