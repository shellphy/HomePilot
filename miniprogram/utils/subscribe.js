// 订阅消息授权收集（「活动状态提醒」单模板打全场景）。
// 微信的规则是一次授权、一次下发：在用户的每个主动操作节点（报名/发起/评价/答题/入驻）
// 顺手请求一次，勾选「总是保持以上选择」后不再弹窗但额度照常累积。
// 拒绝、不支持、报错都静默放行——授权是顺路的事，绝不挡住操作主流程。
const TEMPLATE_ID = 'MNOujHx4Bcm_ruar87ONFsI7VbHhOMBZA1BFsHciA-o';

function requestSubscribe() {
  return new Promise((resolve) => {
    if (!wx.requestSubscribeMessage) return resolve();
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
