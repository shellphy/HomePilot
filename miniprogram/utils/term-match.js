function findFirstTerm(content, terms) {
  return terms.reduce(
    (first, term) => {
      const index = content.indexOf(term);
      if (index === -1 || (first.index !== -1 && index >= first.index)) {
        return first;
      }

      return { index, term };
    },
    { index: -1, term: '' },
  );
}

function splitByTerms(text, terms) {
  const content = String(text || '');
  const candidates = (terms || []).filter((term) => term && term.trim());
  if (!content) return [];
  if (!candidates.length) return [{ text: content }];

  // 长词优先，避免“中央空调”被“空调”截断。
  const sorted = [...candidates].sort((a, b) => b.length - a.length);
  const segments = [];
  let rest = content;

  while (rest) {
    const { index: hitIndex, term: hitTerm } = findFirstTerm(rest, sorted);
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
