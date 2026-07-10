// 管理端 · 社区设置：名称/口号/承诺文案/选项清单，保存即生效、不发版
const admin = require('../../../utils/api/admin');
const { invalidateOptions } = require('../../../utils/api/profile');
const load = require('../../../behaviors/load');

// 单行文本、多行文本、数字、清单（一行一项）四类字段，按组渲染
const GROUPS = [
  {
    title: '社区身份',
    fields: [
      { key: 'name', label: '小区名称', kind: 'input' },
      { key: 'app_name', label: '小程序对外名称', kind: 'input' },
      { key: 'slogan', label: '主口号', kind: 'input' },
      { key: 'sub_slogan', label: '副口号', kind: 'input' },
      { key: 'total_households', label: '小区总户数', kind: 'number' },
    ],
  },
  {
    title: '承诺与提示文案',
    fields: [
      { key: 'pledge', label: '公益承诺（详情页等处展示）', kind: 'textarea' },
      { key: 'initiator_note', label: '牵头人须知（发起页展示）', kind: 'textarea' },
      { key: 'initiate_hint', label: '发起引导语', kind: 'textarea' },
      { key: 'data_footnote', label: '数据页脚注', kind: 'textarea' },
    ],
  },
  {
    title: '选项清单（一行一项）',
    fields: [
      { key: 'layouts', label: '户型', kind: 'list' },
      { key: 'decoration_modes', label: '装修方式', kind: 'list' },
      { key: 'categories', label: '团购品类', kind: 'list' },
    ],
  },
];

Page({
  behaviors: [load],

  data: {
    groups: GROUPS,
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
      GROUPS.forEach((group) => group.fields.forEach((field) => {
        if (field.kind === 'list') values[field.key] = (values[field.key] || []).join('\n');
        if (field.kind === 'number') values[field.key] = String(values[field.key] || '');
      }));
      this.setData({ values });
    });
  },

  onInput(event) {
    this.setData({ [`values.${event.currentTarget.dataset.key}`]: event.detail.value });
  },

  async save() {
    if (this.data.submitting) return;
    const values = { ...this.data.values };
    GROUPS.forEach((group) => group.fields.forEach((field) => {
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
