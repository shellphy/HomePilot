const { ensureLogin } = require('./utils/request');

App({
  onLaunch() {
    // 静默登录，失败不阻塞（页面请求时会自动重试）
    ensureLogin().catch(() => {});
  },
});
