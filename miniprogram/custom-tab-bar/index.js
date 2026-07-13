const { getMe } = require('../utils/me');

const TABS = [
  { icon: 'home', value: 'community', label: '小区' },
  { icon: 'chart-bar', value: 'insights', label: '数据' },
  { icon: 'user', value: 'my', label: '我的' },
];

Component({
  data: {
    value: '',
    // 红点：数据 tab = 有我没答的进行中征集；我的 tab = 我牵头/参与的有新动态
    list: TABS,
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
        .then((me) => {
          const dot = { dot: true };
          const badgeFor = {
            insights: me.has_unanswered_census ? dot : null,
            my: (me.has_mine_updates || me.has_joined_updates) ? dot : null,
          };
          this.setData({ list: TABS.map((tab) => ({ ...tab, badge: badgeFor[tab.value] || null })) });
        })
        .catch(() => {});
    },
  },

  methods: {
    handleChange(event) {
      wx.switchTab({ url: `/pages/${event.detail.value}/index` });
    },
  },
});
