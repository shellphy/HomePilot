// 我的：我家在小区里的档案（标准布局：头像区 + 分组单元格）
const matters = require('../../utils/api/matters');
const admin = require('../../utils/api/admin');
const { getMe } = require('../../utils/me');
const load = require('../../behaviors/load');
const { requestSubscribe, getSubscribeStatus } = require('../../utils/subscribe');

Page({
  behaviors: [load],

  data: {
    me: null,
    joinedCount: 0,
    mineCount: 0,
    censusCount: 0,
    pendingCount: 0,
    partyPendingCount: 0, // 待认证相关方（有成员但未认证）
    subscribeStatus: 'unknown',
  },

  onShow() {
    this.reload();
  },

  async onPullDownRefresh() {
    await this.reload();
    wx.stopPullDownRefresh();
  },

  reload() {
    return this.runLoad(async () => {
      // /me 强制刷新：未读红点（has_mine_updates 等）要反映最新动态，不能吃会话缓存
      const [me, mineRes, joinedRes, subscribeStatus] = await Promise.all([
        getMe(true),
        matters.listMine(),
        matters.listJoined(),
        getSubscribeStatus(),
      ]);
      const [pendingCount, partyPendingCount] = me.is_admin
        ? await Promise.all([
          admin.listMatters(true).then((res) => res.pending_count),
          admin.listParties().then((res) => res.pending_count),
        ])
        : [0, 0];

      // 身份行：业主 · 楼栋 · 房号（空项不显示）；相关方则是 类型 · 名称
      const identityLine = me.party
        ? [me.party.label, me.party.name].filter(Boolean).join(' · ')
        : ['业主', me.unit_label, me.room_label].filter(Boolean).join(' · ');

      this.setData({
        me,
        identityLine,
        censusCount: (me.censuses || []).length,
        mineCount: mineRes.data.length,
        joinedCount: joinedRes.data.length,
        pendingCount,
        partyPendingCount,
        subscribeStatus,
      });
    });
  },

  goProfile() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  // 我的答题：答过的直接落到个人登记与 AI 总结，多期弹选择；没答过就去数据 tab 逛逛
  goCensus() {
    const censuses = (this.data.me && this.data.me.censuses) || [];
    if (!censuses.length) {
      wx.switchTab({ url: '/pages/insights/index' });
      return;
    }
    if (censuses.length === 1) {
      wx.navigateTo({ url: `/pages/census-report/index?id=${censuses[0].matter_id}` });
      return;
    }
    wx.showActionSheet({
      itemList: censuses.map((census) => `${census.title}（已答 ${census.answered} 题）`),
      success: ({ tapIndex }) => {
        wx.navigateTo({ url: `/pages/census-report/index?id=${censuses[tapIndex].matter_id}` });
      },
    });
  },

  goAdminMatters() {
    wx.navigateTo({ url: '/pages/admin/matters/index' });
  },

  goAdminParties() {
    wx.navigateTo({ url: '/pages/admin/parties/index' });
  },

  goAdminSettings() {
    wx.navigateTo({ url: '/pages/admin/settings/index' });
  },

  goJoined() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=joined' });
  },

  goMine() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=mine' });
  },

  async retrySubscribe() {
    await requestSubscribe();
    this.setData({ subscribeStatus: await getSubscribeStatus() });
  },
});
