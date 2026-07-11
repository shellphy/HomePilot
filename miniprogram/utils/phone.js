// 电话号码的统一交互：点击弹「拨打 / 复制」二选一（名录与详情页共用）。
// 开发者工具里 makePhoneCall 只会提示「模拟拨打」，真机才会唤起系统拨号。
function contactPhone(phone) {
  if (!phone) {
    return;
  }
  wx.showActionSheet({
    itemList: ['拨打电话', '复制号码'],
    success: ({ tapIndex }) => {
      if (tapIndex === 0) {
        wx.makePhoneCall({ phoneNumber: phone });
      } else {
        // setClipboardData 成功后微信自带「内容已复制」提示，不用再 toast
        wx.setClipboardData({ data: phone });
      }
    },
  });
}

module.exports = { contactPhone };
