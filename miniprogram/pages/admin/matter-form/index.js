// 管理端 · 事务发布/编辑：所有类型共用，按类型显示对应字段
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    type: 'notice',
    typeLabel: '',
    title: '',
    category: '',
    state: '',
    states: {},        // {key: label}，编辑时用于状态流转
    stateKeys: [],
    isApproved: true,
    targetCount: '',
    // 按类型使用的 payload 字段
    body: '',
    pitch: '',
    perk: '',
    collectsContact: false,
    terms: [],
    glossary: [],
    moduleCount: 0,
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id, type: query.type || 'notice' });
    wx.setNavigationBarTitle({ title: id ? '编辑事务' : '发布事务' });
    if (!id) this.setData({ loaded: true });
  },

  onShow() {
    if (this.data.id) this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.getMatter(this.data.id);
      const matter = res.data;
      const payload = matter.payload || {};
      this.setData({
        type: matter.type,
        typeLabel: matter.type_label,
        title: matter.title,
        category: matter.category || '',
        state: matter.state,
        states: matter.states,
        stateKeys: Object.keys(matter.states),
        isApproved: matter.is_approved,
        targetCount: matter.target_count ? String(matter.target_count) : '',
        body: payload.body || '',
        pitch: payload.pitch || '',
        perk: payload.perk || '',
        collectsContact: !!payload.collects_contact,
        terms: payload.terms || [],
        glossary: payload.glossary || [],
        moduleCount: (payload.modules || []).length,
      });
      wx.setNavigationBarTitle({ title: `编辑${matter.type_label}` });
    });
  },

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  onSwitch(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  pickState(event) {
    this.setData({ state: event.currentTarget.dataset.state });
  },

  // 条目列表（团购条件 / 买前必懂）的增删改
  addRow(event) {
    const list = event.currentTarget.dataset.list;
    const blank = list === 'terms' ? { label: '', value: '' } : { term: '', explain: '' };
    this.setData({ [list]: [...this.data[list], blank] });
  },

  removeRow(event) {
    const { list, index } = event.currentTarget.dataset;
    const rows = [...this.data[list]];
    rows.splice(index, 1);
    this.setData({ [list]: rows });
  },

  onRowInput(event) {
    const { list, index, field } = event.currentTarget.dataset;
    this.setData({ [`${list}[${index}].${field}`]: event.detail.value });
  },

  goSchema() {
    wx.navigateTo({ url: `/pages/admin/census-schema/index?id=${this.data.id}` });
  },

  goRecords() {
    wx.navigateTo({ url: `/pages/admin/records/index?id=${this.data.id}` });
  },

  async submit() {
    const data = this.data;
    if (data.submitting) return;
    if (!data.title.trim()) return wx.showToast({ title: '先填标题', icon: 'none' });

    const payload = {};
    if (data.type === 'notice') payload.body = data.body.trim();
    if (data.type !== 'notice') payload.pitch = data.pitch.trim();
    if (data.type === 'groupbuy') {
      payload.perk = data.perk.trim();
      payload.terms = data.terms.filter((row) => row.label.trim() && row.value.trim());
      payload.glossary = data.glossary.filter((row) => row.term.trim() && row.explain.trim());
    }
    if (data.type === 'census') payload.collects_contact = data.collectsContact;

    const body = {
      title: data.title.trim(),
      category: data.category.trim(),
      is_approved: data.isApproved,
      ...(data.state ? { state: data.state } : {}),
      ...(data.targetCount ? { target_count: Number(data.targetCount) } : { target_count: 0 }),
      payload,
    };

    this.setData({ submitting: true });
    try {
      if (data.id) {
        await admin.updateMatter(data.id, body);
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        const res = await admin.createMatter({ type: data.type, ...body });
        // 新发布的征集顺路去配问卷，其余类型直接返回
        if (data.type === 'census') {
          wx.redirectTo({ url: `/pages/admin/census-schema/index?id=${res.data.id}` });
        } else {
          wx.showToast({ title: '已发布', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        }
      }
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },

  remove() {
    wx.showModal({
      title: '删除这件事务？',
      content: '相关的表态记录会一并删除，不可恢复',
      confirmText: '删除',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.deleteMatter(this.data.id);
          wx.showToast({ title: '已删除', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
