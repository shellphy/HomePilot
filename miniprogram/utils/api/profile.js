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

function getStats() {
  return request('/stats');
}

module.exports = {
  getOptions,
  getStats,
};
