// 管理端 · 拉黑名单：查看被拉黑的成员、解除拉黑（拉黑动作在「大家都在问」等内容处发起）
const admin = require('../../../utils/api/admin');
const load = require('../../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    blocks: [],
  },

  onShow() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const res = await admin.listBlocks();
      this.setData({ blocks: res.data });
    });
  },

  unblock(event) {
    const { id, name } = event.currentTarget.dataset;
    wx.showModal({
      title: '解除拉黑？',
      content: `「${name}」将恢复参与社区互动`,
      confirmText: '解除',
      success: async ({ confirm }) => {
        if (!confirm) return;
        try {
          await admin.unblockResident(id);
          wx.showToast({ title: '已解除', icon: 'none' });
          this.reload();
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        }
      },
    });
  },
});
