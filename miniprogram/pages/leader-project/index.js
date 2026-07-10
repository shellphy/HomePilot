const { request } = require('../../utils/request');

const STATUSES = [
  { value: 'seeking', label: '意向征集' },
  { value: 'negotiating', label: '谈判中' },
  { value: 'open', label: '接龙中' },
  { value: 'done', label: '已成团' },
];

Page({
  data: {
    id: null,
    statuses: STATUSES,
    categories: [],
    category: '',
    customCategory: '',
    title: '',
    status: 'seeking',
    targetHouseholds: '',
    pitch: '',
    perk: '',
    terms: [],
    glossary: [],
    submitting: false,
  },

  async onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id });
    wx.setNavigationBarTitle({ title: id ? '编辑团购' : '发起团购' });

    try {
      const options = await request('/options');
      this.setData({ categories: options.categories });

      if (id) {
        const res = await request(`/projects/${id}`);
        const project = res.data;
        this.setData({
          category: this.data.categories.includes(project.category) ? project.category : '',
          customCategory: this.data.categories.includes(project.category) ? '' : project.category,
          title: project.title,
          status: project.status,
          targetHouseholds: String(project.target_households),
          pitch: project.pitch || '',
          perk: project.perk || '',
          terms: project.terms || [],
          glossary: project.glossary || [],
        });
      }
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  pickCategory(event) {
    this.setData({ category: event.currentTarget.dataset.value, customCategory: '' });
  },

  pickStatus(event) {
    this.setData({ status: event.currentTarget.dataset.value });
  },

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
    if (event.currentTarget.dataset.field === 'customCategory' && event.detail.value) {
      this.setData({ category: '' });
    }
  },

  onRowInput(event) {
    const { list, index, key } = event.currentTarget.dataset;
    this.setData({ [`${list}[${index}].${key}`]: event.detail.value });
  },

  addTerm() {
    this.setData({ terms: [...this.data.terms, { label: '', value: '' }] });
  },

  removeTerm(event) {
    const terms = this.data.terms.filter((_, i) => i !== event.currentTarget.dataset.index);
    this.setData({ terms });
  },

  addGlossary() {
    this.setData({ glossary: [...this.data.glossary, { term: '', explain: '' }] });
  },

  removeGlossary(event) {
    const glossary = this.data.glossary.filter((_, i) => i !== event.currentTarget.dataset.index);
    this.setData({ glossary });
  },

  async submit() {
    const { id, category, customCategory, title, status, targetHouseholds, pitch, perk, submitting } = this.data;
    if (submitting) return;

    const finalCategory = customCategory.trim() || category;
    if (!finalCategory) return wx.showToast({ title: '请选择或填写品类', icon: 'none' });
    if (!title.trim()) return wx.showToast({ title: '请填写标题', icon: 'none' });
    if (!targetHouseholds || Number(targetHouseholds) < 1) {
      return wx.showToast({ title: '请填写目标户数', icon: 'none' });
    }

    const terms = this.data.terms.filter((t) => t.label.trim() && t.value.trim());
    const glossary = this.data.glossary.filter((g) => g.term.trim() && g.explain.trim());

    this.setData({ submitting: true });
    try {
      await request(id ? `/projects/${id}` : '/projects', {
        method: id ? 'PUT' : 'POST',
        data: {
          category: finalCategory,
          title: title.trim(),
          status,
          target_households: Number(targetHouseholds),
          pitch: pitch.trim(),
          perk: perk.trim(),
          terms,
          glossary,
        },
      });
      if (id) {
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        wx.showModal({
          title: '已提交',
          content: '管理员审核通过后就会出现在团购列表里。你是这个团购的团长，可以在「我的」里随时查看和管理它。',
          showCancel: false,
          confirmText: '好的',
          success: () => wx.navigateBack(),
        });
      }
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
