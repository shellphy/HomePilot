// 管理端 · 相关方认证：入驻档案一览（商家/物业等都自助入驻），认证通过公示 / 驳回附理由
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    all: [],
    parties: [],
    statusFilter: '', // '' 全部 / pending 待认证 / approved 已认证 / rejected 未通过
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.listParties();
      this.setData({ all: res.data });
      this.applyFilter();
    });
  },

  applyFilter() {
    const { all, statusFilter } = this.data;
    this.setData({
      parties: all.filter((party) => !statusFilter || party.review_status === statusFilter),
    });
  },

  pickFilter(event) {
    this.setData({ statusFilter: event.currentTarget.dataset.filter });
    this.applyFilter();
  },

  // 审核前先看完整档案（详情页与名录共用，管理员可看未认证的）
  goDetail(event) {
    wx.navigateTo({ url: `/pages/party/index?id=${event.currentTarget.dataset.id}` });
  },

  // 认证即把「已认证」身份推给全小区，先确认
  approve(event) {
    const { id } = event.currentTarget.dataset;
    const party = this.data.all.find((item) => item.id === id);
    wx.showModal({
      title: '认证并公示？',
      content: `认证后「${party.name || 'TA'}」将以「已认证」身份对全小区公示`,
      confirmText: '认证',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.reviewParty(id, true);
          wx.showToast({ title: '已认证公示', icon: 'success' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },

  // 驳回/撤下附一句理由，归属人在详情页看到，改资料后即重新提交
  reject(event) {
    const { id } = event.currentTarget.dataset;
    const party = this.data.all.find((item) => item.id === id);
    wx.showModal({
      title: `驳回「${party.name || 'TA'}」`,
      editable: true,
      confirmText: '驳回',
      success: async ({ confirm, content }) => {
        if (!confirm) return;
        const reason = (content || '').trim();
        // 没有理由的驳回会让归属人不知道怎么改，重新提交的闭环就断了
        if (!reason) return wx.showToast({ title: '请写一句驳回理由', icon: 'none' });
        try {
          await admin.reviewParty(id, false, reason);
          wx.showToast({ title: '已驳回', icon: 'none' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
