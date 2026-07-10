// 团购详情体：该类型的全部行为（报名/评价/流转/成交公示入口）都在组件内，
// 数据变更后向页面发 refresh 事件，由页面重新拉取。
const matters = require('../../utils/api/matters');
const { STATE_FLOW, pillClass, joinPercent } = require('../../utils/constants');

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
    myReview: Object,
  },

  data: {
    pillClass: '',
    percent: 0,
    reviews: [],
    reviewRating: 0,
    reviewContent: '',
    submitting: false,
    submittingReview: false,
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

    async toggleJoin() {
      if (this.data.submitting) return;
      this.setData({ submitting: true });
      try {
        const res = this.data.joined
          ? await matters.leave(this.data.matter.id)
          : await matters.join(this.data.matter.id);
        if (res.joined) {
          wx.showModal({
            title: '报名成功',
            content: '你已经在接龙名单里了。谈判结果和进度都会更新在本页，有进展记得回来看看。',
            showCancel: false,
            confirmText: '好的',
          });
        } else {
          wx.showToast({ title: '已取消报名', icon: 'none' });
        }
        this.refresh();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ submitting: false });
      }
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
      const current = this.data.matter.state;
      const options = STATE_FLOW.filter((state) => state.value !== current);

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
