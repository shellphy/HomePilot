const matters = require('../../utils/api/matters');
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
      const census = await matters.getCensus(this.data.censusId);
      this.setData({
        answerModules: answerModules(census, this.data.answerModules),
        answeredCount: Object.keys(census.answers || {}).length,
        censusState: census.state || '',
        initiatorParty: census.initiator_party || null,
        visibleToInitiator: !!census.my_visible_to_initiator,
      });
    });
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

  goReport() {
    wx.navigateTo({ url: `/pages/census-report/index?id=${this.data.censusId}` });
  },

  goStats() {
    wx.navigateTo({ url: `/pages/census-insights/index?id=${this.data.censusId}` });
  },

  goEdit() {
    wx.navigateTo({ url: `/pages/census-form/index?id=${this.data.censusId}` });
  },
});
