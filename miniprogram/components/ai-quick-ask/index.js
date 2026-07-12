// AI 答疑入口：决策现场只留一个醒目的 AI 图标，不把预设问题铺在页面上占地方。
// 点开半屏面板后，预设问题以「猜你想问」出现在输入框上方，让业主自己挑或直接打字。
Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matterId: Number,
    matterTitle: String,
    questions: Array, // 透传给面板做「猜你想问」，入口本身不展示
  },

  methods: {
    // 宿主页面持有半屏 AI 面板（page-container），这里只负责把上下文与预设问题递过去
    open() {
      const pages = getCurrentPages();
      const page = pages[pages.length - 1];
      if (page && page.openAiChat) {
        page.openAiChat({
          matterId: this.data.matterId,
          matterTitle: this.data.matterTitle,
          questions: this.data.questions,
        });
      }
    },
  },
});
