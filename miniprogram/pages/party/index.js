// 相关方详情页：名录与管理端审核共用。
// 已认证对全小区公示；未认证的档案仅管理员与归属人可见。
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');
const { contactPhone } = require('../../utils/phone');

Page({
  behaviors: [load],

  data: {
    id: null,
    party: null,
    note: '',
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    if (this.data.id) this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await profile.getParty(this.data.id);
      const party = res.data;
      this.setData({
        party,
        note: party.deal_count
          ? `成团 ${party.deal_count} 单${party.rating ? ` · ★${party.rating}（${party.review_count} 条评价）` : ''}`
          : '',
      });
      wx.setNavigationBarTitle({ title: party.name });
    });
  },

  callParty() {
    contactPhone(this.data.party.phone);
  },

  previewImage(event) {
    wx.previewImage({ urls: this.data.party.images, current: event.currentTarget.dataset.url });
  },
});
