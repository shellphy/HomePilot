// 事务相关的共享常量（列表、详情、发起页都用，改文案只改这里）

const PILL_CLASS = {
  open: 'pill-open',
  negotiating: 'pill-negotiating',
  seeking: 'pill-seeking',
  collecting: 'pill-seeking',
  done: 'pill-done',
  closed: 'pill-done',
  resolved: 'pill-done',
};

// 各事务类型的参与文案（团购/公告有专属组件，不走这份配置）
const TYPE_META = {
  activity: { joinCta: '报名参加', joinedCta: '已报名 ✓（点击取消）', foot: '人已报名', roster: '报名名单' },
  aid: { joinCta: '算我一个', joinedCta: '已加入 ✓（点击退出）', foot: '人已加入', roster: '参与名单' },
  rights: { joinCta: '参与联名', joinedCta: '已联名 ✓（点击撤回）', foot: '户已联名', roster: '联名名单' },
};

// 团购（groupbuy 类型）的状态机
const STATE_FLOW = [
  { value: 'seeking', label: '意向征集' },
  { value: 'negotiating', label: '谈判中' },
  { value: 'open', label: '接龙中' },
  { value: 'done', label: '已成团' },
];

function pillClass(state) {
  return PILL_CLASS[state] || 'pill-seeking';
}

function joinPercent(matter) {
  if (!matter.target_count) return 0;
  return Math.min(100, Math.round((matter.join_count / matter.target_count) * 100));
}

module.exports = { PILL_CLASS, STATE_FLOW, TYPE_META, pillClass, joinPercent };
