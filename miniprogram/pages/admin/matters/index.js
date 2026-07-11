// 管理端 · 事项列表：审核队列与全部事项，发布新事项的入口
const admin = require('../../../utils/api/admin');
const profile = require('../../../utils/api/profile');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    all: [],
    matters: [],       // 过滤后的展示列表
    matterTypes: [],
    typeFilter: '',    // '' = 全部类型
    stateFilter: '',   // '' 全部 / pending 待审核 / approved 已公示
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const [res, options] = await Promise.all([
        admin.listMatters(false),
        profile.getOptions(),
      ]);
      this.setData({ all: res.data, matterTypes: options.matter_types || [] });
      this.applyFilters();
    });
  },

  applyFilters() {
    const { all, typeFilter, stateFilter } = this.data;
    this.setData({
      matters: all
        .filter((matter) => !typeFilter || matter.type === typeFilter)
        .filter((matter) => !stateFilter
          || (stateFilter === 'pending' ? !matter.is_approved : matter.is_approved)),
    });
  },

  pickType(event) {
    this.setData({ typeFilter: event.currentTarget.dataset.type });
    this.applyFilters();
  },

  pickState(event) {
    this.setData({ stateFilter: event.currentTarget.dataset.state });
    this.applyFilters();
  },

  goEdit(event) {
    wx.navigateTo({ url: `/pages/admin/matter-form/index?id=${event.currentTarget.dataset.id}` });
  },

  // 通过即对全小区公示，且按钮在列表行上容易误触，先确认再执行
  approve(event) {
    const id = event.currentTarget.dataset.id;
    const matter = this.data.matters.find((item) => item.id === id);
    wx.showModal({
      title: '通过并公示？',
      content: `「${matter.title}」将立即对全小区可见`,
      confirmText: '通过',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.approveMatter(id, true);
          wx.showToast({ title: '已通过并公示', icon: 'success' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },

  // 驳回不删除：附一句理由，发起人在详情页看到，修改后即重新提交
  reject(event) {
    const id = event.currentTarget.dataset.id;
    const matter = this.data.matters.find((item) => item.id === id);
    wx.showModal({
      title: `驳回「${matter.title}」`,
      editable: true,
      placeholderText: '写一句驳回理由（发起人会看到）',
      confirmText: '驳回',
      success: async ({ confirm, content }) => {
        if (!confirm) return;
        try {
          await admin.approveMatter(id, false, (content || '').trim());
          wx.showToast({ title: '已驳回', icon: 'none' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },

  // 管理员可发布任何类型（公告、征集这类业主不能自发的也在内）
  goCreate() {
    const types = this.data.matterTypes;
    wx.showActionSheet({
      itemList: types.map((type) => `发布${type.label}`),
      success: ({ tapIndex }) => {
        wx.navigateTo({ url: `/pages/admin/matter-form/index?type=${types[tapIndex].key}` });
      },
    });
  },
});
