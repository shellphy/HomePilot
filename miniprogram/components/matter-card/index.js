// 通用事项卡片（活动/互助/维权等接龙型事项；团购/公告有专属卡片）
const { pillClass, TYPE_META } = require('../../utils/constants');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  data: {
    pillClass: '',
    foot: '人已参与',
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      const meta = TYPE_META[matter.type] || {};
      this.setData({
        pillClass: pillClass(matter.state),
        foot: meta.foot || '人已参与',
      });
    },
  },

  methods: {
    goDetail() {
      wx.navigateTo({ url: `/pages/matter/index?id=${this.data.matter.id}` });
    },
  },
});
