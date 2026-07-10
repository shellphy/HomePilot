// 环境配置：本地联调时在开发者工具勾选「不校验合法域名」
// 用显式 IPv4 地址，避免 localhost 在 request/uploadFile 里解析到不同协议栈；
// 后端启动命令：php artisan serve --host=0.0.0.0
module.exports = {
  apiBase: 'http://127.0.0.1:8000/api',
};
