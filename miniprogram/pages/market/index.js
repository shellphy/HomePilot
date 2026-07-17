// 市集页：交易类事项——团购 + 二手闲置。就近发布：发起入口在本页。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { getMe } = require('../../utils/me');
const { MARKET_TYPES } = require('../../utils/constants');
const load = require('../../behaviors/load');

// 收尾态：已成交/已下架/流团压成单行沉底
const CLOSED = ['done', 'closed', 'resolved', 'aborted'];

Page({
  behaviors: [load],

  data: {
    community: {},
    initiatableTypes: [],
    merchantUnlisted: false, // 未核验商家：入口保留，点击引导核验
    doings: [], // 在售 / 团购进行中
    feedTypes: [], // 在售里出现过的类型 {key, label}，≥2 种才露出筛选
    typeFilter: '', // '' 全部 / 其余对应 matter.type
    visibleDoings: [], // doings 按 typeFilter 过滤后的结果
    finished: [], // 已收场的：单行 {id, title, note}
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  onShareAppMessage() {
    const { community } = this.data;
    return {
      title: `${community.name || '小区'} · 邻里市集`,
      path: '/pages/market/index',
    };
  },

  onShareTimeline() {
    const { community } = this.data;
    return { title: `${community.name || '小区'} · 邻里市集` };
  },

  reload() {
    return this.runLoad(async () => {
      const [res, options, me] = await Promise.all([
        matters.listMatters(),
        profile.getOptions(),
        getMe(),
      ]);
      const all = res.data.filter((matter) => MARKET_TYPES.includes(matter.type));
      const doings = all.filter((matter) => !CLOSED.includes(matter.state));
      // 在售里出现过的类型，按首次出现顺序去重，供顶部筛选
      const feedTypes = [];
      doings.forEach((matter) => {
        if (!feedTypes.some((type) => type.key === matter.type)) {
          feedTypes.push({ key: matter.type, label: matter.type_label });
        }
      });
      const finished = all.filter((matter) => CLOSED.includes(matter.state)).map((matter) => ({
        id: matter.id,
        title: matter.title,
        note: `${matter.join_count} 人 · ${matter.state_label}`,
      }));
      const isMerchant = !!(me.party && me.party.type === 'merchant');
      // 选中的类型可能已收场（从在售列表消失），回落到全部
      const typeFilter = feedTypes.some((type) => type.key === this.data.typeFilter) ? this.data.typeFilter : '';
      this.setData({
        community: options.community || {},
        // 就近发布：市集只发起团购/二手，身份分流与张罗页一致
        initiatableTypes: (options.matter_types || []).filter((type) => {
          if (!MARKET_TYPES.includes(type.key)) {
            return false;
          }
          if (me.is_admin) {
            return true;
          }
          if (me.party) {
            return isMerchant && me.party.is_listed && type.merchant_initiatable;
          }
          return type.user_initiatable;
        }),
        merchantUnlisted: isMerchant && !me.party.is_listed,
        doings,
        feedTypes,
        typeFilter,
        visibleDoings: this.filterDoings(doings, typeFilter),
        finished,
      });
    });
  },

  filterDoings(doings, typeFilter) {
    return typeFilter ? doings.filter((matter) => matter.type === typeFilter) : doings;
  },

  pickType(event) {
    const typeFilter = event.currentTarget.dataset.type;
    this.setData({ typeFilter, visibleDoings: this.filterDoings(this.data.doings, typeFilter) });
  },

  // 收场行点进事项详情
  goFinished(e) {
    wx.navigateTo({ url: `/pages/matter/index?id=${e.currentTarget.dataset.id}` });
  },

  // 就近发布：类型清单来自服务端，所有类型走统一创作表单（带 type 参数）
  goCreate() {
    if (this.data.merchantUnlisted) {
      const adminContact = this.data.community.admin_contact;
      const contactTip = adminContact ? `联系管理员核验：${adminContact}` : '请联系管理员核验。';
      wx.showModal({
        title: '先完成商家核验',
        content: `核验后就能以商家身份发起团购，并带「身份已核验」标识。${contactTip}`,
        showCancel: false,
        confirmText: '好的',
      });
      return;
    }
    const types = this.data.initiatableTypes;
    wx.showActionSheet({
      itemList: types.map((type) => `发布${type.label}`),
      success: ({ tapIndex }) => {
        const picked = types[tapIndex];
        wx.navigateTo({ url: `/pages/matter-form/index?type=${picked.key}` });
      },
    });
  },
});
