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

  async toggle(event) {
    const { id, index } = event.currentTarget.dataset;
    const listed = event.detail.value;
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
