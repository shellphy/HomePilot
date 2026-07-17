const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

function formatAnswer(value) {
  return Array.isArray(value) ? value.join('、') : String(value || '');
}

// 已作答的题按模块分组；首个模块默认展开，其余沿用当前展开态
function answerModules(census, currentModules = []) {
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
    answerModules: [],
    answeredCount: 0,
    censusState: '',
    initiatorParty: null, // 署名发起方；有署名才给「让发起者看到我的问卷」授权
    visibleToInitiator: false, // 是否把匿名破例授权给这个发起者本人（默认关）
    reportStatus: 'idle', // AI 总结：idle 未生成 / pending 生成中 / completed 可查看 / failed 失败
    aiReportEnabled: false, // AI 征集报告开关，由 /options 下发
  },

  onLoad(query) {
    this.pageActive = true;
    this.setData({ censusId: Number(query.id || 0) });
  },

  onShow() {
    this.pageActive = true;
    this.reload();
  },

  onHide() {
    this.stopPolling();
  },

  onUnload() {
    this.pageActive = false;
    this.stopPolling();
  },

  onPullDownRefresh() {
    this.reload().finally(() => wx.stopPullDownRefresh());
  },

  reload() {
    return this.runLoad(async () => {
      const [census, ai] = await Promise.all([
        matters.getCensus(this.data.censusId),
        profile.getAiFeatures(),
      ]);
      const answeredCount = Object.keys(census.answers || {}).length;
      this.setData({
        answerModules: answerModules(census, this.data.answerModules),
        answeredCount,
        censusState: census.state || '',
        initiatorParty: census.initiator_party || null,
        visibleToInitiator: !!census.my_visible_to_initiator,
        aiReportEnabled: !!ai.census_report,
      });
      if (answeredCount > 0 && ai.census_report) {
        const report = await matters.getCensusReport(this.data.censusId);
        this.applyReportStatus(report.generation_status);
      }
    });
  },

  onSummaryTap() {
    if (this.data.reportStatus === 'pending') {
      return;
    }
    if (this.data.reportStatus === 'completed') {
      wx.navigateTo({ url: `/pages/census-report/index?id=${this.data.censusId}` });
      return;
    }
    this.startSummary();
  },

  // 就地生成：按钮转「AI 总结中」带转圈，好了自动变「查看 AI 总结」
  async startSummary() {
    this.setData({ reportStatus: 'pending' });
    try {
      const report = await matters.generateCensusReport(this.data.censusId);
      this.applyReportStatus(report.generation_status);
    } catch (error) {
      this.setData({ reportStatus: 'failed' });
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  applyReportStatus(status) {
    this.setData({ reportStatus: status });
    if (status === 'pending') {
      this.startPolling();
    }
  },

  startPolling() {
    if (!this.pageActive) {
      return;
    }
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
      const report = await matters.getCensusReport(this.data.censusId);
      this.setData({ reportStatus: report.generation_status });
      if (report.generation_status === 'pending') {
        this.startPolling();
      } else if (report.generation_status === 'failed') {
        wx.showToast({ title: report.generation_error || '生成失败，请稍后重试', icon: 'none' });
      }
    } catch {
      if (this.pageActive) {
        this.startPolling();
      }
    }
  },

  // 授权开关：开启是把联系方式+逐题答案交给发起者，先二次确认；关闭随时、无摩擦
  toggleConsent(event) {
    const visible = event.detail.value;
    if (!visible) {
      this.saveConsent(false);
      return;
    }
    const party = this.data.initiatorParty;
    wx.showModal({
      title: '让发起者看到我的问卷？',
      content: `开启后，「${party.label} · ${party.name}」能看到你的联系方式和每道题的回答。可随时关闭。`,
      confirmText: '开启',
      success: ({ confirm }) => {
        if (confirm) this.saveConsent(true);
        else this.setData({ visibleToInitiator: false }); // 取消：把开关拨回去
      },
    });
  },

  async saveConsent(visible) {
    try {
      await matters.setCensusConsent(this.data.censusId, visible);
      this.setData({ visibleToInitiator: visible });
      wx.showToast({ title: visible ? '已授权发起者查看' : '已关闭授权', icon: 'none' });
    } catch (error) {
      // 失败回滚开关，避免显示与后端不一致
      this.setData({ visibleToInitiator: !visible });
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  toggleModule(event) {
    const index = Number(event.currentTarget.dataset.index);
    this.setData({
      answerModules: this.data.answerModules.map((module, moduleIndex) =>
        moduleIndex === index ? { ...module, expanded: !module.expanded } : module,
      ),
    });
  },

  goStats() {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${this.data.censusId}` });
  },

  goEdit() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },
});
