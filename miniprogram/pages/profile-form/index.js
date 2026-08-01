const { uploadImage } = require('../../utils/request');
const profile = require('../../utils/api/profile');
const { getMe, updateMe, verifyOwner, resolvePhone, bindParty, unbindParty } = require('../../utils/me');
const { requestSubscribe } = require('../../utils/subscribe');
const load = require('../../behaviors/load');
const dirty = require('../../behaviors/dirty');
const { syncInputValue } = require('../../utils/input');

Page({
  behaviors: [load, dirty],

  data: {
    avatar: '',
    nickname: '',
    phone: '',
    identity: 'resident',
    identityLabel: '业主',
    identities: [],
    identityMeta: {},
    wasParty: false,
    buildings: [],
    buildingIndex: -1,
    unitLabel: '',
    roomLabel: '',
    layouts: [],
    layoutIndex: -1,
    layoutLabel: '',
    ownerVerified: false,
    inviteCode: '',
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
      const layouts = options.layouts || [];
      this.setData({
        avatar: me.avatar || '',
        nickname: me.nickname || '',
        phone: me.phone || '',
        identity,
        identities,
        wasParty: !!me.party,
        identityLabel: current ? current.label : (me.party && me.party.label) || '业主',
        identityMeta: current || {},
        buildings,
        buildingIndex: buildings.indexOf(me.unit_label),
        unitLabel: me.unit_label || '',
        roomLabel: me.room_label || '',
        layouts,
        layoutIndex: layouts.indexOf(me.layout_label),
        layoutLabel: me.layout_label || '',
        ownerVerified: !!me.is_owner_verified,
        inviteCode: '',
        partyName: (me.party && me.party.name) || (me.last_party && me.last_party.name) || '',
        partyCategory: (me.party && me.party.category) || (me.last_party && me.last_party.category) || '',
        partyIntro: (me.party && me.party.intro) || (me.last_party && me.last_party.intro) || '',
        partyDescription: (me.party && me.party.description) || (me.last_party && me.last_party.description) || '',
        partyImages: (me.party && me.party.images) || (me.last_party && me.last_party.images) || [],
      });
    });
  },

  async onChooseAvatar(event) {
    try {
      const url = await uploadImage(event.detail.avatarUrl);
      this.markDirty();
      this.setData({ avatar: url });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  async onGetPhone(event) {
    if (!event.detail.code) {
      wx.showToast({ title: '未授权，可手动填写手机号', icon: 'none' });
      return;
    }
    try {
      const phone = await resolvePhone(event.detail.code);
      this.markDirty();
      this.setData({ phone });
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

  onPickLayout(event) {
    this.markDirty();
    const index = Number(event.detail.value);
    this.setData({ layoutIndex: index, layoutLabel: this.data.layouts[index] });
  },

  onInput(event) {
    this.markDirty();
    syncInputValue(this, event.currentTarget.dataset.field, event.detail.value);
  },

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
      identity,
      identityMeta,
      wasParty,
      avatar,
      nickname,
      phone,
      unitLabel,
      roomLabel,
      layoutLabel,
      ownerVerified,
      inviteCode,
      partyName,
      partyCategory,
      partyIntro,
      partyDescription,
      partyImages,
      submitting,
      uploading,
    } = this.data;
    if (submitting || uploading) return;

    if (identity === 'resident' && !unitLabel) {
      return wx.showToast({ title: '请选择楼栋号', icon: 'none' });
    }
    if (identity !== 'resident' && !partyName.trim()) {
      return wx.showToast({ title: '请填写名称', icon: 'none' });
    }

    const shouldVerifyOwner = identity === 'resident' && !ownerVerified && inviteCode.trim() !== '';
    this.setData({ submitting: true });
    try {
      const commonFields = { nickname: nickname.trim(), phone: phone.trim() };
      if (avatar) commonFields.avatar = avatar;
      if (identity === 'resident') {
        if (wasParty) await unbindParty();
        await updateMe({
          ...commonFields,
          unit_label: unitLabel,
          room_label: roomLabel.trim(),
          layout_label: layoutLabel,
        });
        if (shouldVerifyOwner) {
          await verifyOwner(inviteCode.trim());
        }
      } else {
        await requestSubscribe();
        await updateMe(commonFields);
        await bindParty(identity, {
          name: partyName.trim(),
          category: identityMeta.category_label ? partyCategory.trim() : '',
          intro: partyIntro.trim(),
          description: partyDescription.trim(),
          images: partyImages,
        });
      }
      this.clearDirty();
      wx.showToast({ title: shouldVerifyOwner ? '认证成功' : '已保存', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
      this.setData({ submitting: false });
    }
  },
});
