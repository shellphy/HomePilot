const { request } = require('../../utils/request');

Page({
  data: {
    layouts: [],
    decorationModes: [],
    categories: [],
    layout: '',
    decorationMode: '',
    interests: [],
    interestOn: {}, // {品类: true}，WXML 里不能调 indexOf，用查表渲染选中态
    unitLabel: '',
    wechatId: '',
    phone: '',
    submitting: false,
    loaded: false,
  },

  onLoad() {
    this.loadForm();
  },

  async loadForm() {
    try {
      const [options, me, mine] = await Promise.all([
        request('/options'),
        request('/me'),
        request('/registration'),
      ]);

      const interests = (mine.data && mine.data.interests) || [];
      const interestOn = {};
      interests.forEach((item) => { interestOn[item] = true; });

      this.setData({
        layouts: options.layouts,
        decorationModes: options.decoration_modes,
        categories: options.categories,
        unitLabel: me.data.unit_label || '',
        wechatId: me.data.wechat_id || '',
        phone: me.data.phone || '',
        layout: (mine.data && mine.data.layout) || '',
        decorationMode: (mine.data && mine.data.decoration_mode) || '',
        interests,
        interestOn,
        loaded: true,
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  pickLayout(event) {
    this.setData({ layout: event.currentTarget.dataset.value });
  },

  pickMode(event) {
    this.setData({ decorationMode: event.currentTarget.dataset.value });
  },

  toggleInterest(event) {
    const { value } = event.currentTarget.dataset;
    const interests = this.data.interests.includes(value)
      ? this.data.interests.filter((item) => item !== value)
      : [...this.data.interests, value];

    const interestOn = {};
    interests.forEach((item) => { interestOn[item] = true; });

    this.setData({ interests, interestOn });
  },

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async submit() {
    const { layout, decorationMode, interests, unitLabel, wechatId, phone, submitting } = this.data;
    if (submitting) return;

    if (!layout) return wx.showToast({ title: '请选择你家的户型', icon: 'none' });
    if (!decorationMode) return wx.showToast({ title: '请选择装修方式', icon: 'none' });
    if (!interests.length) return wx.showToast({ title: '至少选一个感兴趣的团购', icon: 'none' });
    if (!unitLabel.trim()) return wx.showToast({ title: '请填写楼栋号', icon: 'none' });
    if (!wechatId.trim()) return wx.showToast({ title: '请填写微信号', icon: 'none' });

    this.setData({ submitting: true });
    try {
      await request('/me', {
        method: 'PUT',
        data: { unit_label: unitLabel.trim() },
      });
      await request('/registration', {
        method: 'PUT',
        data: {
          layout,
          decoration_mode: decorationMode,
          interests,
          wechat_id: wechatId.trim(),
          ...(phone.trim() ? { phone: phone.trim() } : {}),
        },
      });
      wx.showModal({
        title: '登记完成',
        content: '再花 3 分钟填一份进阶问卷（家庭、生活方式、预算），这些匿名数据是团长和商家谈判的筹码。现在填吗？',
        confirmText: '现在填',
        cancelText: '以后再说',
        success: (res) => {
          if (res.confirm) {
            wx.redirectTo({ url: '/pages/survey/index' });
          } else {
            wx.switchTab({ url: '/pages/map/index' });
          }
        },
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
