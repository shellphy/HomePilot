// AI 答疑面板：宿主页面用 page-container 半屏弹出，业主不离开正在看的事项。
// 回答走 SSE 流式下发，打字机式逐字显示；等待/生成时发送键变停止键，
// 点停止即中断请求并冻结打字机，已显示的文字保留。
// 会话与消息缓存在本地（服务端有完整历史，缓存只为回来能接着看）；
// AI 解释概念不代表承诺，涉及商家承诺的引导去向团长/商家提问。
const matters = require('../../utils/api/matters');
const { mdToHtml } = require('../../utils/markdown');

const CACHE_LIMIT = 40; // 本地只留最近几十条，够回看即可
const TYPE_INTERVAL = 24; // 打字机每帧间隔（ms）

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  data: {
    matterId: null,
    matterTitle: '',
    messages: [], // {role: 'user'|'ai', text}
    presets: [], // 「猜你想问」预设问题，仅空对话时展示在输入框上方
    input: '',
    busy: false, // 一轮问答进行中（请求在途或打字机未追完）：发送键变停止键
    streamingIndex: -1, // 正在流式输出的 AI 气泡下标（-1 表示无）
    remaining: null, // 今日剩余提问次数（后端下发）
    scrollTo: '',
  },

  detached() {
    this.haltStream();
  },

  methods: {
    // 宿主页面打开面板时调用；带 question 则自动填入并发送（如术语追问），
    // 带 questions 则作为「猜你想问」在空对话时展示，让业主自己挑
    open({ matterId, matterTitle, question, questions }) {
      this.haltStream();
      const cache = wx.getStorageSync(this.cacheKey(matterId)) || {};
      this.conversationId = cache.conversationId || null;
      this.setData({
        matterId,
        matterTitle: matterTitle || '',
        messages: this.withRenderedNodes(cache.messages || []),
        presets: questions || [],
        input: question || '',
        busy: false,
        streamingIndex: -1,
      });
      this.scrollToBottom();
      if (question) this.send();
    },

    // 宿主关闭面板时调用：中断在途请求，别让它在后台继续跑
    close() {
      this.haltStream();
    },

    // 点「猜你想问」里的一条：直接发出去，等同于自己打字提问
    pickPreset(event) {
      this.setData({ input: event.currentTarget.dataset.q });
      this.send();
    },

    cacheKey(matterId) {
      return `ai-chat:${matterId}`;
    },

    // 给 AI 气泡预渲染 markdown 节点（rich-text 用）；用户气泡保持纯文本
    withRenderedNodes(messages) {
      return messages.map((message) =>
        message.role === 'ai' ? { ...message, nodes: mdToHtml(message.text) } : message,
      );
    },

    onInput(event) {
      this.setData({ input: event.detail.value });
    },

    // 发送键在忙时充当停止键
    onSendTap() {
      if (this.data.busy) {
        this.stop();
      } else {
        this.send();
      }
    },

    send() {
      const question = this.data.input.trim();
      if (!question || this.data.busy) return;

      // 追加提问气泡与一个待填充的 AI 气泡（先空着，收到首个 delta 前显示「正在想」）
      const messages = [...this.data.messages, { role: 'user', text: question }, { role: 'ai', text: '', nodes: '' }];
      const streamingIndex = messages.length - 1;
      this.streamingIndex = streamingIndex;
      this.fullText = '';
      this.shownLen = 0;
      this.receiving = true;
      this.roundDone = false;
      this.setData({ messages, input: '', busy: true, streamingIndex });
      this.scrollToBottom();
      this.startTyping();

      this.stream = matters.aiChatStream(this.data.matterId, question, this.conversationId, {
        onDelta: (delta) => {
          this.fullText += delta;
          this.startTyping();
        },
      });

      this.stream.promise
        .then(({ conversationId, remaining, aborted }) => {
          if (aborted) return; // 停止/关闭已在别处收尾
          if (conversationId) this.conversationId = conversationId;
          if (remaining !== null && remaining !== undefined) {
            this.setData({ remaining });
          }
          this.receiving = false; // 让打字机把剩余文字追完后收尾
          this.startTyping();
        })
        .catch((error) => {
          this.failRound(error);
        });
    },

    // 停止：中断请求并冻结打字机，保留已经显示出来的文字
    stop() {
      if (!this.data.busy) return;
      if (this.stream) this.stream.abort();
      this.finishRound();
    },

    // 打字机：把已收到的 fullText 按帧逐字显示到当前 AI 气泡
    startTyping() {
      if (this.typeTimer) return;
      this.typeTimer = setInterval(() => this.typeTick(), TYPE_INTERVAL);
    },

    stopTyping() {
      if (this.typeTimer) {
        clearInterval(this.typeTimer);
        this.typeTimer = null;
      }
    },

    typeTick() {
      const backlog = this.fullText.length - this.shownLen;
      if (backlog > 0) {
        // 落后越多每帧显示越多，避免网络突发一大段时打字机拖太久
        const step = Math.max(2, Math.ceil(backlog / 16));
        this.shownLen = Math.min(this.fullText.length, this.shownLen + step);
        const shown = this.fullText.slice(0, this.shownLen);
        this.setData({
          [`messages[${this.streamingIndex}].text`]: shown,
          [`messages[${this.streamingIndex}].nodes`]: mdToHtml(shown, true),
        });
        this.scrollToBottom();
      } else if (!this.receiving) {
        // 文字追完且网络已结束：正常收尾
        this.finishRound();
      }
    },

    // 一轮正常结束或被停止：停打字机、去掉空气泡、落缓存
    finishRound() {
      if (this.roundDone) return;
      this.roundDone = true;
      this.stopTyping();
      this.receiving = false;

      let { messages } = this.data;
      const idx = this.streamingIndex;
      // 停止时若 AI 一个字都没吐出来，去掉那个空气泡（提问保留在会话里）
      if (messages[idx] && messages[idx].role === 'ai' && !messages[idx].text) {
        messages = messages.slice(0, idx);
      } else if (messages[idx] && messages[idx].role === 'ai') {
        messages[idx] = { ...messages[idx], nodes: mdToHtml(messages[idx].text) };
      }
      this.streamingIndex = -1;
      this.setData({ messages, busy: false, streamingIndex: -1 });
      this.persist(messages);
      this.scrollToBottom();
    },

    // 请求失败：去掉空 AI 气泡；若一个字都没答出来，把提问退回输入框方便重发
    failRound(error) {
      if (this.roundDone) return;
      this.roundDone = true;
      this.stopTyping();
      this.receiving = false;

      const idx = this.streamingIndex;
      let { messages } = this.data;
      const hadText = !!(messages[idx] && messages[idx].text);
      messages = messages.slice(0, idx); // 去掉 AI 气泡
      const patch = { busy: false, streamingIndex: -1 };
      if (!hadText) {
        const asked = messages[messages.length - 1];
        messages = messages.slice(0, -1); // 连同提问一起撤回
        patch.input = asked ? asked.text : '';
      }
      this.streamingIndex = -1;
      this.setData({ messages, ...patch });
      wx.showToast({ title: error.message, icon: 'none' });
    },

    // 中断并收尾（关闭面板/清空对话/组件销毁时用）
    haltStream() {
      if (this.stream) this.stream.abort();
      this.stopTyping();
      if (this.data.busy) this.finishRound();
    },

    persist(messages) {
      wx.setStorageSync(this.cacheKey(this.data.matterId), {
        conversationId: this.conversationId,
        // 只存 role/text，nodes 是从 text 渲染出来的派生值，回来时重新渲染即可
        messages: messages.slice(-CACHE_LIMIT).map(({ role, text }) => ({ role, text })),
      });
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
          this.haltStream();
          this.conversationId = null;
          wx.removeStorageSync(this.cacheKey(this.data.matterId));
          this.setData({ messages: [], streamingIndex: -1 });
        },
      });
    },
  },
});
