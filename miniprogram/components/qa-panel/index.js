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
      wx.showModal({
        title: '向团长/商家提问',
        editable: true,
        placeholderText: '问题和回答都会公示，帮到后面的邻居',
        confirmText: '提问',
        cancelText: '再想想',
        success: async ({ confirm, content }) => {
          if (!confirm || !(content || '').trim()) return;
          try {
            await matters.askQuestion(this.data.matter.id, content.trim());
            wx.showToast({ title: '已提问，等负责方回答', icon: 'none' });
            this.fetchQuestions();
          } catch (error) {
            if (!guardProfileError(error, '提问会以「楼栋 + 昵称」公示，请先在个人资料里选好楼栋号。')) {
              wx.showToast({ title: error.message, icon: 'none' });
            }
          }
        },
      });
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

    answer(event) {
      const { id, answered } = event.currentTarget.dataset;
      wx.showModal({
        title: answered ? '修改回答' : '回答这个问题',
        editable: true,
        placeholderText: '回答会公示；拿不准的写「以书面确认为准」',
        confirmText: '发布回答',
        cancelText: '再想想',
        success: async ({ confirm, content }) => {
          if (!confirm || !(content || '').trim()) return;
          try {
            await matters.answerQuestion(id, content.trim());
            wx.showToast({ title: '已回答', icon: 'success' });
            this.fetchQuestions();
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
        title: '沉淀为「买前必懂」',
        editable: true,
        placeholderText: '给这条词条起个名，如：整机保修',
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
