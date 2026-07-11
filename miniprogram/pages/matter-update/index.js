const { uploadImage } = require('../../utils/request');
const matters = require('../../utils/api/matters');
const dirty = require('../../behaviors/dirty');

function today() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

Page({
  behaviors: [dirty],

  data: {
    id: null,
    date: '',
    today: '', // 进度是已发生的事，日期选择上限到今天
    content: '',
    images: [],
    uploading: false,
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id), date: today(), today: today() });
  },

  onDateChange(event) {
    this.markDirty();
    this.setData({ date: event.detail.value });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ content: event.detail.value });
  },

  chooseImages() {
    if (this.data.uploading) return;
    const remaining = 9 - this.data.images.length;
    if (remaining <= 0) return wx.showToast({ title: '最多 9 张', icon: 'none' });

    wx.chooseMedia({
      count: remaining,
      mediaType: ['image'],
      success: async ({ tempFiles }) => {
        this.setData({ uploading: true });
        try {
          const urls = await Promise.all(tempFiles.map((file) => uploadImage(file.tempFilePath)));
          this.markDirty();
          this.setData({ images: [...this.data.images, ...urls] });
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        } finally {
          this.setData({ uploading: false });
        }
      },
    });
  },

  removeImage(event) {
    this.markDirty();
    const images = this.data.images.filter((_, i) => i !== event.currentTarget.dataset.index);
    this.setData({ images });
  },

  previewImage(event) {
    wx.previewImage({ urls: this.data.images, current: event.currentTarget.dataset.url });
  },

  async submit() {
    const { id, date, content, images, submitting, uploading } = this.data;
    if (submitting || uploading) return;
    if (!content.trim()) return wx.showToast({ title: '写一句进度内容吧', icon: 'none' });

    this.setData({ submitting: true });
    try {
      await matters.postUpdate(id, { happened_on: date, content: content.trim(), images });
      this.clearDirty();
      // 成功后不复位 submitting：按钮保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      wx.showToast({ title: '进度已发布', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },
});
