// 表单脏标记：有未保存的修改时，返回/退出前弹确认，防止编辑丢失
module.exports = Behavior({
  methods: {
    markDirty() {
      if (this.dirty || !wx.enableAlertBeforeUnload) return;
      this.dirty = true;
      wx.enableAlertBeforeUnload({ message: '修改还没保存，确定要离开吗？' });
    },

    clearDirty() {
      if (!this.dirty) return;
      this.dirty = false;
      wx.disableAlertBeforeUnload();
    },
  },
});
