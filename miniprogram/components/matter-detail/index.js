// 通用事项详情体（活动/互助/维权等接龙型事项）：
// 该类事项的全部行为（参与/流转/进度/编辑入口）都在组件内，变更后向页面发 refresh。
const matters = require('../../utils/api/matters');
const { pillClass, TYPE_META, stateOptions, starsOf } = require('../../utils/constants');
const { guardProfileError } = require('../../utils/profile-guard');
const { requestSubscribe } = require('../../utils/subscribe');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
    joined: Boolean,
    isInitiator: Boolean,
    myReview: Object, // 我的评价（结束后可修改）
    canRespond: Boolean, // 被认证的治理类相关方成员：可发官方回应
    isParty: Boolean, // 相关方身份不参与接龙，参与区改为解释 + 切回业主入口
    partyLabel: String, // 当前相关方身份的显示名（解释文案用）
    contacts: Array, // 发起人视角：同意共享的参与者联系方式（互通阶段，如活动报名中）
    initiatorContact: Object, // 参与者视角：发起人联系方式（互通阶段且自己同意过共享）
    myShareContact: Boolean, // 我加入时的共享意愿：没共享的看不到发起人电话，给补开入口
  },

  data: {
    pillClass: '',
    meta: {},
    nextState: null, // 状态机的下一站（终态时为 null）
    nextIsFinal: false, // 下一站是否终态：终态不可回退，确认弹窗要说清后果
    submitting: false,
    reviews: [],
    reviewRating: 0,
    reviewContent: '',
    submittingReview: false,
    // 与发起人互通手机号的意愿：加入前用开关选择，默认同意（维权不互通，开关不出现）
    shareContact: true,
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      // 状态只能沿状态机推进一步（与后端守卫一致），算出下一站；终态时为 null（按钮隐藏）
      const states = stateOptions(matter.states);
      const stateIndex = states.findIndex((state) => state.value === matter.state);
      const nextState = stateIndex >= 0 ? states[stateIndex + 1] || null : null;
      this.setData(
        {
          pillClass: pillClass(matter.state),
          meta: TYPE_META[matter.type] || {
            joinCta: '参与',
            joinedCta: '取消参与',
            foot: '人已参与',
            roster: '参与名单',
          },
          nextState,
          nextIsFinal: !!nextState && stateIndex + 2 === states.length,
          reviews: (matter.reviews || []).map((review) => ({ ...review, stars: starsOf(review.rating) })),
        },
        () => this.measureDock(),
      );
    },
    myReview(myReview) {
      this.setData({
        reviewRating: (myReview && myReview.rating) || 0,
        reviewContent: (myReview && myReview.content) || '',
      });
    },
  },

  methods: {
    refresh() {
      this.triggerEvent('refresh');
    },

    // 量出吸底操作条的实际高度上报给页面，让页面按需精确预留底部空间：
    // 操作条高度随状态变（互通开关行 / 相关方说明），固定值要么遮内容要么留空白。
    measureDock() {
      this.createSelectorQuery()
        .select('.cta-dock')
        .boundingClientRect((rect) => {
          this.triggerEvent('dockmeasure', { height: rect ? Math.ceil(rect.height) : 0 });
        })
        .exec();
    },

    toggleJoin() {
      if (this.data.submitting) return;

      if (this.data.joined) {
        // 一键误触就退出太伤，取消前确认
        wx.showModal({
          title: '确定退出？',
          content: '你会从名单里移除，之后可以随时再加入。',
          confirmText: '退出',
          cancelText: '再想想',
          success: ({ confirm }) => {
            if (confirm) this.doToggle(true);
          },
        });
        return;
      }

      // 不互通联系方式的类型（如维权）一步加入；互通的类型加入前把共享后果说清
      const { matter, shareContact } = this.data;
      if (!matter.contacts_open) {
        this.doToggle(false);
        return;
      }
      wx.showModal({
        title: this.data.meta.joinCta,
        content: shareContact
          ? '你的手机号将与发起人互通（只在你们双方之间可见，不会公开展示），方便拉群、约时间、对接安排。'
          : '你选择了不互通手机号，之后拉群、对接需要你主动联系发起人。',
        confirmText: '确认加入',
        cancelText: '再想想',
        success: ({ confirm }) => {
          if (confirm) this.doToggle(false);
        },
      });
    },

    async doToggle(leaving) {
      this.setData({ submitting: true });
      try {
        // 加入的这一下顺手收一次订阅授权：之后状态流转/进展才有额度可推
        if (!leaving) await requestSubscribe();
        const res = leaving
          ? await matters.leave(this.data.matter.id)
          : await matters.join(this.data.matter.id, this.data.shareContact);
        wx.showToast({ title: res.joined ? '已加入，名单里见' : '已退出', icon: 'none' });
        this.refresh();
      } catch (error) {
        this.handleJoinError(error);
      } finally {
        this.setData({ submitting: false });
      }
    },

    onShareContactChange(event) {
      this.setData({ shareContact: event.detail.value });
    },

    // 联系电话点击给两个动作：拨打（对齐商家名录的一键拨号）或复制（建群粘贴用）
    onPhoneTap(event) {
      const { phone } = event.currentTarget.dataset;
      wx.showActionSheet({
        itemList: [`拨打 ${phone}`, '复制号码'],
        success: ({ tapIndex }) => {
          if (tapIndex === 0) {
            wx.makePhoneCall({ phoneNumber: phone });
          } else {
            wx.setClipboardData({ data: phone });
          }
        },
      });
    },

    // 没共享联系方式的参与者：补开共享，与发起人互见电话（双向对等）
    enableShare() {
      wx.showModal({
        title: '开启联系方式共享？',
        content: '开启后你和发起人可互见手机号（只在你们双方之间可见，不会公开展示），方便进群对接。',
        confirmText: '开启共享',
        cancelText: '再想想',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.join(this.data.matter.id, true);
            wx.showToast({ title: '已开启共享', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 业主没选楼栋号会被后端拦下（errors.profile）：引导去个人资料补全，回来即可加入
    handleJoinError(error) {
      if (!guardProfileError(error, '加入前请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
    },

    previewImage(event) {
      const { urls, current } = event.currentTarget.dataset;
      wx.previewImage({ urls, current });
    },

    // ---- 评价（仅参与过的业主，事项结束后）----

    onRateChange(event) {
      this.setData({ reviewRating: event.detail.value });
    },

    onReviewInput(event) {
      this.setData({ reviewContent: event.detail.value });
    },

    async submitReview() {
      const { reviewRating, reviewContent, submittingReview } = this.data;
      if (submittingReview) return;
      if (!reviewRating) return wx.showToast({ title: '请先打个分', icon: 'none' });

      this.setData({ submittingReview: true });
      try {
        await requestSubscribe();
        await matters.review(this.data.matter.id, reviewRating, reviewContent.trim());
        wx.showToast({ title: '评价已发布', icon: 'success' });
        this.refresh();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ submittingReview: false });
      }
    },

    // ---- 以下仅发起人可见的操作 ----

    goEdit() {
      wx.navigateTo({ url: `/pages/admin/matter-form/index?id=${this.data.matter.id}` });
    },

    goProgress() {
      wx.navigateTo({ url: `/pages/matter-update/index?id=${this.data.matter.id}` });
    },

    // 办不下去/不再推进的收场出口：不开放评价等事后环节，也不可再改
    abortMatter() {
      const { matter } = this.data;
      if (!matter.abort_state) return;

      wx.showModal({
        title: `按「${matter.abort_state.label}」收场？`,
        content: `确认后这件事按「${matter.abort_state.label}」收场：参与关闭、名单封存，不开放评价，且不能再改回来（弄错了需要联系管理员）。`,
        confirmText: '确认收场',
        cancelText: '再想想',
        confirmColor: '#e34d59',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.flipState(matter.id, matter.abort_state.value);
            wx.showToast({ title: '已收场', icon: 'none' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 状态只能推进到下一站（跳步/回退后端会拦）；进终态是不可逆动作，确认时把后果讲清楚
    flipState() {
      const { matter, nextState, nextIsFinal } = this.data;
      if (!nextState) return;

      wx.showModal({
        title: `进入「${nextState.label}」？`,
        content: nextIsFinal
          ? `「${nextState.label}」是最后一步，确认后不能再改回来，评价等事后环节将开启（弄错了需要联系管理员）。`
          : `这件事将从「${matter.state_label}」进入「${nextState.label}」，之后不能退回上一步。`,
        confirmText: '确认推进',
        cancelText: '再想想',
        // 不可逆的最后一步用红色确认，让分量在弹窗上就能感知
        ...(nextIsFinal ? { confirmColor: '#e34d59' } : {}),
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.flipState(matter.id, nextState.value);
            wx.showToast({ title: '状态已更新', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 相关方身份不参与接龙：去个人资料页切回业主身份
    goSwitchIdentity() {
      wx.navigateTo({ url: '/pages/profile-form/index' });
    },
  },
});
