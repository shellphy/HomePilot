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
    footCount: 0,
    footNoun: '',
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      // 计数口径跟着阶段走：意向期看兴趣热度，接龙后看确认人数（与详情页一致）
      const confirmedPhase = matter.state === 'open' || matter.state === 'done';
      let footNoun = '感兴趣';
      if (confirmedPhase) {
        footNoun = '已确认参团';
      } else if (matter.state === 'aborted') {
        footNoun = '报名过';
      }
      this.setData({
        pillClass: pillClass(matter.state),
        percent: joinPercent(matter),
        footCount: confirmedPhase && matter.confirmed_count != null ? matter.confirmed_count : matter.join_count,
        footNoun,
      });
    },
  },

  methods: {
    goDetail() {
      wx.navigateTo({ url: `/pages/matter/index?id=${this.data.matter.id}` });
    },
  },
});
