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
    contacts: [], // 牵头人视角：同意共享的参与者联系方式（互通阶段）
    initiatorContact: null, // 参与者视角：牵头人联系方式（互通阶段且自己同意过共享）
    canRespond: false, // 被认证的治理类相关方成员：可发官方回应
    isParty: false, // 相关方身份不参与接龙，改为解释 + 切回业主入口
    partyLabel: '', // 当前相关方身份的显示名（解释文案用）
    myShareContact: false, // 我报名时的联系方式共享意愿（成团后补开共享的入口据此显示）
    myJoinStage: '', // 我的承诺档位（团购分意向/确认）：接龙中的意向登记者看到「确认参团」入口
    communityName: '小区', // 兜底文案，实际名称由 /options 下发
    aiChatShow: false, // AI 答疑半屏面板（ai-quick-ask / 术语弹层通过页面方法呼出）
    dockReserve: 0, // 吸底操作条实测高度（px），据此精确预留底部空间，见 onDockMeasure
  },

  // 详情组件量出吸底操作条实际高度后上报：按需精确预留，避免遮挡或大片空白
  onDockMeasure(e) {
    const height = (e.detail && e.detail.height) || 0;
    if (height !== this.data.dockReserve) this.setData({ dockReserve: height });
  },

  // 子组件（快捷提问/术语弹层）经 getCurrentPages 调到这里：半屏弹出 AI 面板
  openAiChat(options) {
    this.setData({ aiChatShow: true });
    this.selectComponent('#aiChat').open(options);
  },

  onAiChatLeave() {
    if (!this.data.aiChatShow) return;
    // 关闭面板顺手中断在途的流式回答，别让它在后台继续跑
    const panel = this.selectComponent('#aiChat');
    if (panel) panel.close();
    this.setData({ aiChatShow: false });
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
  },

  onShow() {
    if (this.data.id) this.reload();
  },

  onShareAppMessage() {
    const { matter, id, communityName } = this.data;
    if (!matter) return { title: communityName, path: '/pages/community/index' };
    if (matter.type === 'notice') {
      return { title: `${matter.title}｜${communityName}`, path: `/pages/matter/index?id=${id}` };
    }
    return {
      title: `${matter.title}｜${matter.join_count} 位邻居已参与`,
      path: `/pages/matter/index?id=${id}`,
    };
  },

  onShareTimeline() {
    const { matter, communityName } = this.data;
    return { title: matter ? `${matter.title}｜${communityName}` : communityName };
  },

  reload() {
    return this.runLoad(async () => {
      const [res, me, options] = await Promise.all([matters.getMatter(this.data.id), getMe(), profile.getOptions()]);
      if (options.community && options.community.name) {
        this.setData({ communityName: options.community.name });
      }
      // 征集类事项的详情就是它的公示面（小区数据页对应期次）
      if (res.data.type === 'census') {
        wx.redirectTo({ url: `/pages/census-insights/index?id=${res.data.id}` });
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
        partyLabel: me.party ? me.party.label : '',
        myShareContact: !!res.my_share_contact,
        myJoinStage: res.my_join_stage || '',
      });
      wx.setNavigationBarTitle({ title: res.data.title });
    });
  },
});
