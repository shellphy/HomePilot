// 「待我处理」列表：把聚合待办渲染成一张卡，点每项按类型跳到对应页。
// limit>0 时只展示前几项（首页 peek），其余引导去「我的」看全量。
const TARGET = {
  census_answer: (t) => `/pages/census-form/index?id=${t.matter_id}`,
  groupbuy_confirm: (t) => `/pages/matter/index?id=${t.matter_id}`,
  review: (t) => `/pages/matter/index?id=${t.matter_id}`,
  answer_question: (t) => `/pages/matter/index?id=${t.matter_id}`,
  progress: (t) => `/pages/matter/index?id=${t.matter_id}`,
  post_deal: (t) => `/pages/groupbuy-deal/index?id=${t.matter_id}`,
  admin_review: () => '/pages/admin/matters/index',
  admin_party: () => '/pages/admin/parties/index',
};

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    todos: { type: Array, value: [] },
    limit: { type: Number, value: 0 },
  },

  data: {
    shown: [],
    more: 0,
  },

  observers: {
    'todos, limit': function (todos, limit) {
      const list = todos || [];
      const shown = limit > 0 ? list.slice(0, limit) : list;
      this.setData({ shown, more: Math.max(0, list.length - shown.length) });
    },
  },

  methods: {
    open(event) {
      const { todo } = event.currentTarget.dataset;
      const to = TARGET[todo.type];
      if (to) wx.navigateTo({ url: to(todo) });
    },

    goAll() {
      wx.switchTab({ url: '/pages/my/index' });
    },
  },
});
