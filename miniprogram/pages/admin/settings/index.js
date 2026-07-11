// 管理端 · 社区设置：名称/口号/承诺文案/选项清单，保存即生效、不发版
// 表单结构（分组、字段、控件类型）由后端随设置值一起下发，新增设置字段不用改前端
const admin = require('../../../utils/api/admin');
const { invalidateOptions } = require('../../../utils/api/profile');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    groups: [],
    values: {},
    submitting: false,
  },

  onLoad() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.getSettings();
      const values = { ...res.data };
      res.groups.forEach((group) => group.fields.forEach((field) => {
        if (field.kind === 'list') values[field.key] = (values[field.key] || []).join('\n');
        if (field.kind === 'number') values[field.key] = String(values[field.key] || '');
      }));
      this.setData({ groups: res.groups, values });
    });
  },

  onInput(event) {
    this.setData({ [`values.${event.currentTarget.dataset.key}`]: event.detail.value });
  },

  async save() {
    if (this.data.submitting) return;
    const values = { ...this.data.values };
    this.data.groups.forEach((group) => group.fields.forEach((field) => {
      if (field.kind === 'list') {
        values[field.key] = String(values[field.key]).split('\n').map((line) => line.trim()).filter(Boolean);
      }
      if (field.kind === 'number') values[field.key] = Number(values[field.key]) || 0;
    }));

    this.setData({ submitting: true });
    try {
      await admin.saveSettings(values);
      invalidateOptions(); // 全端文案来自 /options，立即让缓存失效
      wx.showToast({ title: '已保存', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
