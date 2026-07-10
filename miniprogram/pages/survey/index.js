const { request } = require('../../utils/request');

Page({
  data: {
    modules: [],
    moduleIndex: 0,
    current: null,
    answers: {},
    picked: {}, // {'题key::选项': true}，WXML 不能调 indexOf，用查表渲染选中态
    submitting: false,
    loaded: false,
  },

  onLoad() {
    this.loadSurvey();
  },

  async loadSurvey() {
    try {
      const res = await request('/survey');
      const answers = res.answers || {};
      this.setData({
        modules: res.modules,
        answers,
        picked: this.buildPicked(answers),
        loaded: true,
      });
      this.showModule(0);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  showModule(index) {
    this.setData({ moduleIndex: index, current: this.data.modules[index] });
    if (typeof wx.pageScrollTo === 'function') {
      wx.pageScrollTo({ scrollTop: 0, duration: 0 });
    }
  },

  pick(event) {
    const { qkey, qtype, value } = event.currentTarget.dataset;
    const answers = { ...this.data.answers };

    if (qtype === 'multi') {
      const selected = answers[qkey] || [];
      answers[qkey] = selected.includes(value)
        ? selected.filter((item) => item !== value)
        : [...selected, value];
      if (!answers[qkey].length) delete answers[qkey];
    } else {
      answers[qkey] = value;
    }

    this.setData({ answers, picked: this.buildPicked(answers) });
  },

  buildPicked(answers) {
    const picked = {};
    Object.keys(answers).forEach((qkey) => {
      const answer = answers[qkey];
      (Array.isArray(answer) ? answer : [answer]).forEach((value) => {
        picked[`${qkey}::${value}`] = true;
      });
    });
    return picked;
  },

  async next() {
    const { current, answers, moduleIndex, modules, submitting } = this.data;
    if (submitting) return;

    // 只提交当前模块已作答的题
    const moduleAnswers = {};
    current.questions.forEach((question) => {
      if (answers[question.key] !== undefined) {
        moduleAnswers[question.key] = answers[question.key];
      }
    });

    this.setData({ submitting: true });
    try {
      if (Object.keys(moduleAnswers).length) {
        await request('/survey', { method: 'PUT', data: { answers: moduleAnswers } });
      }

      if (moduleIndex + 1 < modules.length) {
        this.showModule(moduleIndex + 1);
      } else {
        wx.showModal({
          title: '问卷完成，谢谢！',
          content: '这些信息只做匿名统计，会变成团长们和商家谈判的筹码。',
          showCancel: false,
          confirmText: '好的',
          success: () => wx.switchTab({ url: '/pages/map/index' }),
        });
      }
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },

  prev() {
    if (this.data.moduleIndex > 0) {
      this.showModule(this.data.moduleIndex - 1);
    }
  },
});
