// 相关方详情页：名录与管理端审核共用。
// 已认证对全小区可见；未认证的档案只有管理员（审核前看资料）和归属人自己（预览）能看到。
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

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
    wx.makePhoneCall({ phoneNumber: this.data.party.phone });
  },

  previewImage(event) {
    wx.previewImage({ urls: this.data.party.images, current: event.currentTarget.dataset.url });
  },
});
