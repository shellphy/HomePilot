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

function markMatterSeen(id) {
  return request(`/matters/${id}/seen`, { method: 'POST' });
}

function listCensusOverview() {
  return request('/censuses/overview');
}

function updateParticipant(id, stanceId, data) {
  return request(`/matters/${id}/participants/${stanceId}`, { method: 'PUT', data });
}

// 统一创作：所有类型（团购/活动/互助/维权/征集/公告）的发起。
// data 里的内容字段（title/body/category/target_count/perk/terms/glossary/
// purpose/collects_contact/needs_survey/modules 等）都走顶层，不包 payload。
function createMatter(type, data) {
  return request('/matters', { method: 'POST', data: { type, ...data } });
}

function updateMatter(id, data) {
  return request(`/matters/${id}`, { method: 'PUT', data });
}

function submitMatterReview(id) {
  return request(`/matters/${id}/submit-review`, { method: 'POST' });
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

// 征集：schema 下发与表态提交（通用）
function getCensus(id) {
  return request(`/matters/${id}/census`);
}

function saveCensus(id, data) {
  return request(`/matters/${id}/census`, { method: 'PUT', data });
}

// 「让发起者看到我的问卷」授权开关：在「查看我的问卷」页冷静态设置
function setCensusConsent(id, visible) {
  return request(`/matters/${id}/census/consent`, { method: 'PUT', data: { visible_to_initiator: visible } });
}

function getCensusReport(id) {
  return request(`/matters/${id}/census-report`);
}

function generateCensusReport(id) {
  return request(`/matters/${id}/census-report`, { method: 'POST' });
}

// 发起者视图：主动勾选授权的参与者明细（后端限发起者本人/管理员可看）
function getCensusConsented(id) {
  return request(`/matters/${id}/census-consented`);
}

// 「买前必懂」AI 改写：发起人手填的一段说明交 AI 改顺，返回单段文本回填，由填表人校订后提交
function draftGlossary(term, draft, category) {
  return request('/glossary/draft', { method: 'POST', data: { term, draft, category } });
}

// 业主侧 AI 答疑：带事项上下文的多轮对话，conversation_id 续聊。
// 流式返回，onDelta 收增量文字、onSearching 收联网检索词、onSource 收命中来源；
// 返回 { abort, promise } 供停止/收尾。
function aiChatStream(id, question, conversationId, { answers, onDelta, onSearching, onSource } = {}) {
  return streamRequest(`/matters/${id}/ai-chat`, {
    method: 'POST',
    data: { question, conversation_id: conversationId || null, answers: answers || undefined },
    onDelta,
    onSearching,
    onSource,
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

// 删除整条问答（本人或管理员）
function deleteQuestion(questionId) {
  return request(`/questions/${questionId}`, { method: 'DELETE' });
}

// 只删回复保留问题（本人或管理员）
function deleteAnswer(questionId) {
  return request(`/questions/${questionId}/answer`, { method: 'DELETE' });
}

module.exports = {
  listMatters,
  listMine,
  listJoined,
  getMatter,
  markMatterSeen,
  listCensusOverview,
  updateParticipant,
  createMatter,
  updateMatter,
  submitMatterReview,
  deleteMatter,
  flipState,
  publishDeal,
  join,
  leave,
  review,
  postUpdate,
  getCensus,
  saveCensus,
  setCensusConsent,
  getCensusReport,
  generateCensusReport,
  getCensusConsented,
  draftGlossary,
  aiChatStream,
  getQuestions,
  askQuestion,
  echoQuestion,
  answerQuestion,
  promoteQuestion,
  deleteQuestion,
  deleteAnswer,
};
