// 题目编辑：题干 / 注释 / 单选多选填空 / 必答 / 选项（一行一个）。走统一 /matters 接口。
const matters = require('../../../utils/api/matters');
const load = require('../../../behaviors/load');
const dirty = require('../../../behaviors/dirty');

// 选项行语法：「选项｜解释」（半角 | 也认）。解释显示在答题页选项下方——
// 答题的过程就是建概念，选项即术语卡。答案只存选项本身，解释可随时改。
function parseOptionLines(optionsText) {
  const options = [];
  const optionNotes = [];
  optionsText.split('\n').forEach((line) => {
    const trimmed = line.trim();
    if (!trimmed) return;
    const splitIndex = trimmed.search(/[｜|]/);
    if (splitIndex === -1) {
      options.push(trimmed);
      optionNotes.push('');
    } else {
      options.push(trimmed.slice(0, splitIndex).trim());
      optionNotes.push(trimmed.slice(splitIndex + 1).trim());
    }
  });
  return { options, optionNotes };
}

function formatOptionLines(question) {
  const notes = question.option_notes || [];
  return (question.options || [])
    .map((option, index) => (notes[index] ? `${option}｜${notes[index]}` : option))
    .join('\n');
}

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    mi: 0,
    qi: -1, // -1 = 新题
    matterTitle: '',
    modules: [],
    text: '',
    note: '',
    type: 'single',
    required: false,
    optionsText: '',
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id), mi: Number(query.mi), qi: Number(query.qi) });
    wx.setNavigationBarTitle({ title: Number(query.qi) < 0 ? '添加题目' : '编辑题目' });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getMatter(this.data.id);
      const payload = res.data.payload || {};
      this._preserved = {
        pitch: payload.pitch || '',
        purpose: payload.purpose || '',
        collects_contact: !!payload.collects_contact,
      };
      const modules = payload.modules || [];
      const question = this.data.qi >= 0 ? modules[this.data.mi].questions[this.data.qi] : null;
      this.setData({
        matterTitle: res.data.title,
        moduleTitle: (modules[this.data.mi] && modules[this.data.mi].title) || '',
        modules,
        text: question ? question.text : '',
        note: (question && question.note) || '',
        type: question ? question.type : 'single',
        required: question ? !!question.required : false,
        optionsText: question ? formatOptionLines(question) : '',
      });
    });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  pickType(event) {
    this.markDirty();
    this.setData({ type: event.currentTarget.dataset.type });
  },

  onRequired(event) {
    this.markDirty();
    this.setData({ required: event.detail.value });
  },

  async save() {
    const { id, mi, qi, modules, text, note, type, required, optionsText, submitting } = this.data;
    if (submitting) return;
    if (!text.trim()) return wx.showToast({ title: '先填题目', icon: 'none' });

    const { options, optionNotes } = parseOptionLines(optionsText);
    if (type !== 'text' && options.length < 2) return wx.showToast({ title: '至少两个选项，一行一个', icon: 'none' });

    const next = modules.map((module) => ({ ...module, questions: [...module.questions] }));
    const question = { text: text.trim(), note: note.trim(), type, required };
    if (type !== 'text') {
      question.options = options;
      question.option_notes = optionNotes;
    }
    if (qi >= 0) {
      // 保留原 key：答案按它存储，改题面不换 key
      next[mi].questions[qi] = { ...next[mi].questions[qi], ...question };
      if (type === 'text') {
        delete next[mi].questions[qi].options; // 选择题改填空：不留下过时选项
        delete next[mi].questions[qi].option_notes;
      }
    } else {
      next[mi].questions.push(question); // key 由服务端生成
    }

    this.setData({ submitting: true });
    try {
      await matters.updateMatter(id, { title: this.data.matterTitle, ...this._preserved, modules: next });
      this.clearDirty();
      // 成功后不复位 submitting：按钮保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      wx.showToast({ title: '已保存', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },

  remove() {
    if (this.data.qi < 0) return wx.navigateBack();
    wx.showModal({
      title: '删除这道题？',
      content: '已收到的这道题的答案会随之退出统计',
      confirmText: '删除',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        const next = this.data.modules.map((module) => ({ ...module, questions: [...module.questions] }));
        next[this.data.mi].questions.splice(this.data.qi, 1);
        try {
          await matters.updateMatter(this.data.id, {
            title: this.data.matterTitle,
            ...this._preserved,
            modules: next,
          });
          this.clearDirty(); // 题目已删，未保存的编辑不必再拦返回
          wx.showToast({ title: '已删除', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
