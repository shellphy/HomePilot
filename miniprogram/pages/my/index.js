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
    partyPendingCount: 0,
    partyStatusNote: '',
    invitationCode: '',
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
      const [me, mineRes, joinedRes, todos] = await Promise.all([
        getMe(true),
        matters.listMine(),
        matters.listJoined(),
        getTodos(),
      ]);
      let pendingCount = 0;
      let partyPendingCount = 0;
      let invitationCode = '';
      if (me.is_admin) {
        [pendingCount, partyPendingCount, invitationCode] = await Promise.all([
          admin.listMatters(true).then((res) => res.pending_count),
          admin.listParties().then((res) => res.pending_count),
          admin.getInvitationCode().then((res) => res.data.code),
        ]);
      }

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
        invitationCode,
      });
    });
  },

  goProfile() {
    wx.navigateTo({ url: '/pages/profile-form/index' });
  },

  goPartyStatus() {
    const { party } = this.data.me;
    if (party.review_status === 'rejected') {
      wx.navigateTo({ url: '/pages/profile-form/index' });
      return;
    }
    wx.navigateTo({ url: `/pages/party/index?id=${party.id}` });
  },

  copyInvitationCode() {
    wx.setClipboardData({ data: this.data.invitationCode });
  },

  goCensus() {
    const censuses = this.data.me.censuses || [];
    if (!censuses.length) {
      wx.navigateTo({ url: '/pages/insights/index' });
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
