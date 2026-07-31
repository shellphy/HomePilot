const matters = require('./api/matters');

async function doRewrite(page, index, row) {
  if (page._draftingGlossary) return;
  page._draftingGlossary = true;
  wx.showLoading({ title: 'AI 改写中，稍等片刻', mask: true });
  try {
    const res = await matters.draftGlossary(row.term.trim(), row.explain.trim(), (page.data.category || '').trim());
    wx.hideLoading();
    page.markDirty();
    page.setData({ [`glossary[${index}].explain`]: res.data.explain });
    wx.showToast({ title: '已改写，发布前请把关', icon: 'none' });
  } catch (error) {
    wx.hideLoading();
    wx.showToast({ title: error.message, icon: 'none' });
  } finally {
    page._draftingGlossary = false;
  }
}

function draftGlossaryRow(page, index) {
  const row = page.data.glossary[index];
  if (!row || !row.term.trim()) {
    wx.showToast({ title: '先填术语再改写', icon: 'none' });
    return;
  }
  if (!(row.explain || '').trim()) {
    wx.showToast({ title: '先自己写一句，AI 帮你改顺', icon: 'none' });
    return;
  }

  doRewrite(page, index, row);
}

module.exports = { draftGlossaryRow };
