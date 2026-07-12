const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    censusId: null,
    token: '',
    title: '',
    report: null,
    generatedAt: '',
    shareEnabled: false,
    shareToken: '',
    generating: false,
    aiChatShow: false,
    presentation: {},
  },

  onLoad(query) {
    this.setData({ censusId: Number(query.id || 0), token: query.token || '' });
    this.reload();
  },

  onPullDownRefresh() {
    this.reload().finally(() => wx.stopPullDownRefresh());
  },

  reload() {
    return this.runLoad(async () => {
      const data = this.data.token
        ? await matters.getSharedCensusReport(this.data.token)
        : await matters.getCensusReport(this.data.censusId);
      this.applyReport(data);
      const presentation = data.presentation || {};
      wx.setNavigationBarTitle({ title: presentation.report_title || '我的问卷总结' });
    });
  },

  applyReport(data) {
    this.setData({
      title: data.title || '',
      report: data.report || null,
      generatedAt: data.generated_at || '',
      shareEnabled: !!data.share_enabled,
      shareToken: data.share_token || this.data.token || '',
      presentation: data.presentation || {},
    });
  },

  async generate() {
    if (this.data.generating) return;
    this.setData({ generating: true });
    try {
      this.applyReport(await matters.generateCensusReport(this.data.censusId));
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ generating: false });
    }
  },

  async enableShare() {
    try {
      this.applyReport(await matters.shareCensusReport(this.data.censusId));
      wx.showToast({ title: '已开启分享', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  revokeShare() {
    wx.showModal({
      title: '关闭这份报告的分享？',
      content: '已经发出的旧链接会立即失效。',
      success: async ({ confirm }) => {
        if (!confirm) return;
        this.applyReport(await matters.revokeCensusReport(this.data.censusId));
      },
    });
  },

  askAi() {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open({
      matterId: this.data.censusId,
      matterTitle: this.data.title,
      question: '结合我的需求报告，先指出最需要我尽快确认的一件事，并告诉我怎么确认。',
    });
  },

  onAiChatLeave() {
    const panel = this.selectComponent('#aiChat');
    if (panel) panel.close();
    this.setData({ aiChatShow: false });
  },

  onShareAppMessage() {
    if (!this.data.shareEnabled || !this.data.shareToken) {
      return { title: this.data.presentation.report_title || '我的问卷总结', path: `/pages/census-report/index?id=${this.data.censusId}` };
    }
    return {
      title: `${this.data.title}｜${this.data.presentation.report_title || '问卷总结'}`,
      path: `/pages/census-report/index?token=${this.data.shareToken}`,
    };
  },
});
