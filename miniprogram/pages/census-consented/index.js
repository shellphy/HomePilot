// 发起者视图 · 邻居授权登记：只列出主动勾选「让发起者看到我的登记」的邻居，
// 含显示名、手机号（限收联系方式的征集）、逐题回答。授权由后端收窄到发起者本人/管理员。
const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    registrations: [],
    openId: null, // 展开答案的登记
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getCensusConsented(this.data.id);
      this.setData({ registrations: res.data });
    });
  },

  toggle(event) {
    const { id } = event.currentTarget.dataset;
    this.setData({ openId: this.data.openId === id ? null : id });
  },
});
