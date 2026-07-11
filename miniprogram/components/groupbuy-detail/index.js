// 团购详情体：该类型的全部行为（报名/评价/流转/成交公示入口）都在组件内，
// 数据变更后向页面发 refresh 事件，由页面重新拉取。
const matters = require('../../utils/api/matters');
const { pillClass, joinPercent, stateOptions, starsOf } = require('../../utils/constants');
const { guardProfileError } = require('../../utils/profile-guard');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
    joined: Boolean,
    isInitiator: Boolean,
    myReview: Object,
    contacts: Array,          // 团长视角：同意共享的参团者联系方式（成团后）
    initiatorContact: Object, // 参团者视角：团长联系方式（成团后且自己同意过共享）
    isParty: Boolean,         // 相关方身份不参与接龙，隐藏报名按钮
  },

  data: {
    pillClass: '',
    percent: 0,
    reviews: [],
    reviewRating: 0,
    reviewContent: '',
    submitting: false,
    submittingReview: false,
    // 成团后与团长互通手机号的意愿：报名前用开关选择，默认同意
    shareContact: true,
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      this.setData({
        pillClass: pillClass(matter.state),
        percent: joinPercent(matter),
        reviews: (matter.reviews || []).map((review) => ({ ...review, stars: starsOf(review.rating) })),
      });
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

    toggleJoin() {
      if (this.data.submitting) return;

      if (this.data.joined) {
        // 一键误触就掉出名单太伤，取消前确认
        wx.showModal({
          title: '取消报名？',
          content: '你会从接龙名单里移除，之后可以随时再报。',
          confirmText: '取消报名',
          cancelText: '再想想',
          success: ({ confirm }) => {
            if (confirm) this.doLeave();
          },
        });
        return;
      }

      // 共享意愿在页面开关里选好，弹窗只做最终确认，点「再想想」不会报名
      const { matter, shareContact } = this.data;
      wx.showModal({
        title: matter.state === 'seeking' ? '登记意向' : '报名接龙',
        content: shareContact
          ? '成团后你的手机号将与团长互通（只在你和团长之间可见，不会公开展示），方便建群对接、安排上门。'
          : '你选择了不互通手机号，成团后建群、对接需要你主动联系团长。',
        confirmText: matter.state === 'seeking' ? '登记' : '报名',
        cancelText: '再想想',
        success: ({ confirm }) => {
          if (confirm) this.doJoin(shareContact);
        },
      });
    },

    onShareContactChange(event) {
      this.setData({ shareContact: event.detail.value });
    },

    async doJoin(shareContact) {
      this.setData({ submitting: true });
      try {
        await matters.join(this.data.matter.id, shareContact);
        wx.showModal({
          title: '报名成功',
          content: '你已经在接龙名单里了。谈判结果和进度都会更新在本页，有进展记得回来看看。',
          showCancel: false,
          confirmText: '好的',
        });
        this.refresh();
      } catch (error) {
        this.handleJoinError(error);
      } finally {
        this.setData({ submitting: false });
      }
    },

    async doLeave() {
      this.setData({ submitting: true });
      try {
        await matters.leave(this.data.matter.id);
        wx.showToast({ title: '已取消报名', icon: 'none' });
        this.refresh();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ submitting: false });
      }
    },

    // 业主没选楼栋号会被后端拦下（errors.profile）：引导去个人资料补全，回来即可报名
    handleJoinError(error) {
      if (!guardProfileError(error, '接龙名单以「楼栋 + 昵称」公示，报名前请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
    },

    copyPhone(event) {
      wx.setClipboardData({ data: event.currentTarget.dataset.phone });
    },

    previewImage(event) {
      const { urls, current } = event.currentTarget.dataset;
      wx.previewImage({ urls, current });
    },

    // ---- 评价（仅参团业主，成团后）----

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
      wx.navigateTo({ url: `/pages/groupbuy-form/index?id=${this.data.matter.id}` });
    },

    goProgress() {
      wx.navigateTo({ url: `/pages/matter-update/index?id=${this.data.matter.id}` });
    },

    goDeal() {
      wx.navigateTo({ url: `/pages/groupbuy-deal/index?id=${this.data.matter.id}` });
    },

    flipState() {
      const { matter } = this.data;
      const options = stateOptions(matter.states).filter((state) => state.value !== matter.state);

      wx.showActionSheet({
        itemList: options.map((state) => `流转为「${state.label}」`),
        success: async ({ tapIndex }) => {
          try {
            await matters.flipState(this.data.matter.id, options[tapIndex].value);
            wx.showToast({ title: '状态已更新', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },
  },
});
