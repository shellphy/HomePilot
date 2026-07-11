// 发起/编辑团购（groupbuy 类型的表单）
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { stateOptions } = require('../../utils/constants');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    states: [], // 编辑时来自后端下发的该事务状态机；新建不选状态（后端定初始态）
    categories: [],
    category: '',
    customCategory: '',
    title: '',
    state: '',
    targetCount: '',
    pitch: '',
    perk: '',
    terms: [],
    glossary: [],
    interestCount: {}, // {品类: 意向户数}，决策依据要贴着决策点出现
    initiatorNote: '',
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id });
    wx.setNavigationBarTitle({ title: id ? '编辑团购' : '发起团购' });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const [options, stats] = await Promise.all([
        profile.getOptions(),
        profile.getStats().catch(() => ({})), // 聚合拿不到不影响发起
      ]);

      this.setData({
        categories: options.categories,
        // 品类意向户数由后端从征集答案聚合（/stats 的 category_interest），没有就不显示
        interestCount: stats.category_interest || {},
        initiatorNote: (options.community && options.community.initiator_note) || '',
      });

      if (this.data.id) {
        const res = await matters.getMatter(this.data.id);
        const matter = res.data;
        const isPreset = options.categories.includes(matter.category);
        this.setData({
          category: isPreset ? matter.category : '',
          customCategory: isPreset ? '' : matter.category,
          title: matter.title,
          states: stateOptions(matter.states),
          state: matter.state,
          targetCount: String(matter.target_count),
          pitch: matter.pitch || '',
          perk: matter.perk || '',
          terms: matter.terms || [],
          glossary: matter.glossary || [],
        });
      }
    });
  },

  pickCategory(event) {
    this.setData({ category: event.currentTarget.dataset.value, customCategory: '' });
  },

  pickState(event) {
    this.setData({ state: event.currentTarget.dataset.value });
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
    const { id, category, customCategory, title, state, targetCount, pitch, perk, submitting } = this.data;
    if (submitting) return;

    const finalCategory = customCategory.trim() || category;
    if (!finalCategory) return wx.showToast({ title: '请选择或填写品类', icon: 'none' });
    if (!title.trim()) return wx.showToast({ title: '请填写标题', icon: 'none' });
    if (!targetCount || Number(targetCount) < 1) {
      return wx.showToast({ title: '请填写目标户数', icon: 'none' });
    }

    const payload = {
      category: finalCategory,
      title: title.trim(),
      target_count: Number(targetCount),
      pitch: pitch.trim(),
      perk: perk.trim(),
      terms: this.data.terms.filter((t) => t.label.trim() && t.value.trim()),
      glossary: this.data.glossary.filter((g) => g.term.trim() && g.explain.trim()),
    };

    this.setData({ submitting: true });
    try {
      if (id) {
        await matters.updateGroupbuy(id, { ...payload, state });
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        await matters.createGroupbuy(payload);
        wx.showModal({
          title: '已提交',
          content: '管理员通常会在 24 小时内完成审核，通过后就会出现在小区页里。你是这个团购的团长，可以在「我的」里随时查看和管理它。',
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
