// 把 AI 输出的 Markdown 文本转成 rich-text 可渲染的 HTML 字符串。
// 只覆盖答疑常见语法（标题 / 加粗 / 斜体 / 行内代码 / 代码块 / 有序无序列表 / 引用 /
// 分割线 / 链接），其余按纯文本处理。流式过程中未闭合的标记先按字面显示，闭合后自动成型。
// 样式一律走内联 style：rich-text 子节点的 class 在组件里不一定生效，内联最稳。

const CODE_MARK = String.fromCharCode(0); // 占位标记，正常文本不会出现，避免与文中内容撞车

function escapeHtml(text) {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// 行内解析：先把行内代码抠出来占位保护，转义后再套用加粗 / 斜体 / 链接，最后回填代码
function renderInline(text) {
  const codes = [];
  let masked = text.replace(/`([^`]+)`/g, (match, code) => {
    codes.push(code);
    return `${CODE_MARK}${codes.length - 1}${CODE_MARK}`;
  });
  masked = escapeHtml(masked);
  masked = masked.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>'); // 加粗先于斜体，别让 ** 被拆成两个 *
  masked = masked.replace(/__([^_]+)__/g, '<strong>$1</strong>');
  masked = masked.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
  // 链接只高亮文字：rich-text 内不可点，保留 URL 反而误导
  masked = masked.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<span style="color:#3b6cff">$1</span>');
  masked = masked.replace(
    new RegExp(`${CODE_MARK}(\\d+)${CODE_MARK}`, 'g'),
    (match, index) =>
      `<code style="background:#f2f3f5;padding:2rpx 8rpx;border-radius:6rpx;font-family:monospace;font-size:26rpx">${escapeHtml(codes[Number(index)])}</code>`,
  );
  return masked;
}

// 判断某行是否是块级结构的开头（用来打断段落的连续合并）
function isBlockStart(line) {
  return (
    /^```/.test(line) ||
    /^#{1,6}\s+/.test(line) ||
    /^>\s?/.test(line) ||
    /^\s*[-*+]\s+/.test(line) ||
    /^\s*\d+\.\s+/.test(line) ||
    /^\s*([-*_])(\s*\1){2,}\s*$/.test(line)
  );
}

const HEADING_SIZES = [34, 32, 30, 29, 28, 28];

/**
 * @param {string} text 原始 Markdown 文本
 * @returns {string} 供 rich-text nodes 使用的 HTML 字符串
 */
function mdToHtml(text) {
  if (!text) {
    return '';
  }
  const lines = text.replace(/\r\n/g, '\n').split('\n');
  const out = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (/^```/.test(line)) {
      // 代码块 ```
      i += 1;
      const buffer = [];
      while (i < lines.length && !/^```/.test(lines[i])) {
        buffer.push(lines[i]);
        i += 1;
      }
      i += 1; // 跳过结尾的 ```
      out.push(
        `<pre style="background:#f6f7f9;padding:16rpx 20rpx;border-radius:12rpx;font-family:monospace;font-size:26rpx;white-space:pre-wrap;margin:8rpx 0">${escapeHtml(buffer.join('\n'))}</pre>`,
      );
    } else if (/^\s*$/.test(line)) {
      // 空行
      i += 1;
    } else if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
      // 分割线
      out.push('<div style="border-top:1rpx solid #eee;margin:14rpx 0"></div>');
      i += 1;
    } else if (/^#{1,6}\s+/.test(line)) {
      // 标题
      const heading = line.match(/^(#{1,6})\s+(.*)$/);
      const size = HEADING_SIZES[heading[1].length - 1];
      out.push(`<p style="font-weight:600;font-size:${size}rpx;margin:12rpx 0 6rpx">${renderInline(heading[2])}</p>`);
      i += 1;
    } else if (/^>\s?/.test(line)) {
      // 引用
      const buffer = [];
      while (i < lines.length && /^>\s?/.test(lines[i])) {
        buffer.push(renderInline(lines[i].replace(/^>\s?/, '')));
        i += 1;
      }
      out.push(
        `<div style="border-left:6rpx solid #ddd;padding-left:16rpx;color:#8a8a8a;margin:8rpx 0">${buffer.join('<br>')}</div>`,
      );
    } else if (/^\s*[-*+]\s+/.test(line)) {
      // 无序列表
      const items = [];
      while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) {
        items.push(`<li style="margin:2rpx 0">${renderInline(lines[i].replace(/^\s*[-*+]\s+/, ''))}</li>`);
        i += 1;
      }
      out.push(`<ul style="margin:6rpx 0;padding-left:36rpx">${items.join('')}</ul>`);
    } else if (/^\s*\d+\.\s+/.test(line)) {
      // 有序列表
      const items = [];
      while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) {
        items.push(`<li style="margin:2rpx 0">${renderInline(lines[i].replace(/^\s*\d+\.\s+/, ''))}</li>`);
        i += 1;
      }
      out.push(`<ol style="margin:6rpx 0;padding-left:40rpx">${items.join('')}</ol>`);
    } else {
      // 段落：合并连续的普通行，行内换行渲染成 <br>
      const buffer = [];
      while (i < lines.length && !/^\s*$/.test(lines[i]) && !isBlockStart(lines[i])) {
        buffer.push(renderInline(lines[i]));
        i += 1;
      }
      out.push(`<p style="margin:0 0 10rpx">${buffer.join('<br>')}</p>`);
    }
  }

  return out.join('');
}

module.exports = { mdToHtml };
