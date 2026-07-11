// 管理端 · 文本题明细与归纳：浏览匿名文字反馈（搜索即时计数辅助数数），
// 手写「主题 + 条数 + 概括」，存草稿或发布到公示面（census-insights 页）。
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');
const dirty = require('../../../behaviors/dirty');

const emptyTheme = () => ({ title: '', count: '', note: '' });

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    questions: [], // [{key, text, module_title, answers, summary}]
    qi: 0, // 当前文本题下标
    keyword: '',
    shownAnswers: [],
    hitCount: 0,
    themes: [emptyTheme()],
    published: false,
    savingAction: '', // ''｜'publish'｜'draft'：两个按钮各自转圈，点哪个哪个有反馈
  },

  onLoad(query) {
    this.setData({ id: Number(query.id) });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.getCensusText(this.data.id);
      this.setData({ questions: res.questions });
      this.pickQuestionAt(Math.min(this.data.qi, res.questions.length - 1));
    });
  },

  pickQuestion(event) {
    const qi = Number(event.currentTarget.dataset.qi);
    if (qi === this.data.qi) return;
    // 切题会用服务端归纳覆盖编辑区，未保存的修改先确认再丢
    if (this.dirty) {
      wx.showModal({
        title: '切换题目？',
        content: '当前题的归纳修改还没保存，切换后会丢失',
        confirmText: '切换',
        success: ({ confirm }) => {
          if (!confirm) return;
          this.clearDirty();
          this.pickQuestionAt(qi);
        },
      });
      return;
    }
    this.pickQuestionAt(qi);
  },

  // 切题：答案列表、搜索、归纳编辑区整体切到该题
  pickQuestionAt(qi) {
    const question = this.data.questions[qi];
    if (!question) return;
    const { summary } = question;
    this.setData({
      qi,
      keyword: '',
      themes: summary && summary.themes.length
        ? summary.themes.map((theme) => ({ ...theme, count: String(theme.count) }))
        : [emptyTheme()],
      published: !!(summary && summary.published),
    });
    this.applyFilter();
  },

  onSearch(event) {
    this.setData({ keyword: event.detail.value.trim() });
    this.applyFilter();
  },

  applyFilter() {
    const { questions, qi, keyword } = this.data;
    const all = (questions[qi] && questions[qi].answers) || [];
    const shown = keyword ? all.filter((answer) => answer.includes(keyword)) : all;
    this.setData({ shownAnswers: shown, hitCount: shown.length });
  },

  onThemeInput(event) {
    this.markDirty();
    const { index, field } = event.currentTarget.dataset;
    this.setData({ [`themes[${index}].${field}`]: event.detail.value });
  },

  addTheme() {
    this.markDirty();
    this.setData({ themes: [...this.data.themes, emptyTheme()] });
  },

  removeTheme(event) {
    this.markDirty();
    const index = Number(event.currentTarget.dataset.index);
    const themes = this.data.themes.filter((theme, i) => i !== index);
    this.setData({ themes: themes.length ? themes : [emptyTheme()] });
  },

  saveDraft() {
    // 已发布状态下「存草稿」等于把公示面上的归纳撤下来，是面向全小区的下线动作，先确认
    if (this.data.published) {
      wx.showModal({
        title: '撤下公示？',
        content: '这道题的归纳将从公示面撤下、转回草稿，随时可以再发布',
        confirmText: '撤下',
        confirmColor: '#e34d59',
        success: ({ confirm }) => {
          if (confirm) this.save(false);
        },
      });
      return;
    }
    this.save(false);
  },

  publish() {
    return this.save(true);
  },

  async save(published) {
    const { id, questions, qi, themes, savingAction } = this.data;
    if (savingAction) return;

    const cleaned = themes
      .map((theme) => ({
        title: theme.title.trim(),
        count: Number(theme.count) || 0,
        note: (theme.note || '').trim(),
      }))
      .filter((theme) => theme.title);
    if (published && !cleaned.length) {
      return wx.showToast({ title: '发布前先写至少一个主题', icon: 'none' });
    }

    this.setData({ savingAction: published ? 'publish' : 'draft' });
    try {
      await admin.saveCensusSummary(id, {
        question_key: questions[qi].key,
        themes: cleaned,
        published,
      });
      this.clearDirty();
      // 答案明细没变，原地同步归纳即可，不整页 reload——保住正在用的搜索关键词
      this.setData({
        published,
        themes: cleaned.length
          ? cleaned.map((theme) => ({ ...theme, count: String(theme.count) }))
          : [emptyTheme()],
        [`questions[${qi}].summary`]: { themes: cleaned, published },
      });
      wx.showToast({ title: published ? '已发布到公示面' : '草稿已保存', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ savingAction: '' });
    }
  },
});
