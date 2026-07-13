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
      });
    });
  },

  toggleModule(event) {
    const index = Number(event.currentTarget.dataset.index);
    this.setData({
      answerModules: this.data.answerModules.map((module, moduleIndex) =>
        moduleIndex === index ? { ...module, expanded: !module.expanded } : module,
      ),
    });
  },
});
