// 订阅消息授权：一天最多拉起一次，避免每次操作都弹窗。任何结果都静默放行。
const TEMPLATE_ID = 'MNOujHx4Bcm_ruar87ONFsI7VbHhOMBZA1BFsHciA-o';
const ASKED_AT_KEY = 'subscribe_asked_at';
const ASK_INTERVAL = 24 * 60 * 60 * 1000;

function requestSubscribe() {
  return new Promise((resolve) => {
    if (!wx.requestSubscribeMessage) return resolve();
    const lastAsked = wx.getStorageSync(ASKED_AT_KEY) || 0;
    if (Date.now() - lastAsked < ASK_INTERVAL) return resolve();
    wx.setStorageSync(ASKED_AT_KEY, Date.now());
    wx.requestSubscribeMessage({ tmplIds: [TEMPLATE_ID], complete: resolve });
  });
}

function getSubscribeStatus() {
  return new Promise((resolve) => {
    if (!wx.getSetting) return resolve('unsupported');
    wx.getSetting({
      withSubscriptions: true,
      success: ({ subscriptionsSetting }) => {
        const setting = subscriptionsSetting || {};
        resolve((setting.itemSettings && setting.itemSettings[TEMPLATE_ID]) || (setting.mainSwitch === false ? 'disabled' : 'unknown'));
      },
      fail: () => resolve('unknown'),
    });
  });
}

module.exports = { requestSubscribe, getSubscribeStatus };
