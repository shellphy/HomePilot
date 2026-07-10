// 团购卡片：类型自带展示逻辑与跳转，页面只负责喂 matter
const { pillClass, joinPercent } = require('../../utils/constants');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  data: {
    pillClass: '',
    percent: 0,
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      this.setData({
        pillClass: pillClass(matter.state),
        percent: joinPercent(matter),
      });
    },
  },

  methods: {
    goDetail() {
      wx.navigateTo({ url: `/pages/matter/index?id=${this.data.matter.id}` });
    },
  },
});
