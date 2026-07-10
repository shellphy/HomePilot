// 管理端 · 登记明细：含楼栋/房号/联系方式（仅管理员可见），点击展开答案
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    id: null,
    all: [],
    records: [],
    keyword: '',
    openId: null, // 展开答案的记录
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.listRecords(this.data.id);
      this.setData({ all: res.data });
      this.applyFilter();
    });
  },

  onSearch(event) {
    this.setData({ keyword: event.detail.value.trim() });
    this.applyFilter();
  },

  applyFilter() {
    const { all, keyword } = this.data;
    this.setData({
      records: keyword
        ? all.filter((record) => [record.nickname, record.unit_label, record.room_label, record.wechat_id, record.phone]
          .some((field) => field && field.includes(keyword)))
        : all,
    });
  },

  toggle(event) {
    const id = event.currentTarget.dataset.id;
    this.setData({ openId: this.data.openId === id ? null : id });
  },
});
