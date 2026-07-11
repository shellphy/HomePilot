// 「买前必懂」的 AI 起草：业主端与管理端表单共用。
// AI 只出草稿，回填后仍由填表人把关修改；已填过的内容覆盖前先确认。
const matters = require('./api/matters');

function draftGlossaryRow(page, index) {
  const row = page.data.glossary[index];
  if (!row || !row.term.trim()) {
    wx.showToast({ title: '先填术语再起草', icon: 'none' });
    return;
  }

  const hasContent = ['explain', 'judge', 'caution'].some((field) => (row[field] || '').trim());
  if (hasContent) {
    wx.showModal({
      title: '覆盖已填内容？',
      content: 'AI 草稿会覆盖这一条已填的解释。',
      confirmText: '覆盖',
      cancelText: '再想想',
      success: ({ confirm }) => {
        if (confirm) doDraft(page, index, row);
      },
    });
    return;
  }

  doDraft(page, index, row);
}

async function doDraft(page, index, row) {
  if (page._draftingGlossary) return;
  page._draftingGlossary = true;
  wx.showLoading({ title: 'AI 起草中，稍等片刻', mask: true });
  try {
    const res = await matters.draftGlossary(row.term.trim(), (page.data.category || '').trim());
    wx.hideLoading();
    page.markDirty();
    page.setData({
      [`glossary[${index}].explain`]: res.data.explain,
      [`glossary[${index}].judge`]: res.data.judge,
      [`glossary[${index}].caution`]: res.data.caution,
    });
    wx.showToast({ title: '草稿已回填，发布前请把关', icon: 'none' });
  } catch (error) {
    wx.hideLoading();
    wx.showToast({ title: error.message, icon: 'none' });
  } finally {
    page._draftingGlossary = false;
  }
}

module.exports = { draftGlossaryRow };
