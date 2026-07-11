// 通用事项详情体（活动/互助/维权等接龙型事项）：
// 该类事项的全部行为（参与/流转/进度/编辑入口）都在组件内，变更后向页面发 refresh。
const matters = require('../../utils/api/matters');
const { pillClass, TYPE_META, stateOptions } = require('../../utils/constants');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
    joined: Boolean,
    isInitiator: Boolean,
  },

  data: {
    pillClass: '',
    meta: {},
    submitting: false,
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      this.setData({
        pillClass: pillClass(matter.state),
        meta: TYPE_META[matter.type] || { joinCta: '参与', joinedCta: '已参与（点击取消）', foot: '人已参与', roster: '参与名单' },
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
        wx.showToast({ title: res.joined ? '已加入，名单里见' : '已退出', icon: 'none' });
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
