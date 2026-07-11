// AI 快捷提问：预设问题点一下即自动提交给 AI（带本事项上下文），业主不用打字。
// 教育发生在疑问冒头的那一刻，入口长在决策现场而不是让人自己去找对话页。
Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matterId: Number,
    matterTitle: String,
    questions: Array,
  },

  methods: {
    ask(event) {
      this.go(event.currentTarget.dataset.q);
    },

    askBlank() {
      this.go('');
    },

    // 宿主页面持有半屏 AI 面板（page-container），这里只负责把问题递过去
    go(question) {
      const pages = getCurrentPages();
      const page = pages[pages.length - 1];
      if (page && page.openAiChat) {
        page.openAiChat({
          matterId: this.data.matterId,
          matterTitle: this.data.matterTitle,
          question,
        });
      }
    },
  },
});
