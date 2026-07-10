// 团购状态相关的共享常量（列表、详情、发起页都用，改文案只改这里）

const PILL_CLASS = {
  open: 'pill-open',
  negotiating: 'pill-negotiating',
  seeking: 'pill-seeking',
  done: 'pill-done',
};

const STATUS_FLOW = [
  { value: 'seeking', label: '意向征集' },
  { value: 'negotiating', label: '谈判中' },
  { value: 'open', label: '接龙中' },
  { value: 'done', label: '已成团' },
];

function pillClass(status) {
  return PILL_CLASS[status] || 'pill-seeking';
}

function signupPercent(project) {
  if (!project.target_households) return 0;
  return Math.min(100, Math.round((project.signups_count / project.target_households) * 100));
}

module.exports = { PILL_CLASS, STATUS_FLOW, pillClass, signupPercent };
