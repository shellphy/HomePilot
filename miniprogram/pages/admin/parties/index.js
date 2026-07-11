// 管理端 · 相关方：入驻档案一览与认证；物业/业委会等不自助入驻的身份在这里建档并绑定成员
const admin = require('../../../utils/api/admin');
const profile = require('../../../utils/api/profile');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    all: [],
    parties: [],
    listedFilter: '', // '' 全部 / yes 已认证 / no 未认证
    partyTypes: [],
    showCreate: false,
    newType: '',
    newName: '',
    newCategory: '',
    creating: false,
    expandedId: null, // 展开成员管理的相关方
    bindKey: '',      // 待绑定成员的 ID 或手机号
    binding: false,
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const [res, options] = await Promise.all([admin.listParties(), profile.getOptions()]);
      this.setData({
        all: res.data,
        partyTypes: options.party_types || [],
      });
      this.applyFilter();
    });
  },

  applyFilter() {
    const { all, listedFilter } = this.data;
    this.setData({
      parties: all.filter((party) => !listedFilter
        || (listedFilter === 'yes' ? party.is_listed : !party.is_listed)),
    });
  },

  pickFilter(event) {
    this.setData({ listedFilter: event.currentTarget.dataset.filter });
    this.applyFilter();
  },

  toggle(event) {
    const { id, index } = event.currentTarget.dataset;
    const listed = event.detail.value;
    if (listed) return this.certify(id, index, true);
    // 撤下会从公示名单消失，先确认；取消时重设 checked 把开关拨回去
    wx.showModal({
      title: '撤下这个相关方？',
      content: '撤下后将从小区公示名单消失',
      confirmText: '撤下',
      confirmColor: '#e34d59',
      success: ({ confirm }) => {
        if (confirm) this.certify(id, index, false);
        else this.setData({ [`parties[${index}].is_listed`]: true });
      },
    });
  },

  async certify(id, index, listed) {
    try {
      await admin.certifyParty(id, listed);
      const all = this.data.all.map((party) => (party.id === id ? { ...party, is_listed: listed } : party));
      this.setData({ all, [`parties[${index}].is_listed`]: listed });
      wx.showToast({ title: listed ? '已认证公示' : '已撤下', icon: 'none' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.reload();
    }
  },

  // ---- 建档（物业/开发商/业委会等由管理员建档，商家一般自助入驻）----

  toggleCreate() {
    this.setData({ showCreate: !this.data.showCreate });
  },

  pickNewType(event) {
    this.setData({ newType: event.currentTarget.dataset.key });
  },

  onField(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async createParty() {
    const { newType, newName, newCategory, creating } = this.data;
    if (creating) return;
    if (!newType) return wx.showToast({ title: '请选择类型', icon: 'none' });
    if (!newName.trim()) return wx.showToast({ title: '请填写名称', icon: 'none' });

    this.setData({ creating: true });
    try {
      await admin.createParty({ type: newType, name: newName.trim(), category: newCategory.trim() });
      wx.showToast({ title: '已建档并认证', icon: 'success' });
      this.setData({ showCreate: false, newType: '', newName: '', newCategory: '' });
      await this.reload();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ creating: false });
    }
  },

  // ---- 成员绑定（点击相关方行展开）----

  expand(event) {
    const { id } = event.currentTarget.dataset;
    this.setData({
      expandedId: this.data.expandedId === id ? null : id,
      bindKey: '',
    });
  },

  onBindInput(event) {
    this.setData({ bindKey: event.detail.value });
  },

  async bindMember(event) {
    const { id } = event.currentTarget.dataset;
    const { bindKey, binding } = this.data;
    if (binding) return;
    if (!bindKey.trim()) return wx.showToast({ title: '请填成员 ID 或手机号', icon: 'none' });

    this.setData({ binding: true });
    try {
      const res = await admin.bindPartyMember(id, bindKey.trim());
      this.updateParty(res.data);
      this.setData({ bindKey: '' });
      wx.showToast({ title: '已绑定', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ binding: false });
    }
  },

  unbindMember(event) {
    const { id, member, name } = event.currentTarget.dataset;
    wx.showModal({
      title: '解绑这个成员？',
      content: `${name || '该成员'}将切回业主身份，相关方档案保留。`,
      confirmText: '解绑',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          const res = await admin.unbindPartyMember(id, member);
          this.updateParty(res.data);
          wx.showToast({ title: '已解绑', icon: 'none' });
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },

  // 用后端返回的最新档案替换本地这一条（含成员列表与计数）
  updateParty(fresh) {
    const all = this.data.all.map((party) => (party.id === fresh.id ? fresh : party));
    this.setData({ all });
    this.applyFilter();
  },
});
