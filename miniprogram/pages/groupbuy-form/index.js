// 发起/编辑团购（groupbuy 类型的表单），格式与交互对齐管理端事项表单
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');
const { guardProfileError } = require('../../utils/profile-guard');
const { requestSubscribe } = require('../../utils/subscribe');

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    states: {}, // {key: label}，编辑时来自后端下发的该事项状态机；新建不选状态（后端定初始态）
    stateKeys: [],
    category: '',
    title: '',
    state: '',
    targetCount: '',
    pitch: '',
    perk: '',
    terms: [],
    glossary: [],
    // 按户出方案（中央空调这类非标品）：只在发起时选，之后不可改（联系互通的隐私承诺不同）
    needsSurvey: false,
    initiatorNote: '',
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    this.setData({ id });
    wx.setNavigationBarTitle({ title: id ? '编辑团购' : '发起团购' });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const options = await profile.getOptions();
      this.setData({
        initiatorNote: (options.community && options.community.initiator_note) || '',
      });

      if (this.data.id) {
        const res = await matters.getMatter(this.data.id);
        const matter = res.data;
        this.setData({
          category: matter.category,
          title: matter.title,
          states: matter.states,
          stateKeys: Object.keys(matter.states),
          state: matter.state,
          targetCount: String(matter.target_count),
          pitch: matter.pitch || '',
          perk: matter.perk || '',
          terms: matter.terms || [],
          glossary: matter.glossary || [],
          needsSurvey: !!matter.needs_survey,
        });
      }
    });
  },

  chooseState() {
    const { states, stateKeys } = this.data;
    wx.showActionSheet({
      itemList: stateKeys.map((key) => states[key]),
      success: ({ tapIndex }) => {
        this.markDirty();
        this.setData({ state: stateKeys[tapIndex] });
      },
    });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  onSurveyChange(event) {
    this.markDirty();
    this.setData({ needsSurvey: event.detail.value });
  },

  // 条目列表（团购条件 / 买前必懂）的增删改
  addRow(event) {
    this.markDirty();
    const { list } = event.currentTarget.dataset;
    const blank = list === 'terms' ? { label: '', value: '' } : { term: '', explain: '' };
    this.setData({ [list]: [...this.data[list], blank] });
  },

  removeRow(event) {
    this.markDirty();
    const { list, index } = event.currentTarget.dataset;
    const rows = [...this.data[list]];
    rows.splice(index, 1);
    this.setData({ [list]: rows });
  },

  onRowInput(event) {
    this.markDirty();
    const { list, index, field } = event.currentTarget.dataset;
    this.setData({ [`${list}[${index}].${field}`]: event.detail.value });
  },

  async submit() {
    const { id, category, title, state, targetCount, pitch, perk, submitting } = this.data;
    if (submitting) return;

    if (!category.trim()) return wx.showToast({ title: '请填写品类', icon: 'none' });
    if (!title.trim()) return wx.showToast({ title: '请填写标题', icon: 'none' });
    if (!targetCount || Number(targetCount) < 1) {
      return wx.showToast({ title: '请填写目标人数', icon: 'none' });
    }

    const payload = {
      category: category.trim(),
      title: title.trim(),
      target_count: Number(targetCount),
      pitch: pitch.trim(),
      perk: perk.trim(),
      terms: this.data.terms.filter((t) => t.label.trim() && t.value.trim()),
      glossary: this.data.glossary.filter((g) => g.term.trim() && g.explain.trim()),
      // 发起时锁定；编辑时也回传当前值（后端只认发起时的选择）
      needs_survey: this.data.needsSurvey,
    };

    this.setData({ submitting: true });
    try {
      // 提交的这一下顺手收一次订阅授权：审核结果/新报名的通知才有额度可推
      await requestSubscribe();
      if (id) {
        await matters.updateGroupbuy(id, { ...payload, state });
        this.clearDirty();
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        await matters.createGroupbuy(payload);
        this.clearDirty();
        wx.showModal({
          title: '已提交',
          content: '管理员通常会在 24 小时内完成审核，通过后就会出现在小区页里。你是这个团购的团长，可以在「我的」里随时查看和管理它。',
          showCancel: false,
          confirmText: '好的',
          success: () => wx.navigateBack(),
        });
      }
    } catch (error) {
      if (!guardProfileError(error, '你发起后就是本团团长，也会以「楼栋 + 昵称」出现在公示名单里，请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
      // 只在失败时复位：成功分支保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      this.setData({ submitting: false });
    }
  },
});
