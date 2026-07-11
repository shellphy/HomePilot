// AI 答疑面板：宿主页面用 page-container 半屏弹出，业主不离开正在看的事项。
// 会话与消息缓存在本地（服务端有完整历史，缓存只为回来能接着看）；
// AI 解释概念不代表承诺，涉及商家承诺的引导去向团长/商家提问。
const matters = require('../../utils/api/matters');

const CACHE_LIMIT = 40; // 本地只留最近几十条，够回看即可

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  data: {
    matterId: null,
    matterTitle: '',
    messages: [], // {role: 'user'|'ai', text}
    input: '',
    sending: false,
    remaining: null, // 今日剩余提问次数（后端下发）
    scrollTo: '',
  },

  methods: {
    // 宿主页面打开面板时调用；带 question 则自动填入并发送（快捷提问）
    open({ matterId, matterTitle, question }) {
      const cache = wx.getStorageSync(this.cacheKey(matterId)) || {};
      this.conversationId = cache.conversationId || null;
      this.setData({
        matterId,
        matterTitle: matterTitle || '',
        messages: cache.messages || [],
        input: question || '',
      });
      this.scrollToBottom();
      if (question) this.send();
    },

    cacheKey(matterId) {
      return `ai-chat:${matterId}`;
    },

    onInput(event) {
      this.setData({ input: event.detail.value });
    },

    async send() {
      const question = this.data.input.trim();
      if (!question || this.data.sending) return;

      const messages = [...this.data.messages, { role: 'user', text: question }];
      this.setData({ messages, input: '', sending: true });
      this.scrollToBottom();

      try {
        const res = await matters.aiChat(this.data.matterId, question, this.conversationId);
        this.conversationId = res.data.conversation_id;
        const next = [...this.data.messages, { role: 'ai', text: res.data.answer }];
        this.setData({ messages: next, remaining: res.data.remaining_today });
        wx.setStorageSync(this.cacheKey(this.data.matterId), {
          conversationId: this.conversationId,
          messages: next.slice(-CACHE_LIMIT),
        });
      } catch (error) {
        // 失败的提问不留在气泡里，放回输入框方便重发
        this.setData({ messages: this.data.messages.slice(0, -1), input: question });
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ sending: false });
        this.scrollToBottom();
      }
    },

    scrollToBottom() {
      // 锚点滚动比 scrollTop 可靠：内容高度由渲染决定，面板不必测量
      this.setData({ scrollTo: 'chat-bottom' });
    },

    // 换个话题：清掉本地会话，从头开始（服务端历史仍在）
    resetChat() {
      wx.showModal({
        title: '清空这里的对话？',
        confirmText: '清空',
        cancelText: '再想想',
        success: ({ confirm }) => {
          if (!confirm) return;
          this.conversationId = null;
          wx.removeStorageSync(this.cacheKey(this.data.matterId));
          this.setData({ messages: [] });
        },
      });
    },
  },
});
