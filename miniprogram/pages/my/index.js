// 我的：我家在小区里的档案（标准布局：头像区 + 分组单元格）
const matters = require('../../utils/api/matters');
const admin = require('../../utils/api/admin');
const { getMe, getTodos } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    me: null,
    todos: [],
    joinedCount: 0,
    mineCount: 0,
    censusCount: 0,
    pendingCount: 0,
    partyPendingCount: 0, // 待核验相关方（有成员但未核验）
    partyStatusNote: '', // 我的相关方核验状态（审核中/已核验/未通过）
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
      const [me, mineRes, joinedRes, todos] = await Promise.all([
        getMe(true),
        matters.listMine(),
        matters.listJoined(),
        getTodos(),
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

      const partyStatusNote = me.party
        ? {
          pending: '审核中',
          approved: '身份已核验',
          rejected: '未通过，点此改资料重交',
        }[me.party.review_status]
        : '';

      this.setData({
        me,
        todos,
        identityLine,
        partyStatusNote,
        censusCount: (me.censuses || []).length,
        mineCount: mineRes.data.length,
        joinedCount: joinedRes.data.length,
        pendingCount,
        partyPendingCount,
      });
    });
  },

  goProfile() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  // 核验状态入口：未通过直接去改资料重交，其余去档案详情看公示情况
  goPartyStatus() {
    const party = this.data.me && this.data.me.party;
    if (!party) return;
    if (party.review_status === 'rejected') {
      wx.navigateTo({ url: '/pages/profile-form/index' });
      return;
    }
    wx.navigateTo({ url: `/pages/party/index?id=${party.id}` });
  },

  // 我的问卷：答过一份直接落到个人问卷，多份进列表页选；没答过就去数据 tab 逛逛
  goCensus() {
    const censuses = (this.data.me && this.data.me.censuses) || [];
    if (!censuses.length) {
      wx.switchTab({ url: '/pages/insights/index' });
      return;
    }
    if (censuses.length === 1) {
      wx.navigateTo({ url: `/pages/census-answers/index?id=${censuses[0].matter_id}` });
      return;
    }
    wx.navigateTo({ url: '/pages/my-censuses/index' });
  },

  goAdminMatters() {
    wx.navigateTo({ url: '/pages/admin/matters/index' });
  },

  goAdminParties() {
    wx.navigateTo({ url: '/pages/admin/parties/index' });
  },

  goAdminUsers() {
    wx.navigateTo({ url: '/pages/admin/admins/index' });
  },

  goAdminSettings() {
    wx.navigateTo({ url: '/pages/admin/settings/index' });
  },

  goAdminBlocks() {
    wx.navigateTo({ url: '/pages/admin/blocks/index' });
  },

  goJoined() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=joined' });
  },

  goMine() {
    wx.navigateTo({ url: '/pages/mine-matters/index?kind=mine' });
  },
});
