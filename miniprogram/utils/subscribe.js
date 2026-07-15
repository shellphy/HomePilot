// 订阅消息授权：一次授权换一次下发额度，额度可叠加、永久有效，直到被下发消耗完。
// 勾了「总是保持以上选择」后微信不再弹窗，于是每次操作都调一次把额度攒起来；
// 没勾时一天最多拉起一次，避免每次操作都弹窗。任何结果都静默放行。
const TEMPLATE_ID = 'MNOujHx4Bcm_ruar87ONFsI7VbHhOMBZA1BFsHciA-o';
const ASKED_AT_KEY = 'subscribe_asked_at';
const ALWAYS_KEPT_KEY = 'subscribe_always_kept';
const ASK_INTERVAL = 24 * 60 * 60 * 1000;

function requestSubscribe() {
  return new Promise((resolve) => {
    // 同步读：不在点击手势与 requestSubscribeMessage 之间插入异步调用
    const alwaysKept = wx.getStorageSync(ALWAYS_KEPT_KEY);
    const lastAsked = wx.getStorageSync(ASKED_AT_KEY) || 0;
    if (!alwaysKept && Date.now() - lastAsked < ASK_INTERVAL) return resolve();

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

/**
 * itemSettings 只包含用户勾过「总是保持以上选择」的模板，是判断勾没勾的唯一依据。
 * 每次调用后都重算：用户事后取消勾选时抹掉标记，退回一天一问。
 */
function syncAlwaysKept() {
  wx.getSetting({
    withSubscriptions: true,
    success: ({ subscriptionsSetting }) => {
      const { mainSwitch, itemSettings = {} } = subscriptionsSetting;
      wx.setStorageSync(ALWAYS_KEPT_KEY, mainSwitch && itemSettings[TEMPLATE_ID] === 'accept');
    },
  });
}

module.exports = { requestSubscribe };
