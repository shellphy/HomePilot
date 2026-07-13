// 「大家都在问」：针对事项的公开问答面板。
// 填好资料的业主、已认证相关方都能问能答；同问聚合热度。本人或管理员可删问答，管理员可拉黑成员。
const matters = require('../../utils/api/matters');
const admin = require('../../utils/api/admin');
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
    canModerate: false,
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
          canModerate: res.can_moderate,
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
          wx.showToast({ title: '已提问，等人解答', icon: 'none' });
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

    // 删除整条问答（本人或管理员）
    remove(event) {
      const { id } = event.currentTarget.dataset;
      wx.showModal({
        title: '删除整条问答？',
        content: '问题连同回答一并删除，不可恢复',
        confirmText: '删除',
        confirmColor: '#e34d59',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.deleteQuestion(id);
            wx.showToast({ title: '已删除', icon: 'none' });
            this.fetchQuestions();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 只删回复保留问题（本人或管理员）
    removeAnswer(event) {
      const { id } = event.currentTarget.dataset;
      wx.showModal({
        title: '删除这条回复？',
        content: '问题保留，仅删除回复',
        confirmText: '删除',
        confirmColor: '#e34d59',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.deleteAnswer(id);
            wx.showToast({ title: '已删回复', icon: 'none' });
            this.fetchQuestions();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 管理员拉黑发问人/回复人：之后不能再参与社区互动
    blockPerson(event) {
      const { id, name } = event.currentTarget.dataset;
      wx.showModal({
        title: '拉黑这位成员？',
        content: `「${name}」将不能再提问、回复、接龙、发起`,
        confirmText: '拉黑',
        confirmColor: '#e34d59',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await admin.blockResident(id);
            wx.showToast({ title: '已拉黑', icon: 'none' });
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
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
