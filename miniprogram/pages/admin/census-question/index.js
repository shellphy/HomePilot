// 管理端 · 题目编辑：题干 / 单选多选 / 必答 / 选项（一行一个）
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    mi: 0,
    qi: -1, // -1 = 新题
    matterTitle: '',
    modules: [],
    text: '',
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
      const res = await admin.getMatter(this.data.id);
      const modules = res.data.payload.modules || [];
      const question = this.data.qi >= 0 ? modules[this.data.mi].questions[this.data.qi] : null;
      this.setData({
        matterTitle: res.data.title,
        modules,
        text: question ? question.text : '',
        type: question ? question.type : 'single',
        required: question ? !!question.required : false,
        optionsText: question ? question.options.join('\n') : '',
      });
    });
  },

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  pickType(event) {
    this.setData({ type: event.currentTarget.dataset.type });
  },

  onRequired(event) {
    this.setData({ required: event.detail.value });
  },

  async save() {
    const { id, mi, qi, modules, text, type, required, optionsText, submitting } = this.data;
    if (submitting) return;
    if (!text.trim()) return wx.showToast({ title: '先填题目', icon: 'none' });

    const options = optionsText.split('\n').map((line) => line.trim()).filter(Boolean);
    if (options.length < 2) return wx.showToast({ title: '至少两个选项，一行一个', icon: 'none' });

    const next = modules.map((module) => ({ ...module, questions: [...module.questions] }));
    const question = { text: text.trim(), type, required, options };
    if (qi >= 0) {
      // 保留原 key：答案按它存储，改题面不换 key
      next[mi].questions[qi] = { ...next[mi].questions[qi], ...question };
    } else {
      next[mi].questions.push(question); // key 由服务端生成
    }

    this.setData({ submitting: true });
    try {
      await admin.updateMatter(id, { title: this.data.matterTitle, payload: { modules: next } });
      wx.showToast({ title: '已保存', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
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
          await admin.updateMatter(this.data.id, {
            title: this.data.matterTitle,
            payload: { modules: next },
          });
          wx.showToast({ title: '已删除', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
