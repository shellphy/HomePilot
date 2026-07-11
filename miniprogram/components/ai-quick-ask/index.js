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

    go(question) {
      const query = `id=${this.data.matterId}&title=${encodeURIComponent(this.data.matterTitle || '')}`
        + (question ? `&q=${encodeURIComponent(question)}` : '');
      wx.navigateTo({ url: `/pages/ai-chat/index?${query}` });
    },
  },
});
