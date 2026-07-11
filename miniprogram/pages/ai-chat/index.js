// 业主侧 AI 答疑：带着事项上下文的多轮对话。
// 会话与消息缓存在本地（服务端有完整历史，页面只为回来能接着看）；
// AI 解释概念不代表承诺，涉及商家承诺的引导去向团长/商家提问。
const matters = require('../../utils/api/matters');

const CACHE_LIMIT = 40; // 本地只留最近几十条，够回看即可

Page({
  data: {
    matterId: null,
    matterTitle: '',
    messages: [], // {role: 'user'|'ai', text}
    input: '',
    sending: false,
    remaining: null, // 今日剩余提问次数（后端下发）
    scrollTo: '',
  },

  onLoad(query) {
    const matterId = Number(query.id);
    const matterTitle = decodeURIComponent(query.title || '');
    const cache = wx.getStorageSync(this.cacheKey(matterId)) || {};
    this.conversationId = cache.conversationId || null;
    this.setData({
      matterId,
      matterTitle,
      messages: cache.messages || [],
    });
    if (matterTitle) wx.setNavigationBarTitle({ title: `AI · ${matterTitle}` });
    this.scrollToBottom();

    // 快捷提问入口带来的问题：填入即发，业主不用打一个字
    if (query.q) {
      this.setData({ input: decodeURIComponent(query.q) });
      this.send();
    }
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
    // 锚点滚动比 scrollTop 可靠：内容高度由渲染决定，页面不必测量
    this.setData({ scrollTo: 'chat-bottom' });
  },

  // 换个话题：清掉本地会话，从头开始（服务端历史仍在）
  resetChat() {
    wx.showModal({
      title: '重新开始对话？',
      content: '会清空本页的聊天记录，从头开始问。',
      confirmText: '重新开始',
      cancelText: '再想想',
      success: ({ confirm }) => {
        if (!confirm) return;
        this.conversationId = null;
        wx.removeStorageSync(this.cacheKey(this.data.matterId));
        this.setData({ messages: [] });
      },
    });
  },
});
