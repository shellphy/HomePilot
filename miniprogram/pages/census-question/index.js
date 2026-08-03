const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');
const { syncInputValue } = require('../../utils/input');

function parseOptionLines(optionsText) {
  return optionsText
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);
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
    optionsText: '',
    readOnly: false,
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id), mi: Number(query.mi), qi: Number(query.qi) });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getMatter(this.data.id);
      const payload = res.data.payload || {};
      this._preserved = {
        purpose: payload.purpose || '',
        collects_contact: !!payload.collects_contact,
      };
      const modules = payload.modules || [];
      const question = this.data.qi >= 0 ? modules[this.data.mi].questions[this.data.qi] : null;
      const readOnly = !!res.data.census_schema_locked && this.data.qi >= 0;
      this.setData({
        matterTitle: res.data.title,
        moduleTitle: (modules[this.data.mi] && modules[this.data.mi].title) || '',
        modules,
        text: question ? question.text : '',
        note: (question && question.note) || '',
        type: question ? question.type : 'single',
        optionsText: question ? (question.options || []).join('\n') : '',
        readOnly,
      });
      let navigationTitle = '编辑题目';
      if (readOnly) {
        navigationTitle = '查看题目';
      } else if (this.data.qi < 0) {
        navigationTitle = '添加题目';
      }
      wx.setNavigationBarTitle({ title: navigationTitle });
    });
  },

  onInput(event) {
    this.markDirty();
    syncInputValue(this, event.currentTarget.dataset.field, event.detail.value);
  },

  pickType(event) {
    if (this.data.readOnly) return;
    this.markDirty();
    this.setData({ type: event.currentTarget.dataset.type });
  },

  async save() {
    const { id, mi, qi, modules, text, note, type, optionsText, submitting } = this.data;
    if (submitting) return;
    if (!text.trim()) return wx.showToast({ title: '先填题目', icon: 'none' });

    const options = parseOptionLines(optionsText);
    if (type !== 'text' && options.length < 2) return wx.showToast({ title: '至少两个选项，一行一个', icon: 'none' });

    const next = modules.map((module) => ({ ...module, questions: [...module.questions] }));
    const question = { text: text.trim(), note: note.trim(), type };
    if (type !== 'text') {
      question.options = options;
    }
    if (qi >= 0) {
      // 保留原 key：答案按它存储，改题面不换 key
      next[mi].questions[qi] = { ...next[mi].questions[qi], ...question };
      if (type === 'text') {
        delete next[mi].questions[qi].options;
      }
    } else {
      next[mi].questions.push(question);
    }

    this.setData({ submitting: true });
    try {
      await matters.updateMatter(id, { title: this.data.matterTitle, ...this._preserved, modules: next });
      this.clearDirty();
      // 保持 loading，防止提示关闭前重复提交。
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
          this.clearDirty();
          wx.showToast({ title: '已删除', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
