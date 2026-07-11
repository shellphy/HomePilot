// 把一段文案按「买前必懂」的术语切段：命中的段渲染成可点的词，就地弹出决策卡。
// 教育发生在业主撞见术语的那一刻，而不是让他先去读完术语表。
function splitByTerms(text, terms) {
  const content = String(text || '');
  const candidates = (terms || []).filter((term) => term && term.trim());
  if (!content) return [];
  if (!candidates.length) return [{ text: content }];

  // 长词优先：同一位置「中央空调」不能被「空调」截胡
  const sorted = [...candidates].sort((a, b) => b.length - a.length);
  const segments = [];
  let rest = content;

  while (rest) {
    let hitIndex = -1;
    let hitTerm = '';
    for (const term of sorted) {
      const index = rest.indexOf(term);
      if (index !== -1 && (hitIndex === -1 || index < hitIndex)) {
        hitIndex = index;
        hitTerm = term;
      }
    }
    if (hitIndex === -1) {
      segments.push({ text: rest });
      break;
    }
    if (hitIndex > 0) segments.push({ text: rest.slice(0, hitIndex) });
    segments.push({ text: hitTerm, term: hitTerm });
    rest = rest.slice(hitIndex + hitTerm.length);
  }

  return segments;
}

module.exports = { splitByTerms };
