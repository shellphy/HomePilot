// 问卷模块：标题/引言 + 该模块的题目列表。走统一 /matters 接口。
const matters = require('../../../utils/api/matters');
const load = require('../../../behaviors/load');
const dirty = require('../../../behaviors/dirty');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    mi: -1, // -1 = 新建模块
    matterTitle: '',
    modules: [],
    title: '',
    intro: '',
    questions: [],
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id), mi: Number(query.mi) });
    wx.setNavigationBarTitle({ title: Number(query.mi) < 0 ? '新建模块' : '编辑模块' });
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await matters.getMatter(this.data.id);
      // 后端只对管理员下发原始 payload：拿不到就挡住，避免非管理员误编丢字段（见交付报告后端后续项）
      if (res.data.payload === undefined) {
        wx.showModal({
          title: '暂不可编辑问卷',
          content: '问卷题目的编辑目前仅对管理员开放，其他发起人的编辑入口待后端支持。',
          showCancel: false,
          success: () => wx.navigateBack(),
        });
        return;
      }
      const payload = res.data.payload || {};
      // 保留征集的其它 payload 字段（pitch/purpose/collects_contact），保存模块时一并回传，
      // 否则后端 payloadFrom 会把没传的键重置掉
      this._preserved = {
        pitch: payload.pitch || '',
        purpose: payload.purpose || '',
        collects_contact: !!payload.collects_contact,
      };
      const modules = payload.modules || [];
      const current = this.data.mi >= 0 ? modules[this.data.mi] : null;
      this.setData({
        matterTitle: res.data.title,
        modules,
        // 从题目页返回会触发 onShow 重拉：标题/引言有未保存的本地编辑时保留，别被服务端值冲掉
        title: current && !this.dirty ? current.title : this.data.title,
        intro: current && !this.dirty ? (current.intro || '') : this.data.intro,
        questions: current ? current.questions : [],
      });
    });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  goQuestion(event) {
    const { qi } = event.currentTarget.dataset;
    wx.navigateTo({ url: `/pages/admin/census-question/index?id=${this.data.id}&mi=${this.data.mi}&qi=${qi}` });
  },

  addQuestion() {
    if (this.data.mi < 0) {
      return wx.showToast({ title: '先保存模块，再加题目', icon: 'none' });
    }
    wx.navigateTo({ url: `/pages/admin/census-question/index?id=${this.data.id}&mi=${this.data.mi}&qi=-1` });
  },

  async save() {
    const { id, mi, modules, title, intro, submitting } = this.data;
    if (submitting) return;
    if (!title.trim()) return wx.showToast({ title: '先填模块标题', icon: 'none' });

    const next = modules.map((module) => ({ ...module }));
    if (mi >= 0) {
      next[mi] = { ...next[mi], title: title.trim(), intro: intro.trim() };
    } else {
      next.push({ title: title.trim(), intro: intro.trim(), questions: [] });
    }

    this.setData({ submitting: true });
    try {
      await matters.updateMatter(id, {
        title: this.data.matterTitle,
        ...this._preserved,
        modules: next,
      });
      this.clearDirty();
      if (mi < 0) {
        // 落位到刚建好的模块，继续加题
        this.setData({ mi: next.length - 1, submitting: false });
        wx.setNavigationBarTitle({ title: '编辑模块' });
        wx.showToast({ title: '模块已建好，加题目吧', icon: 'none' });
        this.reload();
      } else {
        // 成功后不复位 submitting：按钮保持 loading 直到返回，堵住 toast 800ms 里的二次提交
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      }
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },

  remove() {
    if (this.data.mi < 0) return wx.navigateBack();
    wx.showModal({
      title: '删除这个模块？',
      content: '模块下的题目一并移除；已收到的旧答案会和统计对不上',
      confirmText: '删除',
      confirmColor: '#e34d59',
      success: async ({ confirm }) => {
        if (!confirm) return;
        const next = [...this.data.modules];
        next.splice(this.data.mi, 1);
        try {
          await matters.updateMatter(this.data.id, {
            title: this.data.matterTitle,
            ...this._preserved,
            modules: next,
          });
          this.clearDirty(); // 模块已删，未保存的编辑不必再拦返回
          wx.showToast({ title: '已删除', icon: 'success' });
          setTimeout(() => wx.navigateBack(), 800);
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
