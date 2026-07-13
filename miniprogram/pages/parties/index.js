// 已认证商家名录：管理员认证过的相关方 + 各家的成团数与评价沉淀
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');
const { contactPhone } = require('../../utils/phone');

Page({
  behaviors: [load],

  data: {
    parties: [],
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  callParty(event) {
    contactPhone(event.currentTarget.dataset.phone);
  },

  goDetail(event) {
    wx.navigateTo({ url: `/pages/party/index?id=${event.currentTarget.dataset.id}` });
  },

  // 相关方入驻：去个人资料切换身份、填档案，提交后进入认证队列
  goRegister() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  reload() {
    return this.runLoad(async () => {
      const res = await profile.listParties();
      this.setData({
        parties: res.data.map((party) => ({
          ...party,
          note: party.deal_count
            ? `成团 ${party.deal_count} 单${party.rating ? ` · ★${party.rating}（${party.review_count} 条评价）` : ''}`
            : '还没有成团记录',
        })),
      });
    });
  },
});
