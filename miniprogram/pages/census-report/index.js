const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');

function formatAnswer(value) {
  return Array.isArray(value) ? value.join('、') : String(value || '');
}

function registrationModules(census, currentModules = []) {
  const answers = census.answers || {};
  const expanded = Object.fromEntries(currentModules.map((module) => [module.key, module.expanded]));

  return (census.modules || [])
    .map((module) => ({
      key: module.key || module.title,
      title: module.title,
      questions: (module.questions || [])
        .filter((question) => answers[question.key] !== undefined)
        .map((question) => ({
          key: question.key,
          text: question.text,
          answer: formatAnswer(answers[question.key]),
        })),
    }))
    .filter((module) => module.questions.length)
    .map((module, index) => ({
      ...module,
      expanded: expanded[module.key] === undefined ? index === 0 : expanded[module.key],
    }));
}

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
    generationStatus: 'idle',
    generationError: '',
    registrationModules: [],
    answeredCount: 0,
    censusState: '',
    aiChatShow: false,
    presentation: {},
  },

  onLoad(query) {
    this.pageActive = true;
    this.setData({ censusId: Number(query.id || 0), token: query.token || '' });
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
      const data = this.data.token
        ? await matters.getSharedCensusReport(this.data.token)
        : await Promise.all([
          matters.getCensusReport(this.data.censusId),
          matters.getCensus(this.data.censusId),
        ]).then(([report, census]) => {
          this.applyRegistration(census);
          return report;
        });
      this.applyReport(data);
      if (data.generation_status === 'pending') this.startPolling();
      const presentation = data.presentation || {};
      wx.setNavigationBarTitle({ title: this.data.token ? (presentation.report_title || '问卷总结') : '我的问卷' });
    });
  },

  applyRegistration(census) {
    this.setData({
      registrationModules: registrationModules(census, this.data.registrationModules),
      answeredCount: Object.keys(census.answers || {}).length,
      censusState: census.state || '',
    });
  },

  toggleRegistrationModule(event) {
    const index = Number(event.currentTarget.dataset.index);
    const modules = this.data.registrationModules.map((module, moduleIndex) =>
      moduleIndex === index ? { ...module, expanded: !module.expanded } : module,
    );
    this.setData({ registrationModules: modules });
  },

  goEdit() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },

  goStats() {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${this.data.censusId}` });
  },

  applyReport(data) {
    this.setData({
      title: data.title || '',
      report: data.report || null,
      generatedAt: data.generated_at || '',
      shareEnabled: !!data.share_enabled,
      shareToken: data.share_token || this.data.token || '',
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
      question: '结合我的问卷总结，先指出最需要我尽快确认的一件事，并告诉我怎么确认。',
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
