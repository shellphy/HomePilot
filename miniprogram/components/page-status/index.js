// 页面加载态壳：loaded 渲染内容槽，出错整块可点重试，否则显示骨架
Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    loaded: Boolean,
    error: String,
  },

  methods: {
    onRetry() {
      this.triggerEvent('retry');
    },
  },
});
