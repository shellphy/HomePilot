// 通用事项详情体（活动/互助/维权等接龙型事项）：
// 该类事项的全部行为（参与/流转/进度/编辑入口）都在组件内，变更后向页面发 refresh。
const matters = require('../../utils/api/matters');
const { pillClass, TYPE_META, stateOptions } = require('../../utils/constants');
const { guardProfileError } = require('../../utils/profile-guard');

function starsOf(rating) {
  return '★★★★★'.slice(0, rating) + '☆☆☆☆☆'.slice(0, 5 - rating);
}

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
    joined: Boolean,
    isInitiator: Boolean,
    myReview: Object,    // 我的评价（结束后可修改）
    canRespond: Boolean, // 被认证的治理类相关方成员：可发官方回应
    isParty: Boolean,    // 相关方身份不参与接龙，隐藏参与按钮
  },

  data: {
    pillClass: '',
    meta: {},
    submitting: false,
    reviews: [],
    reviewRating: 0,
    reviewContent: '',
    submittingReview: false,
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      this.setData({
        pillClass: pillClass(matter.state),
        meta: TYPE_META[matter.type] || { joinCta: '参与', joinedCta: '已参与（点击取消）', foot: '人已参与', roster: '参与名单' },
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
      this.doToggle(false);
    },

    async doToggle(leaving) {
      this.setData({ submitting: true });
      try {
        const res = leaving
          ? await matters.leave(this.data.matter.id)
          : await matters.join(this.data.matter.id);
        wx.showToast({ title: res.joined ? '已加入，名单里见' : '已退出', icon: 'none' });
        this.refresh();
      } catch (error) {
        this.handleJoinError(error);
      } finally {
        this.setData({ submitting: false });
      }
    },

    // 业主没选楼栋号会被后端拦下（errors.profile）：引导去个人资料补全，回来即可加入
    handleJoinError(error) {
      if (!guardProfileError(error, '名单以「楼栋 + 昵称」记录，加入前请先在个人资料里选好楼栋号。')) {
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
      wx.navigateTo({ url: `/pages/matter-form/index?id=${this.data.matter.id}` });
    },

    goProgress() {
      wx.navigateTo({ url: `/pages/matter-update/index?id=${this.data.matter.id}` });
    },

    flipState() {
      const { matter } = this.data;
      const options = stateOptions(matter.states).filter((state) => state.value !== matter.state);

      wx.showActionSheet({
        itemList: options.map((state) => `流转为「${state.label}」`),
        success: async ({ tapIndex }) => {
          try {
            await matters.flipState(matter.id, options[tapIndex].value);
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
