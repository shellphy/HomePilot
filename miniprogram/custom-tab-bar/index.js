Component({
  data: {
    value: '',
    list: [
      { icon: 'shop', value: 'groupbuys', label: '团购' },
      { icon: 'chart-bar', value: 'map', label: '小区意向' },
      { icon: 'user', value: 'my', label: '我的' },
    ],
  },

  lifetimes: {
    ready() {
      const pages = getCurrentPages();
      const current = pages[pages.length - 1];
      const matched = current && /pages\/(\w+)\/index/.exec(current.route);
      if (matched) {
        this.setData({ value: matched[1] });
      }
    },
  },

  methods: {
    handleChange(event) {
      wx.switchTab({ url: `/pages/${event.detail.value}/index` });
    },
  },
});
