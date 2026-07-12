// 「大家都在问」：针对事项的公开问答面板。
// 不是评论区——只有业主提问和负责方（团长/商家/管理员）回答，业主间不互相回复
//（闲聊留在微信群）；同问聚合热度，好答案可沉淀成「买前必懂」词条。
const matters = require('../../utils/api/matters');
const { guardProfileError } = require('../../utils/profile-guard');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
  },

  data: {
    questions: [],
    canAsk: false,
    canAnswer: false,
    canPromote: false,
    loaded: false,
    // 提问/回答共用的底部输入弹层
    editorVisible: false,
    editorMode: 'ask', // ask | answer
    editorId: null,
    editorText: '',
    editorSubmitting: false,
  },

  observers: {
    'matter.id': function (id) {
      if (id) this.fetchQuestions();
    },
  },

  methods: {
    async fetchQuestions() {
      try {
        const res = await matters.getQuestions(this.data.matter.id);
        this.setData({
          questions: res.data,
          canAsk: res.can_ask,
          canAnswer: res.can_answer,
          canPromote: res.can_promote,
          loaded: true,
        });
      } catch (error) {
        // 问答区加载失败不阻塞详情页主体，静默降级
        this.setData({ loaded: true });
      }
    },

    ask() {
      this.setData({ editorVisible: true, editorMode: 'ask', editorId: null, editorText: '' });
    },

    answer(event) {
      const { id } = event.currentTarget.dataset;
      this.setData({ editorVisible: true, editorMode: 'answer', editorId: id, editorText: '' });
    },

    onEditorVisible(event) {
      if (!event.detail.visible) this.setData({ editorVisible: false });
    },

    onEditorInput(event) {
      this.setData({ editorText: event.detail.value });
    },

    async submitEditor() {
      const { editorMode, editorId, editorText, editorSubmitting } = this.data;
      const content = editorText.trim();
      if (!content || editorSubmitting) return;

      this.setData({ editorSubmitting: true });
      try {
        if (editorMode === 'ask') {
          await matters.askQuestion(this.data.matter.id, content);
          wx.showToast({ title: '已提问，等负责方回答', icon: 'none' });
        } else {
          await matters.answerQuestion(editorId, content);
          wx.showToast({ title: '已回答', icon: 'success' });
        }
        this.setData({ editorVisible: false, editorText: '' });
        this.fetchQuestions();
      } catch (error) {
        if (!guardProfileError(error, '提问前请先在个人资料里选好楼栋号。')) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      } finally {
        this.setData({ editorSubmitting: false });
      }
    },

    async echo(event) {
      const { id } = event.currentTarget.dataset;
      try {
        await matters.echoQuestion(id);
        this.fetchQuestions();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
    },

    // 沉淀：答过的问题变成「买前必懂」词条，后来的业主不用再问
    promote(event) {
      const { id } = event.currentTarget.dataset;
      wx.showModal({
        title: '给词条起个名',
        editable: true,
        confirmText: '沉淀',
        cancelText: '再想想',
        success: async ({ confirm, content }) => {
          if (!confirm || !(content || '').trim()) return;
          try {
            await matters.promoteQuestion(id, content.trim());
            wx.showToast({ title: '已进入买前必懂', icon: 'success' });
            this.triggerEvent('refresh'); // 事项 glossary 变了，让页面重拉
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },
  },
});
