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
    needsSurvey: false, // 逐人报价团每人成交价不同：公示的是成交规则与人数，不是统一价
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
        needsSurvey: !!matter.needs_survey,
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
      // 团购的收尾时刻，用 modal 把「发生了什么」讲清楚，分量对齐发起时的反馈（modal 也挡住了二次提交）
      wx.showModal({
        title: '成交公示已发布',
        content: '最终条件已对全小区公开，这次团购就此收尾。辛苦了，团长。',
        showCancel: false,
        confirmText: '好的',
        success: () => wx.navigateBack(),
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },
});
