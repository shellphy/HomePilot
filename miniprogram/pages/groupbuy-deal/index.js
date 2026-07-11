// 成交公示（groupbuy 类型的收尾表单）
const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    finalTerms: [],
    finalNote: '',
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getMatter(this.data.id);
      const matter = res.data;
      this.setData({
        // 默认把团购条件带进来，团长在此基础上改成最终成交版
        finalTerms: matter.final_terms.length ? matter.final_terms : (matter.terms || []),
        finalNote: matter.final_note || '',
      });
    });
  },

  onRowInput(event) {
    this.markDirty();
    const { index, key } = event.currentTarget.dataset;
    this.setData({ [`finalTerms[${index}].${key}`]: event.detail.value });
  },

  onNoteInput(event) {
    this.markDirty();
    this.setData({ finalNote: event.detail.value });
  },

  addTerm() {
    this.markDirty();
    this.setData({ finalTerms: [...this.data.finalTerms, { label: '', value: '' }] });
  },

  removeTerm(event) {
    this.markDirty();
    const finalTerms = this.data.finalTerms.filter((_, i) => i !== event.currentTarget.dataset.index);
    this.setData({ finalTerms });
  },

  async submit() {
    const { id, finalNote, submitting } = this.data;
    if (submitting) return;

    const finalTerms = this.data.finalTerms.filter((t) => t.label.trim() && t.value.trim());
    if (!finalTerms.length) return wx.showToast({ title: '至少公示一条成交条件', icon: 'none' });

    this.setData({ submitting: true });
    try {
      await matters.publishDeal(id, finalTerms, finalNote.trim());
      this.clearDirty();
      // 成功后不复位 submitting：按钮保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      wx.showToast({ title: '成交公示已发布', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },
});
