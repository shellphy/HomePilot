// 管理端 · 文本题明细与归纳：浏览匿名文字反馈（搜索即时计数辅助数数），
// 手写「主题 + 条数 + 概括」，存草稿或发布到公示面（census-insights 页）。
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

const emptyTheme = () => ({ title: '', count: '', note: '' });

Page({
  behaviors: [load],

  data: {
    id: null,
    questions: [], // [{key, text, module_title, answers, summary}]
    qi: 0, // 当前文本题下标
    keyword: '',
    shownAnswers: [],
    hitCount: 0,
    themes: [emptyTheme()],
    published: false,
    submitting: false,
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
    this.pickQuestionAt(Number(event.currentTarget.dataset.qi));
  },

  // 切题：答案列表、搜索、归纳编辑区整体切到该题
  pickQuestionAt(qi) {
    const question = this.data.questions[qi];
    if (!question) return;
    const summary = question.summary;
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
    const { index, field } = event.currentTarget.dataset;
    this.setData({ [`themes[${index}].${field}`]: event.detail.value });
  },

  addTheme() {
    this.setData({ themes: [...this.data.themes, emptyTheme()] });
  },

  removeTheme(event) {
    const index = Number(event.currentTarget.dataset.index);
    const themes = this.data.themes.filter((theme, i) => i !== index);
    this.setData({ themes: themes.length ? themes : [emptyTheme()] });
  },

  saveDraft() {
    return this.save(false);
  },

  publish() {
    return this.save(true);
  },

  async save(published) {
    const { id, questions, qi, themes, submitting } = this.data;
    if (submitting) return;

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

    this.setData({ submitting: true });
    try {
      await admin.saveCensusSummary(id, {
        question_key: questions[qi].key,
        themes: cleaned,
        published,
      });
      this.setData({ published });
      wx.showToast({ title: published ? '已发布到公示面' : '草稿已保存', icon: 'success' });
      await this.reload();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
