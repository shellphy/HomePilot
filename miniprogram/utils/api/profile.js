const { request } = require('../request');

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

function invalidateOptions() {
  optionsCache = null;
}

function getStats() {
  return request('/stats');
}

function listParties() {
  return request('/parties');
}

function getParty(id) {
  return request(`/parties/${id}`);
}

module.exports = {
  getOptions,
  invalidateOptions,
  getStats,
  listParties,
  getParty,
};
