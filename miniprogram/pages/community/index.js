// 小区页：社区门户——概览、公告、正在张罗的事、数据入口。
// 社区名称、口号、张罗类型清单全部来自 /options，前端不写死。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { getMe } = require('../../utils/me');
const load = require('../../behaviors/load');

// 各类型的收尾态：收尾的事不再占整卡，压成单行沉底
const CLOSED = ['done', 'closed', 'resolved'];

Page({
  behaviors: [load],

  data: {
    community: {},
    initiatableTypes: [],
    merchantUnlisted: false, // 未认证商家：入口保留，点击引导认证
    listedParties: 0,
    notices: [],
    doings: [], // 进行中的事：整卡
    finished: [], // 已收尾的事：单行 {id, type, title, note}
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
      title: `${community.name || '小区'} · ${activeCount} 件事正在张罗`,
      path: '/pages/community/index',
    };
  },

  onShareTimeline() {
    const { community } = this.data;
    return { title: `${community.name || '小区'} · ${community.slogan || ''}` };
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
      // 征集(census)不进小区信息流：它整条归「数据」tab（进行中+往期+聚合都在那），此处不重复陈列
      const all = res.data.filter((matter) => matter.type !== 'notice' && matter.type !== 'census');
      const doings = all.filter((matter) => !CLOSED.includes(matter.state));
      const finished = all.filter((matter) => CLOSED.includes(matter.state)).map((matter) => ({
        id: matter.id,
        title: matter.title,
        note: `${matter.join_count} 人 · ${matter.state_label}`,
      }));
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
        finished,
        activeCount: doings.length,
        residents: stats.residents,
      });
    });
  },

  // 收尾行点进事项详情（征集已归「数据」tab，不在此列）
  goFinished(e) {
    wx.navigateTo({ url: `/pages/matter/index?id=${e.currentTarget.dataset.id}` });
  },

  goParties() {
    wx.navigateTo({ url: '/pages/parties/index' });
  },

  // 张罗点事：类型清单来自服务端，所有类型走统一创作表单（带 type 参数）
  goCreate() {
    if (this.data.merchantUnlisted) {
      // 给「联系管理员」一个具体落点：联系方式由社区设置下发
      const adminContact = this.data.community.admin_contact;
      const contactTip = adminContact ? `联系管理员认证：${adminContact}` : '请联系管理员认证。';
      wx.showModal({
        title: '先完成商家认证',
        content: `认证后就能以商家身份发起团购和活动，并带「已认证」标识。${contactTip}`,
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
        wx.navigateTo({ url: `/pages/admin/matter-form/index?type=${picked.key}` });
      },
    });
  },
});
