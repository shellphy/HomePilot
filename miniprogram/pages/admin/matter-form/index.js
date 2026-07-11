// 管理端 · 事项发布/编辑：所有类型共用，按类型显示对应字段
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');
const dirty = require('../../../behaviors/dirty');
const { draftGlossaryRow } = require('../../../utils/glossary-draft');

Page({
  behaviors: [load, dirty],

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
    needsSurvey: false, // 团购：按户出方案（业主端发起时锁定，管理端作为纠错通道可改）
    collectsContact: false,
    terms: [],
    glossary: [],
    moduleCount: 0,
    // 配套问卷（仅征集）：挂到哪个团购上
    relatedMatterId: null,
    relatedMatterTitle: '',
    // 署名发起方（仅征集）：物业/业委会/商家的调研亮明身份
    initiatorPartyId: null,
    initiatorPartyLabel: '',
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id, type: query.type || 'notice' });
    wx.setNavigationBarTitle({ title: id ? '编辑事项' : '发布事项' });
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
        needsSurvey: !!payload.needs_survey,
        collectsContact: !!payload.collects_contact,
        terms: payload.terms || [],
        glossary: payload.glossary || [],
        moduleCount: (payload.modules || []).length,
        relatedMatterId: matter.related_matter_id || null,
        initiatorPartyId: matter.initiator_party_id || null,
      });
      wx.setNavigationBarTitle({ title: `编辑${matter.type_label}` });
      if (matter.type === 'census' && matter.related_matter_id) {
        await this.resolveRelatedTitle(matter.related_matter_id);
      }
      if (matter.type === 'census' && matter.initiator_party_id) {
        await this.resolveInitiatorPartyLabel(matter.initiator_party_id);
      }
    });
  },

  async resolveInitiatorPartyLabel(partyId) {
    const parties = await this.loadParties();
    const hit = parties.find((row) => row.id === partyId);
    this.setData({ initiatorPartyLabel: hit ? `${hit.type_label} · ${hit.name}` : `相关方 #${partyId}` });
  },

  async loadParties() {
    if (!this._parties) {
      const res = await admin.listParties();
      // 署名的常客是治理方（物业/业委会/开发商），排前面；商家随后
      this._parties = [...res.data].sort((a, b) => (a.type === 'merchant') - (b.type === 'merchant'));
    }
    return this._parties;
  },

  // 署名发起方：action-sheet 单选（小区官方 + 最多 5 个相关方）
  async chooseInitiatorParty() {
    let parties;
    try {
      parties = (await this.loadParties()).slice(0, 5);
    } catch (error) {
      return wx.showToast({ title: error.message, icon: 'none' });
    }
    if (!parties.length) {
      return wx.showToast({ title: '还没有相关方可署名', icon: 'none' });
    }
    wx.showActionSheet({
      itemList: ['小区官方（不署名）', ...parties.map((row) => `${row.type_label} · ${row.name}`)],
      success: ({ tapIndex }) => {
        this.markDirty();
        if (tapIndex === 0) {
          this.setData({ initiatorPartyId: null, initiatorPartyLabel: '' });
        } else {
          const hit = parties[tapIndex - 1];
          this.setData({ initiatorPartyId: hit.id, initiatorPartyLabel: `${hit.type_label} · ${hit.name}` });
        }
      },
    });
  },

  // 已挂靠团购的标题回显（列表里找不到时退化为编号）
  async resolveRelatedTitle(relatedId) {
    const groupbuys = await this.loadGroupbuys();
    const hit = groupbuys.find((row) => row.id === relatedId);
    this.setData({ relatedMatterTitle: hit ? hit.title : `事项 #${relatedId}` });
  },

  async loadGroupbuys() {
    if (!this._groupbuys) {
      const res = await admin.listMatters();
      this._groupbuys = res.data.filter((row) => row.type === 'groupbuy');
    }
    return this._groupbuys;
  },

  // 挂到团购：action-sheet 单选（最多列最近 5 个团购 + 不挂靠）
  async chooseRelatedMatter() {
    let groupbuys;
    try {
      groupbuys = (await this.loadGroupbuys()).slice(0, 5);
    } catch (error) {
      return wx.showToast({ title: error.message, icon: 'none' });
    }
    if (!groupbuys.length) {
      return wx.showToast({ title: '还没有团购可挂靠', icon: 'none' });
    }
    wx.showActionSheet({
      itemList: ['不挂靠', ...groupbuys.map((row) => row.title)],
      success: ({ tapIndex }) => {
        this.markDirty();
        if (tapIndex === 0) {
          this.setData({ relatedMatterId: null, relatedMatterTitle: '' });
        } else {
          const hit = groupbuys[tapIndex - 1];
          this.setData({ relatedMatterId: hit.id, relatedMatterTitle: hit.title });
        }
      },
    });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  onSwitch(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  chooseState() {
    const { states, stateKeys } = this.data;
    wx.showActionSheet({
      itemList: stateKeys.map((key) => states[key]),
      success: ({ tapIndex }) => {
        this.markDirty();
        this.setData({ state: stateKeys[tapIndex] });
      },
    });
  },

  // 条目列表（团购条件 / 买前必懂）的增删改
  addRow(event) {
    this.markDirty();
    const { list } = event.currentTarget.dataset;
    const blank = list === 'terms' ? { label: '', value: '' } : { term: '', explain: '', judge: '', caution: '' };
    this.setData({ [list]: [...this.data[list], blank] });
  },

  removeRow(event) {
    this.markDirty();
    const { list, index } = event.currentTarget.dataset;
    const rows = [...this.data[list]];
    rows.splice(index, 1);
    this.setData({ [list]: rows });
  },

  onRowInput(event) {
    this.markDirty();
    const { list, index, field } = event.currentTarget.dataset;
    this.setData({ [`${list}[${index}].${field}`]: event.detail.value });
  },

  aiDraft(event) {
    draftGlossaryRow(this, event.currentTarget.dataset.index);
  },

  goSchema() {
    wx.navigateTo({ url: `/pages/admin/census-schema/index?id=${this.data.id}` });
  },

  goRegistrations() {
    wx.navigateTo({ url: `/pages/admin/registrations/index?id=${this.data.id}` });
  },

  goCensusText() {
    wx.navigateTo({ url: `/pages/admin/census-text/index?id=${this.data.id}` });
  },

  async submit() {
    const { data } = this;
    if (data.submitting) return;
    if (!data.title.trim()) return wx.showToast({ title: '先填标题', icon: 'none' });
    // 与后端规则（业主端同一份）对齐，别等 422 才发现
    if (data.type === 'notice' && !data.body.trim()) {
      return wx.showToast({ title: '公告得有正文', icon: 'none' });
    }
    if (data.type === 'groupbuy') {
      if (!data.category.trim()) return wx.showToast({ title: '请填写品类', icon: 'none' });
      if (!data.targetCount || Number(data.targetCount) < 1) {
        return wx.showToast({ title: '请填写目标人数', icon: 'none' });
      }
    }

    const payload = {};
    if (data.type === 'notice') payload.body = data.body.trim();
    if (data.type !== 'notice') payload.pitch = data.pitch.trim();
    if (data.type === 'groupbuy') {
      payload.perk = data.perk.trim();
      payload.needs_survey = data.needsSurvey;
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
      // 显式传 null 表示解除挂靠/去署名（后端按键是否存在区分）
      ...(data.type === 'census' ? { related_matter_id: data.relatedMatterId, initiator_party_id: data.initiatorPartyId } : {}),
      payload,
    };

    this.setData({ submitting: true });
    try {
      if (data.id) {
        await admin.updateMatter(data.id, body);
        this.clearDirty();
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        const res = await admin.createMatter({ type: data.type, ...body });
        this.clearDirty();
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
      title: '删除这件事项？',
      content: '相关的表态记录会一并删除，不可恢复',
      confirmText: '删除',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.deleteMatter(this.data.id);
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
