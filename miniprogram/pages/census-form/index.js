// 征集表态表单：schema 由征集事项下发，本页对任何征集通用，只负责答案。
// 联系方式属于成员档案（「我的 · 个人资料」维护）；要求档案完整的征集，
// 在这里只做前置引导，表单里没有联系方式字段。
const matters = require('../../utils/api/matters');
const { getMe, invalidateMe } = require('../../utils/me');
const { requestSubscribe } = require('../../utils/subscribe');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    modules: [],
    moduleIndex: 0,
    current: null,
    answers: {},
    picked: {}, // {'题key::选项': true}，WXML 不能调 indexOf，用查表渲染选中态
    needProfile: false, // 该征集要求档案完整且当前缺失 → 先去完善个人资料
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  // 从「个人资料」页回来时重查门槛
  async onShow() {
    if (!this.data.loaded || !this.data.needProfile) return;
    const me = await getMe();
    this.setData({ needProfile: !me.unit_label || !me.phone });
  },

  reload() {
    return this.runLoad(async () => {
      const [census, me] = await Promise.all([matters.getCensus(this.data.id), getMe()]);
      const answers = census.answers || {};
      this.setData({
        // 空模块是管理端「先建模块再逐题添加」的中间态，作答时跳过
        modules: census.modules.filter((module) => (module.questions || []).length),
        answers,
        picked: this.buildPicked(answers),
        needProfile: census.collects_contact && (!me.unit_label || !me.phone),
      });
      this.showModule(0);
    });
  },

  goProfile() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  showModule(index) {
    this.setData({ moduleIndex: index, current: this.data.modules[index] });
    wx.pageScrollTo({ scrollTop: 0, duration: 0 });
  },

  pick(event) {
    this.markDirty();
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

  // 填空题：输入即记，空白视为未作答（提交时过滤）
  onText(event) {
    this.markDirty();
    this.setData({ [`answers.${event.currentTarget.dataset.qkey}`]: event.detail.value });
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
    const { id, current, answers, moduleIndex, modules, submitting } = this.data;
    if (submitting) return;

    // 必答题拦在客户端，给出具体是哪题（填空题只填空白也算没答）
    const answered = (value) => value !== undefined && (typeof value !== 'string' || value.trim() !== '');
    const missing = current.questions.find((question) => question.required && !answered(answers[question.key]));
    if (missing) {
      return wx.showToast({ title: `「${missing.text}」是必答题`, icon: 'none' });
    }

    // 只提交当前模块已作答的题
    const moduleAnswers = {};
    current.questions.forEach((question) => {
      if (answered(answers[question.key])) {
        const value = answers[question.key];
        moduleAnswers[question.key] = typeof value === 'string' ? value.trim() : value;
      }
    });

    this.setData({ submitting: true });
    try {
      // 答完最后一个模块顺手收一次订阅授权：征集收尾的通知才有额度可推（中间模块不打扰）
      if (moduleIndex + 1 >= modules.length) await requestSubscribe();
      if (Object.keys(moduleAnswers).length) {
        await matters.saveCensus(id, { answers: moduleAnswers });
        invalidateMe(); // 「我的」页展示答题进度，需要重新拉
      }
      this.clearDirty(); // 当前模块已落库，返回不再拦

      if (moduleIndex + 1 < modules.length) {
        this.showModule(moduleIndex + 1);
      } else {
        wx.showModal({
          title: '感谢参与！',
          content: '这些信息只做匿名统计，聚合结果对全小区公示。不想答的题以后随时可以补。',
          showCancel: false,
          confirmText: '好的',
          // 从公示页进来的走返回（onShow 自会刷新），避免栈里叠两张公示页；其他入口 redirect 过去看结果
          success: () => {
            const pages = getCurrentPages();
            const prev = pages[pages.length - 2];
            if (prev && prev.route === 'pages/census-insights/index') {
              wx.navigateBack();
            } else {
              wx.redirectTo({ url: `/pages/census-insights/index?id=${id}` });
            }
          },
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
