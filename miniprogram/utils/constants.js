// 事项相关的共享常量（列表、详情、发起页都用，改文案只改这里）

const PILL_CLASS = {
  open: 'pill-open',
  negotiating: 'pill-negotiating',
  seeking: 'pill-seeking',
  collecting: 'pill-seeking',
  done: 'pill-done',
  closed: 'pill-done',
  resolved: 'pill-done',
  aborted: 'pill-aborted',
};

// 各事项类型的参与文案（团购/公告有专属组件，不走这份配置）
// shareText：加入前共享开关的说明（维权不互通联系方式，没有这一项）
const TYPE_META = {
  activity: { joinCta: '报名参加', joinedCta: '已报名 ✓（点击取消）', foot: '人已报名', roster: '报名名单', shareText: '与发起人互通手机号，方便拉群约时间（仅双方可见）' },
  aid: { joinCta: '算我一个', joinedCta: '已加入 ✓（点击退出）', foot: '人已加入', roster: '参与名单', shareText: '与发起人互通手机号，方便对接（仅双方可见）' },
  rights: { joinCta: '参与联名', joinedCta: '已联名 ✓（点击撤回）', foot: '人已联名', roster: '联名名单' },
};

// 状态机本身由后端下发（详情/列表数据里的 states 与 state_label），前端不重复维护；
// 这里只把状态值映射到展示样式，未知状态回退到默认。
function pillClass(state) {
  return PILL_CLASS[state] || 'pill-seeking';
}

function joinPercent(matter) {
  if (!matter.target_count) return 0;
  // 目标进度按「确认参团」口径（团购两段表态）；老数据/其他类型没有该字段时退回总数
  const count = matter.confirmed_count != null ? matter.confirmed_count : matter.join_count;
  return Math.min(100, Math.round((count / matter.target_count) * 100));
}

// 把后端下发的状态机（{value: label} 对象）转成模板好用的数组
function stateOptions(states) {
  return Object.keys(states || {}).map((value) => ({ value, label: states[value] }));
}

// 评分转星串（WXML 不能循环画星，预先拼好）
function starsOf(rating) {
  return '★★★★★'.slice(0, rating) + '☆☆☆☆☆'.slice(0, 5 - rating);
}

module.exports = { PILL_CLASS, TYPE_META, pillClass, joinPercent, stateOptions, starsOf };
