// 小区页：社区门户——概览、公告、正在张罗的事、数据入口。
// 社区名称、口号、张罗类型清单全部来自 /options，前端不写死。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    community: {},
    initiatableTypes: [],
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

  onShareAppMessage() {
    const { community, activeCount } = this.data;
    return {
      title: `${community.app_name || '天青府家园'} · ${activeCount} 件事正在张罗`,
      path: '/pages/community/index',
    };
  },

  onShareTimeline() {
    const { community } = this.data;
    return { title: `${community.app_name || '天青府家园'} · ${community.slogan || ''}` };
  },

  reload() {
    return this.runLoad(async () => {
      const [res, stats, options] = await Promise.all([
        matters.listMatters(),
        profile.getStats(),
        profile.getOptions(),
      ]);
      const notices = res.data.filter((matter) => matter.type === 'notice');
      const doings = res.data.filter((matter) => matter.type !== 'notice');
      const CLOSED = ['done', 'closed', 'resolved'];
      this.setData({
        community: options.community || {},
        initiatableTypes: (options.matter_types || []).filter((type) => type.user_initiatable),
        notices,
        doings,
        activeCount: doings.filter((matter) => !CLOSED.includes(matter.state)).length,
        residents: stats.residents,
      });
      if (options.community && options.community.app_name) {
        wx.setNavigationBarTitle({ title: options.community.app_name });
      }
    });
  },

  goInsights() {
    wx.navigateTo({ url: '/pages/insights/index' });
  },

  // 张罗点事：类型清单来自服务端，团购走专属表单，其余走通用表单
  goCreate() {
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
