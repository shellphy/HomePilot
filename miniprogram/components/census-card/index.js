// 征集卡片：摸底类事项（装修意向摸底等），点击进入其公示面（征集详情页）
const { pillClass } = require('../../utils/constants');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  data: {
    pillClass: '',
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      this.setData({ pillClass: pillClass(matter.state) });
    },
  },

  methods: {
    goInsights() {
      wx.navigateTo({ url: `/pages/census-insights/index?id=${this.data.matter.id}` });
    },
  },
});
