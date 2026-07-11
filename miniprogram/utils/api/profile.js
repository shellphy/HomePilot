// 小区概况与静态配置的服务层
const { request } = require('../request');

// 静态配置（社区文案、选项、可发起类型）会话内缓存一次
let optionsCache = null;

function getOptions() {
  if (!optionsCache) {
    optionsCache = request('/options').catch((error) => {
      optionsCache = null;
      throw error;
    });
  }
  return optionsCache;
}

// 管理端改了社区设置后调用，让下一次 getOptions 取到新值
function invalidateOptions() {
  optionsCache = null;
}

function getStats() {
  return request('/stats');
}

// 已认证相关方名录（商家带成团数与评价沉淀）
function listParties() {
  return request('/parties');
}

module.exports = {
  getOptions,
  invalidateOptions,
  getStats,
  listParties,
};
