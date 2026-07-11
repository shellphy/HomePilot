// 小区页：社区门户——概览、公告、正在张罗的事、数据入口。
// 社区名称、口号、张罗类型清单全部来自 /options，前端不写死。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { getMe } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    community: {},
    initiatableTypes: [],
    merchantUnlisted: false, // 未认证商家：入口保留，点击引导认证
    listedParties: 0,
    notices: [],
    doings: [],
    activeCount: 0,
    residents: 0,
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  // 社区名称只认 /options 下发（社区设置里改），没取到时用中性兜底，不写死具体小区
  onShareAppMessage() {
    const { community, activeCount } = this.data;
    return {
      title: `${community.name || '我们小区'} · ${activeCount} 件事正在张罗`,
      path: '/pages/community/index',
    };
  },

  onShareTimeline() {
    const { community } = this.data;
    return { title: `${community.name || '我们小区'} · ${community.slogan || ''}` };
  },

  reload() {
    return this.runLoad(async () => {
      const [res, stats, options, me] = await Promise.all([
        matters.listMatters(),
        profile.getStats(),
        profile.getOptions(),
        getMe(),
      ]);
      const notices = res.data.filter((matter) => matter.type === 'notice');
      const doings = res.data.filter((matter) => matter.type !== 'notice');
      const CLOSED = ['done', 'closed', 'resolved'];
      // 张罗入口按身份分流：业主看 user_initiatable，已认证商家看 merchant_initiatable，
      // 其余相关方（物业等）没有发起入口（他们的参与方式是官方回应）
      const isMerchant = !!(me.party && me.party.type === 'merchant');
      this.setData({
        community: options.community || {},
        initiatableTypes: (options.matter_types || []).filter((type) => (me.party
          ? isMerchant && me.party.is_listed && type.merchant_initiatable
          : type.user_initiatable)),
        merchantUnlisted: isMerchant && !me.party.is_listed,
        listedParties: stats.listed_parties,
        notices,
        doings,
        activeCount: doings.filter((matter) => !CLOSED.includes(matter.state)).length,
        residents: stats.residents,
      });
      if (options.community && options.community.name) {
        wx.setNavigationBarTitle({ title: options.community.name });
      }
    });
  },

  goInsights() {
    wx.switchTab({ url: '/pages/insights/index' });
  },

  goParties() {
    wx.navigateTo({ url: '/pages/parties/index' });
  },

  // 张罗点事：类型清单来自服务端，团购走专属表单，其余走通用表单
  goCreate() {
    if (this.data.merchantUnlisted) {
      // 给「联系管理员」一个具体落点：联系方式由社区设置下发
      const adminContact = this.data.community.admin_contact;
      wx.showModal({
        title: '先完成商家认证',
        content: '认证后就能以商家身份发起团购和活动，并带「已认证」标识。'
          + (adminContact ? `联系管理员认证：${adminContact}` : '请联系管理员认证。'),
        showCancel: false,
        confirmText: '好的',
      });
      return;
    }
    const types = this.data.initiatableTypes;
    wx.showActionSheet({
      itemList: types.map((type) => `发起${type.label}`),
      success: ({ tapIndex }) => {
        const picked = types[tapIndex];
        wx.navigateTo({
          url: picked.key === 'groupbuy'
            ? '/pages/groupbuy-form/index'
            : `/pages/matter-form/index?type=${picked.key}`,
        });
      },
    });
  },
});
