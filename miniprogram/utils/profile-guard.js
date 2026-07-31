function guardProfileError(error, content) {
  const response = error.response && error.response.data;
  if (response && response.code === 'verification_required') {
    wx.showModal({
      title: '需要身份认证',
      content: response.message,
      confirmText: '去认证',
      success: ({ confirm }) => {
        if (confirm) wx.navigateTo({ url: '/pages/profile-form/index' });
      },
    });
    return true;
  }

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
