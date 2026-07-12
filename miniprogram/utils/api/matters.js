// 事项与表态的服务层：页面不拼 URL，只表达意图
const { request, streamRequest } = require('../request');

function listMatters() {
  return request('/matters');
}

function listMine() {
  return request('/matters/mine');
}

function listJoined() {
  return request('/matters/joined');
}

function getMatter(id) {
  return request(`/matters/${id}`);
}

// 统一创作：所有类型（团购/活动/互助/维权/征集/公告）的发起。
// data 里的内容字段（title/category/target_count/pitch/perk/terms/glossary/
// purpose/collects_contact/body/needs_survey/modules 等）都走顶层，不包 payload。
function createMatter(type, data) {
  return request('/matters', { method: 'POST', data: { type, ...data } });
}

function updateMatter(id, data) {
  return request(`/matters/${id}`, { method: 'PUT', data });
}

// 删除事项（后端按 is_admin 授权）
function deleteMatter(id) {
  return request(`/matters/${id}`, { method: 'DELETE' });
}

function flipState(id, state) {
  return request(`/matters/${id}/state`, { method: 'PUT', data: { state } });
}

function publishDeal(id, finalTerms, finalNote) {
  return request(`/matters/${id}/deal`, {
    method: 'PUT',
    data: { final_terms: finalTerms, final_note: finalNote },
  });
}

// shareContact = 成团等互通阶段愿意与牵头人互通手机号
function join(id, shareContact = true) {
  return request(`/matters/${id}/join`, { method: 'POST', data: { share_contact: shareContact } });
}

function leave(id) {
  return request(`/matters/${id}/join`, { method: 'DELETE' });
}

function review(id, rating, content) {
  return request(`/matters/${id}/review`, { method: 'PUT', data: { rating, content } });
}

function postUpdate(id, data) {
  return request(`/matters/${id}/updates`, { method: 'POST', data });
}

// 征集：schema 下发与表态提交（通用，装修摸底只是第一份 schema）
function getCensus(id) {
  return request(`/matters/${id}/census`);
}

function saveCensus(id, data) {
  return request(`/matters/${id}/census`, { method: 'PUT', data });
}

function getCensusReport(id) {
  return request(`/matters/${id}/census-report`);
}

function generateCensusReport(id) {
  return request(`/matters/${id}/census-report`, { method: 'POST' });
}

function shareCensusReport(id) {
  return request(`/matters/${id}/census-report/share`, { method: 'POST' });
}

function revokeCensusReport(id) {
  return request(`/matters/${id}/census-report/share`, { method: 'DELETE' });
}

function getSharedCensusReport(token) {
  return request(`/census-reports/${token}`);
}

// 发起者视图：主动勾选授权的参与者明细（后端限发起者本人/管理员可看）
function getCensusConsented(id) {
  return request(`/matters/${id}/census-consented`);
}

// 「买前必懂」AI 起草：返回三段草稿（是什么/怎么选/避坑），由填表人校订后提交
function draftGlossary(term, category) {
  return request('/glossary/draft', { method: 'POST', data: { term, category } });
}

// 业主侧 AI 答疑：带事项上下文的多轮对话，conversation_id 续聊。
// 流式返回，onDelta 收增量文字；返回 { abort, promise } 供停止/收尾。
function aiChatStream(id, question, conversationId, { onDelta } = {}) {
  return streamRequest(`/matters/${id}/ai-chat`, {
    method: 'POST',
    data: { question, conversation_id: conversationId || null },
    onDelta,
  });
}

// 「大家都在问」：公开问答（提问/同问/负责方回答/沉淀为买前必懂）
function getQuestions(id) {
  return request(`/matters/${id}/questions`);
}

function askQuestion(id, content) {
  return request(`/matters/${id}/questions`, { method: 'POST', data: { content } });
}

function echoQuestion(questionId) {
  return request(`/questions/${questionId}/echo`, { method: 'POST' });
}

function answerQuestion(questionId, content) {
  return request(`/questions/${questionId}/answer`, { method: 'PUT', data: { content } });
}

function promoteQuestion(questionId, term) {
  return request(`/questions/${questionId}/promote`, { method: 'POST', data: { term } });
}

module.exports = {
  listMatters,
  listMine,
  listJoined,
  getMatter,
  createMatter,
  updateMatter,
  deleteMatter,
  flipState,
  publishDeal,
  join,
  leave,
  review,
  postUpdate,
  getCensus,
  saveCensus,
  getCensusReport,
  generateCensusReport,
  shareCensusReport,
  revokeCensusReport,
  getSharedCensusReport,
  getCensusConsented,
  draftGlossary,
  aiChatStream,
  getQuestions,
  askQuestion,
  echoQuestion,
  answerQuestion,
  promoteQuestion,
};
