// 未选择“总是保持”时，24 小时内只请求一次订阅授权。
const TEMPLATE_ID = 'MNOujHx4Bcm_ruar87ONFsI7VbHhOMBZA1BFsHciA-o';
const ASKED_AT_KEY = 'subscribe_asked_at';
const ALWAYS_KEPT_KEY = 'subscribe_always_kept';
const ASK_INTERVAL = 24 * 60 * 60 * 1000;

function syncAlwaysKept() {
  wx.getSetting({
    withSubscriptions: true,
    success: ({ subscriptionsSetting }) => {
      const { mainSwitch, itemSettings = {} } = subscriptionsSetting;
      wx.setStorageSync(ALWAYS_KEPT_KEY, mainSwitch && itemSettings[TEMPLATE_ID] === 'accept');
    },
  });
}

function requestSubscribe() {
  return new Promise((resolve) => {
    const alwaysKept = wx.getStorageSync(ALWAYS_KEPT_KEY);
    const lastAsked = wx.getStorageSync(ASKED_AT_KEY) || 0;
    if (!alwaysKept && Date.now() - lastAsked < ASK_INTERVAL) {
      resolve();
      return;
    }

    wx.setStorageSync(ASKED_AT_KEY, Date.now());
    wx.requestSubscribeMessage({
      tmplIds: [TEMPLATE_ID],
      complete: (res) => {
        syncAlwaysKept();
        resolve(res);
      },
    });
  });
}

module.exports = { requestSubscribe };
