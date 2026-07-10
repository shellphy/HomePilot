// 征集卡片：摸底类事务（装修意向摸底等），点击进入其公示面（小区数据页）
Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  methods: {
    goInsights() {
      wx.navigateTo({ url: `/pages/insights/index?id=${this.data.matter.id}` });
    },
  },
});
