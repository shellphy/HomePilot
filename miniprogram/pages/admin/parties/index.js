// 管理端 · 相关方认证：入驻档案一览（商家/物业等都自助入驻），认证后进入公示身份
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    all: [],
    parties: [],
    listedFilter: '', // '' 全部 / yes 已认证 / no 未认证
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

  // 审核前先看完整档案（详情页与名录共用，管理员可看未认证的）
  goDetail(event) {
    wx.navigateTo({ url: `/pages/party/index?id=${event.currentTarget.dataset.id}` });
  },

  toggle(event) {
    const { id, index } = event.currentTarget.dataset;
    const listed = event.detail.value;
    if (listed) return this.certify(id, true);
    // 撤下会失去公示身份，先确认；取消时重设 checked 把开关拨回去
    wx.showModal({
      title: '撤下这个相关方？',
      content: '撤下后将失去「已认证」公示身份',
      confirmText: '撤下',
      confirmColor: '#e34d59',
      success: ({ confirm }) => {
        if (confirm) this.certify(id, false);
        else this.setData({ [`parties[${index}].is_listed`]: true });
      },
    });
  },

  async certify(id, listed) {
    try {
      await admin.certifyParty(id, listed);
      const all = this.data.all.map((party) => (party.id === id ? { ...party, is_listed: listed } : party));
      this.setData({ all });
      // 重跑筛选：在「未认证」桶里处理完的条目按新状态离开当前列表，不滞留
      this.applyFilter();
      wx.showToast({ title: listed ? '已认证公示' : '已撤下', icon: 'none' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.reload();
    }
  },
});
