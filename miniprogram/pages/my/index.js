const { request, uploadImage } = require('../../utils/request');

Page({
  data: {
    me: null,
    registration: null,
    myProjects: [],
    showMerchantForm: false,
    merchantName: '',
    merchantCategory: '',
  },

  onShow() {
    this.loadMe();
  },

  async loadMe() {
    try {
      const [meRes, mineRes] = await Promise.all([
        request('/me'),
        request('/projects/mine'),
      ]);
      this.setData({
        me: meRes.data,
        registration: meRes.data.registration || null,
        myProjects: mineRes.data,
        merchantName: meRes.data.merchant_name || '',
        merchantCategory: meRes.data.merchant_category || '',
      });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  // 微信原生头像选择
  async onChooseAvatar(event) {
    try {
      const url = await uploadImage(event.detail.avatarUrl);
      await request('/me', { method: 'PUT', data: { avatar: url } });
      this.setData({ 'me.avatar': url });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  // 微信原生昵称填写（输入框失焦时保存）
  async onNicknameBlur(event) {
    const nickname = (event.detail.value || '').trim();
    if (!nickname || nickname === this.data.me.nickname) return;
    try {
      await request('/me', { method: 'PUT', data: { nickname } });
      this.setData({ 'me.nickname': nickname });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  goRegistration() {
    wx.navigateTo({ url: '/pages/registration/index' });
  },

  goSurvey() {
    wx.navigateTo({ url: '/pages/survey/index' });
  },

  goPublish() {
    wx.navigateTo({ url: '/pages/leader-project/index' });
  },

  goProject(event) {
    wx.navigateTo({ url: `/pages/project/index?id=${event.currentTarget.dataset.id}` });
  },

  // ---- 商家身份 ----

  toggleMerchantForm() {
    this.setData({ showMerchantForm: !this.data.showMerchantForm });
  },

  onMerchantInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async applyMerchant() {
    const { merchantName, merchantCategory } = this.data;
    if (!merchantName.trim()) return wx.showToast({ title: '请填写商家名称', icon: 'none' });
    if (!merchantCategory.trim()) return wx.showToast({ title: '请填写主营品类', icon: 'none' });

    try {
      await request('/me', {
        method: 'PUT',
        data: {
          role: 'merchant',
          merchant_name: merchantName.trim(),
          merchant_category: merchantCategory.trim(),
        },
      });
      wx.showToast({ title: '已切换为商家身份', icon: 'success' });
      this.setData({ showMerchantForm: false });
      this.loadMe();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  async backToResident() {
    try {
      await request('/me', { method: 'PUT', data: { role: 'resident' } });
      wx.showToast({ title: '已切回业主身份', icon: 'success' });
      this.loadMe();
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },
});
