const { getMe } = require('../utils/me');

Component({
  data: {
    value: '',
    list: [
      { icon: 'home', value: 'community', label: '小区' },
      { icon: 'chart-bar', value: 'insights', label: '数据' },
      { icon: 'user', value: 'my', label: '我的' },
    ],
    // 「我的」tab 红点：我牵头的/我参与的有没看过的新动态（进对应列表即读）
    myBadge: null,
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

  pageLifetimes: {
    // 每次所在 tab 页展示时按缓存的 /me 刷新红点（未登录/请求失败时不打扰）
    show() {
      getMe()
        .then((me) => this.setData({
          myBadge: (me.has_mine_updates || me.has_joined_updates) ? { dot: true } : null,
        }))
        .catch(() => {});
    },
  },

  methods: {
    handleChange(event) {
      wx.switchTab({ url: `/pages/${event.detail.value}/index` });
    },
  },
});
