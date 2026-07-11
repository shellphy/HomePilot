// 业主资料不全（未选楼栋号）时后端返回结构化 errors.profile：
// 统一弹窗引导去个人资料页补全，回来即可继续。返回是否已处理。
function guardProfileError(error, content) {
  const errors = (error.response && error.response.data && error.response.data.errors) || {};
  if (!errors.profile) return false;

  wx.showModal({
    title: '先选好楼栋号',
    content,
    confirmText: '去完善',
    success: ({ confirm }) => {
      if (confirm) wx.navigateTo({ url: '/pages/profile-form/index' });
    },
  });
  return true;
}

module.exports = { guardProfileError };
