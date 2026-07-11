// 事项详情壳：负责取数与分享，渲染分发给类型详情组件
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { getMe } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    matter: null,
    joined: false,
    isInitiator: false,
    myReview: null,
    contacts: [],          // 牵头人视角：同意共享的参与者联系方式（互通阶段）
    initiatorContact: null, // 参与者视角：牵头人联系方式（互通阶段且自己同意过共享）
    canRespond: false,      // 被认证的治理类相关方成员：可发官方回应
    isParty: false,         // 相关方身份不参与接龙，隐藏报名按钮
    appName: '邻里',
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    if (this.data.id) this.reload();
  },

  onShareAppMessage() {
    const { matter, id, appName } = this.data;
    if (!matter) return { title: appName, path: '/pages/community/index' };
    if (matter.type === 'notice') {
      return { title: `${matter.title}｜${appName}`, path: `/pages/matter/index?id=${id}` };
    }
    return {
      title: `${matter.title}｜${matter.join_count} 位邻居已参与`,
      path: `/pages/matter/index?id=${id}`,
    };
  },

  onShareTimeline() {
    const { matter, appName } = this.data;
    return { title: matter ? `${matter.title}｜${appName}` : appName };
  },

  reload() {
    return this.runLoad(async () => {
      const [res, me, options] = await Promise.all([
        matters.getMatter(this.data.id),
        getMe(),
        profile.getOptions(),
      ]);
      if (options.community && options.community.app_name) {
        this.setData({ appName: options.community.app_name });
      }
      // 征集类事项的详情就是它的公示面（小区数据页对应期次）
      if (res.data.type === 'census') {
        wx.redirectTo({ url: `/pages/insights/index?id=${res.data.id}` });
        return;
      }
      const GOVERNANCE_TYPES = ['property', 'developer', 'committee'];
      this.setData({
        matter: res.data,
        joined: res.joined,
        isInitiator: res.data.initiator_id === me.id,
        myReview: res.my_review || null,
        contacts: res.contacts,
        initiatorContact: res.initiator_contact,
        canRespond: !!(me.party && me.party.is_listed && GOVERNANCE_TYPES.includes(me.party.type)),
        isParty: !!me.party,
      });
      wx.setNavigationBarTitle({ title: res.data.title });
    });
  },
});
