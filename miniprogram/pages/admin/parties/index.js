// 管理端 · 相关方认证：入驻档案一览，认证后进入公示名单
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
});
