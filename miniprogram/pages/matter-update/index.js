const { uploadImage } = require('../../utils/request');
const matters = require('../../utils/api/matters');

function today() {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${now.getFullYear()}-${month}-${day}`;
}

Page({
  data: {
    id: null,
    date: '',
    content: '',
    images: [],
    uploading: false,
    submitting: false,
  },

  onLoad(query) {
    this.setData({ id: Number(query.id), date: today() });
  },

  onDateChange(event) {
    this.setData({ date: event.detail.value });
  },

  onInput(event) {
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
      wx.showToast({ title: '进度已发布', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
