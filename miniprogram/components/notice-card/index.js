// 公告卡片
Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  methods: {
    goDetail() {
      wx.navigateTo({ url: `/pages/matter/index?id=${this.data.matter.id}` });
    },
  },
});
