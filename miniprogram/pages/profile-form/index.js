// 个人资料：设置列表式行布局，头像、昵称、手机号（微信授权获取）对所有身份通用；
// 身份（业主/商家/物业…，选项由服务端下发，点击行弹 action-sheet 切换）决定下方行——
// 业主选楼栋（社区设置的楼栋清单）/填房号；相关方填名称，补充字段（如商家主营）
// 由类型元数据 category_label 决定是否出现。保存时按身份落库（含身份切换）。
const { uploadImage } = require('../../utils/request');
const profile = require('../../utils/api/profile');
const { getMe, updateMe, authPhone, bindParty, unbindParty } = require('../../utils/me');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');

Page({
  behaviors: [load, dirty],

  data: {
    avatar: '',
    nickname: '',
    phone: '',
    identity: 'resident', // 'resident' 或相关方类型 key
    identityLabel: '业主',
    identities: [],       // [{key, label, name_hint, category_label}]，业主 + 服务端下发的可自助入驻类型
    identityMeta: {},     // 当前相关方类型的表单元数据（名称提示、补充字段标签）
    // 业主字段
    buildings: [],        // 楼栋清单（社区设置下发），楼栋号只能从中选
    buildingIndex: -1,
    unitLabel: '',
    roomLabel: '',
    // 相关方字段（简介/详细介绍/照片各类型统一，内容自由发挥）
    partyName: '',
    partyCategory: '',
    partyIntro: '',
    partyDescription: '',
    partyImages: [],
    uploading: false,
    submitting: false,
  },

  onLoad() {
    this.reload();
  },

  reload() {
    return this.runLoad(async () => {
      const [me, options] = await Promise.all([getMe(), profile.getOptions()]);
      const partyTypes = (options.party_types || []).filter((item) => item.self_registrable);
      const identity = me.party ? me.party.type : 'resident';
      const identities = [{ key: 'resident', label: '业主' }, ...partyTypes];
      const current = identities.find((item) => item.key === identity);
      const buildings = options.buildings || [];
      this.setData({
        avatar: me.avatar || '',
        nickname: me.nickname || '',
        phone: me.phone || '',
        identity,
        identities,
        identityLabel: current ? current.label : (me.party && me.party.label) || '业主',
        identityMeta: current || {},
        buildings,
        buildingIndex: buildings.indexOf(me.unit_label),
        unitLabel: me.unit_label || '',
        roomLabel: me.room_label || '',
        // 没有在用的相关方身份时，按上次的档案预填（切走再切回来不用重填）
        partyName: (me.party && me.party.name) || (me.last_party && me.last_party.name) || '',
        partyCategory: (me.party && me.party.category) || (me.last_party && me.last_party.category) || '',
        partyIntro: (me.party && me.party.intro) || (me.last_party && me.last_party.intro) || '',
        partyDescription: (me.party && me.party.description) || (me.last_party && me.last_party.description) || '',
        partyImages: (me.party && me.party.images) || (me.last_party && me.last_party.images) || [],
      });
    });
  },

  // 微信原生头像选择，选完即保存（即时落库要给反馈，别让用户误以为整页都自动存了）
  async onChooseAvatar(event) {
    try {
      const url = await uploadImage(event.detail.avatarUrl);
      await updateMe({ avatar: url });
      this.setData({ avatar: url });
      wx.showToast({ title: '头像已更新', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  // 微信手机号授权组件回调：拿 code 去后端换真实绑定号码，换到即保存
  async onGetPhone(event) {
    if (!event.detail.code) {
      // 用户点了拒绝：给一句反馈说明用途与可见范围，而不是无声无息
      wx.showToast({ title: '未授权。手机号只有管理员和对接的团长能看到，不会公示', icon: 'none' });
      return;
    }
    try {
      const me = await authPhone(event.detail.code);
      this.setData({ phone: me.phone });
      wx.showToast({ title: '手机号已更新', icon: 'success' });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  chooseIdentity() {
    const { identities } = this.data;
    wx.showActionSheet({
      itemList: identities.map((item) => item.label),
      success: ({ tapIndex }) => {
        const item = identities[tapIndex];
        this.markDirty();
        this.setData({ identity: item.key, identityLabel: item.label, identityMeta: item });
      },
    });
  },

  onPickBuilding(event) {
    this.markDirty();
    const index = Number(event.detail.value);
    this.setData({ buildingIndex: index, unitLabel: this.data.buildings[index] });
  },

  onInput(event) {
    this.markDirty();
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  // ---- 相关方档案照片（门头/资质/服务现场，最多 9 张）----

  chooseImages() {
    if (this.data.uploading) return;
    const remaining = 9 - this.data.partyImages.length;
    if (remaining <= 0) return wx.showToast({ title: '最多 9 张', icon: 'none' });

    wx.chooseMedia({
      count: remaining,
      mediaType: ['image'],
      success: async ({ tempFiles }) => {
        this.setData({ uploading: true });
        try {
          const urls = await Promise.all(tempFiles.map((file) => uploadImage(file.tempFilePath)));
          this.markDirty();
          this.setData({ partyImages: [...this.data.partyImages, ...urls] });
        } catch (error) {
          wx.showToast({ title: error.message, icon: 'none' });
        } finally {
          this.setData({ uploading: false });
        }
      },
    });
  },

  removeImage(event) {
    this.markDirty();
    const partyImages = this.data.partyImages.filter((_, i) => i !== event.currentTarget.dataset.index);
    this.setData({ partyImages });
  },

  previewImage(event) {
    wx.previewImage({ urls: this.data.partyImages, current: event.currentTarget.dataset.url });
  },

  async submit() {
    const {
      identity, identityMeta, nickname, unitLabel, roomLabel,
      partyName, partyCategory, partyIntro, partyDescription, partyImages,
      submitting, uploading,
    } = this.data;
    if (submitting || uploading) return;

    if (identity === 'resident' && !unitLabel) {
      return wx.showToast({ title: '请选择楼栋号', icon: 'none' });
    }
    if (identity !== 'resident' && !partyName.trim()) {
      return wx.showToast({ title: '请填写名称', icon: 'none' });
    }

    this.setData({ submitting: true });
    try {
      if (identity === 'resident') {
        const me = await getMe();
        if (me.party) await unbindParty(); // 从相关方切回业主
        await updateMe({
          nickname: nickname.trim(),
          unit_label: unitLabel,
          room_label: roomLabel.trim(),
        });
      } else {
        await updateMe({ nickname: nickname.trim() });
        await bindParty(identity, {
          name: partyName.trim(),
          // 补充字段只有声明了 category_label 的类型才有（商家主营），其余类型不携带
          category: identityMeta.category_label ? partyCategory.trim() : '',
          intro: partyIntro.trim(),
          description: partyDescription.trim(),
          images: partyImages,
        });
      }
      this.clearDirty();
      // 成功后不复位 submitting：按钮保持 loading 直到返回，堵住 toast 800ms 里的二次提交
      wx.showToast({ title: '已保存', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },
});
