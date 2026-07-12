// 通用张罗表单：活动 / 互助 / 维权（团购有专属表单 groupbuy-form）
const matters = require('../../utils/api/matters');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');
const { guardProfileError } = require('../../utils/profile-guard');
const { requestSubscribe } = require('../../utils/subscribe');

const TYPE_COPY = {
  activity: {
    title: '发起邻里活动',
    titlePlaceholder: '如：周六建材市场组团踩点',
    pitchPlaceholder: '时间、地点、怎么集合、大概安排……写清楚邻居才敢报名',
    targetHint: '满多少人成行',
  },
  aid: {
    title: '发起互助',
    titlePlaceholder: '如：拼车去工地看进度（周日上午）',
    pitchPlaceholder: '要互助的事、时间、你能提供什么、需要几个人……',
    targetHint: '需要几个人',
  },
  rights: {
    title: '发起维权联名',
    titlePlaceholder: '如：地下车位定价过高，联名要求公开成本',
    pitchPlaceholder: '事情的来龙去脉、诉求是什么、联名后打算怎么递交……写清楚才有人跟',
    targetHint: '目标联名人数',
  },
};

Page({
  behaviors: [load, dirty],

  data: {
    id: null,
    type: 'activity',
    copy: TYPE_COPY.activity,
    title: '',
    pitch: '',
    targetCount: '',
    submitting: false,
  },

  onLoad(query) {
    const id = query.id ? Number(query.id) : null;
    const type = query.type || 'activity';
    this.setData({ id, type, copy: TYPE_COPY[type] || TYPE_COPY.activity });
    wx.setNavigationBarTitle({ title: id ? '编辑' : (TYPE_COPY[type] || TYPE_COPY.activity).title });
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      if (!this.data.id) return;
      const res = await matters.getMatter(this.data.id);
      const matter = res.data;
      this.setData({
        type: matter.type,
        copy: TYPE_COPY[matter.type] || TYPE_COPY.activity,
        title: matter.title,
        pitch: matter.pitch || '',
        targetCount: matter.target_count ? String(matter.target_count) : '',
      });
    });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async submit() {
    const { id, type, copy, title, pitch, targetCount, submitting } = this.data;
    if (submitting) return;
    if (!title.trim()) return wx.showToast({ title: '先起个一句话标题', icon: 'none' });
    if (!pitch.trim()) return wx.showToast({ title: '把事情说清楚，邻居才敢跟', icon: 'none' });
    if (targetCount && Number(targetCount) < 1) {
      return wx.showToast({ title: `${copy.targetHint}至少是 1`, icon: 'none' });
    }

    const payload = {
      title: title.trim(),
      pitch: pitch.trim(),
      ...(targetCount ? { target_count: Number(targetCount) } : {}),
    };

    this.setData({ submitting: true });
    try {
      // 提交的这一下顺手收一次订阅授权：审核结果/新报名的通知才有额度可推
      await requestSubscribe();
      if (id) {
        await matters.updateMatter(id, payload);
        this.clearDirty();
        wx.showToast({ title: '已保存', icon: 'success' });
        setTimeout(() => wx.navigateBack(), 800);
      } else {
        await matters.createMatter(type, payload);
        this.clearDirty();
        wx.showModal({
          title: '已提交',
          content: '通常 24 小时内完成审核，通过后就会出现在小区页里。这件事由你牵头，可以在「我的」里随时查看和管理它。',
          showCancel: false,
          confirmText: '好的',
          success: () => wx.navigateBack(),
        });
      }
    } catch (error) {
      if (!guardProfileError(error, '你发起后就是这件事的牵头人，也会以「楼栋 + 昵称」出现在公示名单里，请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
      // 只在失败时复位：成功分支保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      this.setData({ submitting: false });
    }
  },
});
