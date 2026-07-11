// 页面加载模式：首次进入走 loaded/loadError（配合 page-status 组件的骨架与重试），
// 之后的 onShow 静默刷新，失败只 toast 不打断浏览。
module.exports = Behavior({
  data: {
    loaded: false,
    loadError: '',
  },

  methods: {
    async runLoad(loader) {
      try {
        await loader();
        this.setData({ loaded: true, loadError: '' });
      } catch (error) {
        const message = error.message || '加载失败，请稍后重试';
        if (this.data.loaded) {
          wx.showToast({ title: message, icon: 'none' });
        } else {
          this.setData({ loadError: message });
        }
      }
    },
  },
});
