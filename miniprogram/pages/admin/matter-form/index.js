// 统一创作/编辑事项：所有身份共用一套表单与 /matters 接口，按类型显示对应内容字段。
// 内容字段（标题/说明/品类/目标数/团购条件/征集设置/问卷入口）人人可编辑；
// 状态流转、公示开关、署名发起方、登记明细/文本题归纳、删除等管理动作按 is_admin 显示。
const matters = require('../../../utils/api/matters');
const admin = require('../../../utils/api/admin');
const { getMe } = require('../../../utils/me');
const load = require('../../../behaviors/load');
const dirty = require('../../../behaviors/dirty');
const { guardProfileError } = require('../../../utils/profile-guard');
const { requestSubscribe } = require('../../../utils/subscribe');
const { draftGlossaryRow } = require('../../../utils/glossary-draft');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    type: 'notice',
    typeLabel: '',
    isAdmin: false, // 管理动作（状态/公示/署名/明细/删除）的显示开关
    title: '',
    category: '',
    state: '',
    states: {},        // {key: label}，编辑时用于状态流转（管理员用全部状态含旁路终态）
    stateKeys: [],
    isApproved: true,
    reviewStatus: '',      // pending / rejected / approved：编辑时回显当前审核态
    reviewStatusLabel: '',
    rejectReason: '',
    targetCount: '',
    // 按类型使用的内容字段
    body: '',
    pitch: '',
    purpose: '', // 仅征集：发起目的自由文本
    perk: '',
    needsSurvey: false, // 团购：按户出方案（业主端发起时锁定，管理端作为纠错通道可改）
    collectsContact: false,
    terms: [],
    glossary: [],
    moduleCount: 0,
    // 署名发起方（仅征集）：物业/业委会/商家的调研亮明身份
    initiatorPartyId: null,
    initiatorPartyLabel: '',
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id, type: query.type || 'notice' });
    wx.setNavigationBarTitle({ title: id ? '编辑' : '发起' });
    if (!id) this.setData({ loaded: true });
  },

  onShow() {
    if (this.data.id) this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const [me, res] = await Promise.all([getMe(), matters.getMatter(this.data.id)]);
      const matter = res.data;
      const payload = matter.payload || {};
      this.setData({
        isAdmin: !!me.is_admin,
        type: matter.type,
        typeLabel: matter.type_label,
        title: matter.title,
        category: matter.category || '',
        // 审核态回显（三态：pending/approved/rejected + 驳回理由），管理端编辑器据此渲染公示开关/驳回提示
        reviewStatus: matter.review_status,
        reviewStatusLabel: matter.review_status_label,
        rejectReason: matter.reject_reason || '',
        targetCount: matter.target_count ? String(matter.target_count) : '',
        // 内容字段一律读平铺（对所有人可见），不依赖管理员专属的 payload
        body: matter.body || '',
        pitch: matter.pitch || '',
        perk: matter.perk || '',
        needsSurvey: !!matter.needs_survey,
        terms: matter.terms || [],
        glossary: matter.glossary || [],
        purpose: payload.purpose || '',
        collectsContact: !!payload.collects_contact,
        moduleCount: (payload.modules || []).length,
        // 管理动作用到的字段（非管理员不下发）
        state: matter.state,
        states: matter.all_states || matter.states || {},
        stateKeys: Object.keys(matter.all_states || matter.states || {}),
        isApproved: matter.is_approved,
        initiatorPartyId: matter.initiator_party_id || null,
      });
      wx.setNavigationBarTitle({ title: `编辑${matter.type_label}` });
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

  // 收敛按类型的内容字段为一份顶层 body（不包 payload，后端 payloadFrom 自行归拢）
  buildContent() {
    const { data } = this;
    const content = { title: data.title.trim() };
    if (data.type === 'notice') {
      content.body = data.body.trim();
    } else {
      content.pitch = data.pitch.trim();
    }
    if (data.type !== 'notice' && data.type !== 'census') {
      content.target_count = data.targetCount ? Number(data.targetCount) : 0;
    }
    if (data.type === 'groupbuy') {
      content.category = data.category.trim();
      content.perk = data.perk.trim();
      content.needs_survey = data.needsSurvey;
      content.terms = data.terms.filter((row) => row.label.trim() && row.value.trim());
      content.glossary = data.glossary.filter((row) => row.term.trim() && row.explain.trim());
    }
    if (data.type === 'census') {
      content.purpose = data.purpose.trim();
      content.collects_contact = data.collectsContact;
    }
    return content;
  },

  async submit() {
    const { data } = this;
    if (data.submitting) return;
    if (!data.title.trim()) return wx.showToast({ title: '先填标题', icon: 'none' });
    // 与后端规则对齐，别等 422 才发现
    if (data.type === 'notice' && !data.body.trim()) {
      return wx.showToast({ title: '公告得有正文', icon: 'none' });
    }
    if (data.type === 'groupbuy') {
      if (!data.category.trim()) return wx.showToast({ title: '请填写品类', icon: 'none' });
      if (!data.targetCount || Number(data.targetCount) < 1) {
        return wx.showToast({ title: '请填写目标人数', icon: 'none' });
      }
    }

    const body = this.buildContent();
    // 管理动作字段只在管理员编辑时下发（后端也按 is_admin 授权）
    if (data.isAdmin) {
      if (data.state) body.state = data.state;
      // 显式传 null 表示去署名（后端按键是否存在区分）
      if (data.type === 'census') body.initiator_party_id = data.initiatorPartyId;
      // 「公示到小区页」开关（仅编辑时有效）：后端按 review_status 落地（勾上→公示，撤下→回待审核）。
      // 创建一律进待审队列，不下发此开关
      if (data.id) body.is_approved = data.isApproved;
    }

    this.setData({ submitting: true });
    try {
      // 提交顺手收一次订阅授权：审核结果/新报名的通知才有额度可推
      await requestSubscribe();
      if (data.id) {
        await matters.updateMatter(data.id, body);
        this.clearDirty();
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        const res = await matters.createMatter(data.type, body);
        this.clearDirty();
        if (data.type === 'census') {
          // 征集顺路去配问卷（发起人本人也能读回 payload 编辑，见 MatterResource）
          wx.redirectTo({ url: `/pages/admin/census-schema/index?id=${res.data.id}` });
        } else if (data.isAdmin) {
          wx.showToast({ title: '已发布', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } else {
          wx.showModal({
            title: '已提交',
            content: '通常 24 小时内完成审核，通过后就会出现在小区页里。这件事由你牵头，可以在「我的」里随时查看和管理它。',
            showCancel: false,
            confirmText: '好的',
            success: () => wx.navigateBack(),
          });
        }
      }
    } catch (error) {
      if (!guardProfileError(error, '发起前请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
      // 只在失败时复位：成功分支保持 loading 直到离开页面，堵住 toast 里的二次提交
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
          await matters.deleteMatter(this.data.id);
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
