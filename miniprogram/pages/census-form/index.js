// 征集表态表单：schema 由征集事项下发，本页对任何征集通用，只负责答案。
// 联系方式属于成员档案（「我的 · 个人资料」维护）；要求档案完整的征集，
// 在这里只做前置引导，表单里没有联系方式字段。
const matters = require('../../utils/api/matters');
const { getMe, invalidateMe } = require('../../utils/me');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    title: '',
    modules: [],
    moduleIndex: 0,
    current: null,
    answers: {},
    picked: {}, // {'题key::选项': true}，WXML 不能调 indexOf，用查表渲染选中态
    needProfile: false, // 该征集要求档案完整且当前缺失 → 先去完善个人资料
    initiatorParty: null, // 署名发起方；有署名才给「让发起者看到我的问卷」勾选
    visibleToInitiator: false, // 是否把匿名破例给这个发起者本人看（默认关）
    submitting: false,
    aiChatShow: false, // AI 答疑半屏面板（每道题「问 AI」呼出）
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  // 填写问卷时每道题的「问 AI」：把题面直接写进问题，AI 顺着这道题讲该怎么选
  askQuestion(event) {
    const { qkey, text } = event.currentTarget.dataset;
    const currentAnswer = this.data.answers[qkey];
    const selected = currentAnswer === undefined
      ? ''
      : `我当前选择的是「${(Array.isArray(currentAnswer) ? currentAnswer : [currentAnswer]).join('、')}」。`;
    this.openAiChat({
      matterId: this.data.id,
      matterTitle: this.data.title,
      question: `我正在回答「${text}」。${selected}请结合我的情况告诉我怎么选；信息不足时，只问我一个最关键的问题。`,
    });
  },

  openAiChat(options) {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open(options);
  },

  onAiChatLeave() {
    if (this.data.aiChatShow) this.setData({ aiChatShow: false });
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
        title: census.title,
        // 空模块是管理端「先建模块再逐题添加」的中间态，作答时跳过
        modules: census.modules
          .filter((module) => (module.questions || []).length)
          .map((module) => ({
            ...module,
            // 带选项解释的题渲染成竖排选项行（填写过程即建概念），没有解释的保持横排 chips
            questions: module.questions.map((question) => {
              const notes = question.option_notes || [];
              const hasNotes = notes.some((note) => note && note.trim());
              return {
                ...question,
                hasNotes,
                optionRows: hasNotes
                  ? (question.options || []).map((label, i) => ({ label, note: (notes[i] || '').trim() }))
                  : [],
              };
            }),
          })),
        answers,
        picked: this.buildPicked(answers),
        needProfile: census.collects_contact && (!me.unit_label || !me.phone),
        initiatorParty: census.initiator_party || null,
        // 回填上次选择：修改问卷时保持勾选状态，默认关
        visibleToInitiator: !!census.my_visible_to_initiator,
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

  // 「让发起者看到我的问卷」：默认关，只在末模块露出，勾选把匿名破例给署名发起方本人
  toggleVisible() {
    this.markDirty();
    this.setData({ visibleToInitiator: !this.data.visibleToInitiator });
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

  // 全量已作答的题（跨模块，空白/未答过滤掉）：末模块只改授权、无新答案时用它补齐请求
  buildCleanAnswers() {
    const out = {};
    this.data.modules.forEach((module) => {
      module.questions.forEach((question) => {
        const value = this.data.answers[question.key];
        if (value !== undefined && (typeof value !== 'string' || value.trim() !== '')) {
          out[question.key] = typeof value === 'string' ? value.trim() : value;
        }
      });
    });
    return out;
  },

  async next() {
    const { id, current, answers, moduleIndex, modules, submitting } = this.data;
    if (submitting) return;

    // 只提交当前模块已作答的题（填空题只填空白也算没答）
    const answered = (value) => value !== undefined && (typeof value !== 'string' || value.trim() !== '');
    const moduleAnswers = {};
    current.questions.forEach((question) => {
      if (answered(answers[question.key])) {
        const value = answers[question.key];
        moduleAnswers[question.key] = typeof value === 'string' ? value.trim() : value;
      }
    });

    const isLast = moduleIndex + 1 >= modules.length;

    this.setData({ submitting: true });
    try {
      // 末模块带上授权勾选：即便这一模块没新答案，也要把 flag 落库（补齐全量答案满足后端校验）
      const payload = { answers: moduleAnswers };
      if (isLast && this.data.initiatorParty) {
        payload.visible_to_initiator = this.data.visibleToInitiator;
        if (!Object.keys(moduleAnswers).length) payload.answers = this.buildCleanAnswers();
      }
      if (Object.keys(payload.answers).length) {
        await matters.saveCensus(id, payload);
        invalidateMe(); // 「我的」页展示问卷进度，需要重新拉
      }
      this.clearDirty(); // 当前模块已落库，返回不再拦

      if (moduleIndex + 1 < modules.length) {
        this.showModule(moduleIndex + 1);
      } else {
        wx.showToast({ title: '问卷完成', icon: 'success' });
        const pages = getCurrentPages();
        const prev = pages[pages.length - 2];
        if (prev && prev.route === 'pages/census-answers/index') {
          setTimeout(() => wx.navigateBack(), 600);
        } else {
          setTimeout(() => wx.redirectTo({ url: `/pages/census-answers/index?id=${id}` }), 600);
        }
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
