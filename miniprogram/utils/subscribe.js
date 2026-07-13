// 订阅消息授权收集（「活动状态提醒」单模板打全场景）。
// 微信的规则是一次授权、一次下发：在用户的主动操作节点顺手请求一次。
// 但用户没勾「总是保持以上选择」时，每次操作都会弹窗，非常打扰——
// 所以这里加一天一次的节流：主动拉起最多一天一次，其余时间静默放行，
// 既不再频繁弹窗，也仍能周期性地补充下发额度。
// 拒绝、不支持、报错都静默放行——授权是顺路的事，绝不挡住操作主流程。
const TEMPLATE_ID = 'MNOujHx4Bcm_ruar87ONFsI7VbHhOMBZA1BFsHciA-o';
const ASKED_AT_KEY = 'subscribe_asked_at';
const ASK_INTERVAL = 24 * 60 * 60 * 1000; // 同一天内不再重复弹

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
