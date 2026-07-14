// 把 AI 输出的 Markdown 文本转成 rich-text 可渲染的 HTML 字符串。
// 只覆盖答疑常见语法（标题 / 加粗 / 斜体 / 行内代码 / 代码块 / 有序无序列表 / 引用 /
// 分割线 / 链接），其余按纯文本处理。流式过程中未闭合的标记先按字面显示，闭合后自动成型。
// 样式一律走内联 style：rich-text 子节点的 class 在组件里不一定生效，内联最稳。

const CODE_MARK = String.fromCharCode(0); // 占位标记，正常文本不会出现，避免与文中内容撞车
const TYPING_CARET = '<span style="color:#8a8f98;font-weight:400">▍</span>';

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
  // 图片先于链接：![alt](url) 里也含 []()，否则会被链接规则先啃掉留下多余的 !
  masked = masked.replace(
    /!\[([^\]]*)\]\(([^)]+)\)/g,
    (match, alt, url) => `<img src="${url}" alt="${alt}" style="max-width:100%;border-radius:8rpx" />`,
  );
  masked = masked.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong><em>$1</em></strong>'); // 粗斜体先于粗/斜，别被拆开
  masked = masked.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>'); // 加粗先于斜体，别让 ** 被拆成两个 *
  masked = masked.replace(/__([^_]+)__/g, '<strong>$1</strong>');
  masked = masked.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
  masked = masked.replace(/~~([^~]+)~~/g, '<del style="color:#8a8f98">$1</del>'); // 删除线
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

// GFM 表格分隔行：| --- | :--: | 这类，至少一个横线，允许对齐冒号
function isTableSeparator(line) {
  return /\|/.test(line) && /^\s*\|?(\s*:?-+:?\s*\|)+\s*:?-*:?\s*$/.test(line);
}

// 把一行按 | 拆成单元格（去掉首尾竖线），转义忽略被 \ 转义的竖线
function splitTableRow(line) {
  let text = line.trim();
  if (text.startsWith('|')) {
    text = text.slice(1);
  }
  if (text.endsWith('|')) {
    text = text.slice(0, -1);
  }
  return text.split('|').map((cell) => cell.trim());
}

// 列表项前导空格数（tab 记作两个空格），用来按缩进模拟嵌套
function indentWidth(line) {
  return line.match(/^(\s*)/)[1].replace(/\t/g, '  ').length;
}

// 无序列表项：[ ] / [x] 渲染成勾选框并去掉圆点，普通项照常；pad 是嵌套缩进
function unorderedItem(content, pad) {
  const task = content.match(/^\[([ xX])\]\s+(.*)$/);
  if (task) {
    const done = task[1].toLowerCase() === 'x';
    return `<li style="margin:2rpx 0;padding-left:${pad}rpx;list-style:none">${done ? '☑' : '☐'} ${renderInline(task[2])}</li>`;
  }
  return `<li style="margin:2rpx 0;padding-left:${pad}rpx">${renderInline(content)}</li>`;
}

const HEADING_SIZES = [34, 32, 30, 29, 28, 28];

// 光标必须进入 rich-text 的最后一个文本块，放在组件外会被块级段落挤到下一行。
function appendTypingCaret(html) {
  const closingTags = ['</p>', '</li>', '</pre>', '</div>'];
  const insertionAt = Math.max(...closingTags.map((tag) => html.lastIndexOf(tag)));

  return insertionAt >= 0
    ? `${html.slice(0, insertionAt)}${TYPING_CARET}${html.slice(insertionAt)}`
    : `${html}${TYPING_CARET}`;
}

/**
 * @param {string} text 原始 Markdown 文本
 * @param {boolean} typing 是否在最后一个文字右侧显示流式光标
 * @returns {string} 供 rich-text nodes 使用的 HTML 字符串
 */
function mdToHtml(text, typing = false) {
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
      // 无序列表：按缩进给子项加左内边距模拟嵌套，支持 [ ] / [x] 任务项
      const baseIndent = indentWidth(line);
      const items = [];
      while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) {
        const pad = Math.max(0, Math.round((indentWidth(lines[i]) - baseIndent) / 2)) * 28;
        items.push(unorderedItem(lines[i].replace(/^\s*[-*+]\s+/, ''), pad));
        i += 1;
      }
      out.push(`<ul style="margin:6rpx 0;padding-left:36rpx">${items.join('')}</ul>`);
    } else if (/^\s*\d+\.\s+/.test(line)) {
      // 有序列表：同样按缩进模拟嵌套
      const baseIndent = indentWidth(line);
      const items = [];
      while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) {
        const pad = Math.max(0, Math.round((indentWidth(lines[i]) - baseIndent) / 2)) * 28;
        items.push(`<li style="margin:2rpx 0;padding-left:${pad}rpx">${renderInline(lines[i].replace(/^\s*\d+\.\s+/, ''))}</li>`);
        i += 1;
      }
      out.push(`<ol style="margin:6rpx 0;padding-left:40rpx">${items.join('')}</ol>`);
    } else if (/\|/.test(line) && i + 1 < lines.length && isTableSeparator(lines[i + 1])) {
      // GFM 表格：表头行 + 分隔行 + 若干数据行；rich-text 支持 table/th/td 标签
      const header = splitTableRow(line);
      i += 2; // 跳过表头与分隔行
      const bodyRows = [];
      while (i < lines.length && /\|/.test(lines[i]) && !/^\s*$/.test(lines[i])) {
        bodyRows.push(splitTableRow(lines[i]));
        i += 1;
      }
      const cell = 'border:1rpx solid #e5e6eb;padding:10rpx 14rpx;text-align:left;font-size:26rpx';
      const head = header
        .map((text) => `<th style="${cell};background:#f6f7f9;font-weight:600">${renderInline(text)}</th>`)
        .join('');
      const rows = bodyRows
        .map((row) => {
          // 缺列补空、多列截断，对齐表头列数，避免错行
          const cells = header.map((_, index) => `<td style="${cell}">${renderInline(row[index] || '')}</td>`).join('');
          return `<tr>${cells}</tr>`;
        })
        .join('');
      out.push(
        `<table style="border-collapse:collapse;width:100%;margin:10rpx 0"><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table>`,
      );
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

  const html = out.join('');

  return typing ? appendTypingCaret(html) : html;
}

module.exports = { mdToHtml };
