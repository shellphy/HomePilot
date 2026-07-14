// 环境配置：按小程序运行环境自动切换 API 地址
// - develop（开发版）：本地后端，工具里勾选「不校验合法域名」；
//   用显式 IPv4 地址，避免 localhost 在 request/uploadFile 里解析到不同协议栈；
//   后端启动命令：php artisan serve --host=0.0.0.0
// - trial（体验版）/ release（正式版）：走线上域名，需在小程序后台配置为合法域名
const API_BASES = {
  develop: 'http://127.0.0.1:8000/api',
  trial: 'https://tianqingfu.shellphy.cn/api',
  release: 'https://tianqingfu.shellphy.cn/api',
};

const { envVersion } = wx.getAccountInfoSync().miniProgram;

module.exports = {
  apiBase: API_BASES[envVersion] || API_BASES.develop,
};
