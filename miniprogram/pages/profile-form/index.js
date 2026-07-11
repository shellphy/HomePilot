// 个人资料：设置列表式行布局，头像、昵称、手机号（微信授权获取）对所有身份通用；
// 身份（业主/商家…，选项由服务端下发，点击行弹 action-sheet 切换）决定下方行——
// 业主选楼栋（社区设置的楼栋清单）/填房号，商家填名称/主营。保存时按身份落库（含身份切换）。
const { uploadImage } = require('../../utils/request');
const profile = require('../../utils/api/profile');
const { getMe, updateMe, authPhone, bindParty, unbindParty } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    avatar: '',
    nickname: '',
    phone: '',
    identity: 'resident', // 'resident' 或相关方类型 key
    identityLabel: '业主',
    identities: [],       // [{key, label}]，业主 + 服务端下发的可自助入驻类型
    // 业主字段
    buildings: [],        // 楼栋清单（社区设置下发），楼栋号只能从中选
    buildingIndex: -1,
    unitLabel: '',
    roomLabel: '',
    // 相关方字段
    partyName: '',
    partyCategory: '',
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
        // 当前身份不在可自助入驻列表时（管理员认证的类型），显示服务端给的 label
        identityLabel: current ? current.label : (me.party && me.party.label) || '业主',
        buildings,
        buildingIndex: buildings.indexOf(me.unit_label),
        unitLabel: me.unit_label || '',
        roomLabel: me.room_label || '',
        partyName: (me.party && me.party.name) || '',
        partyCategory: (me.party && me.party.category) || '',
      });
    });
  },

  // 微信原生头像选择，选完即保存
  async onChooseAvatar(event) {
    try {
      const url = await uploadImage(event.detail.avatarUrl);
      await updateMe({ avatar: url });
      this.setData({ avatar: url });
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    }
  },

  // 微信手机号授权组件回调：拿 code 去后端换真实绑定号码，换到即保存
  async onGetPhone(event) {
    if (!event.detail.code) {
      // 用户点了拒绝：给一句反馈说明用途与可见范围，而不是无声无息
      wx.showToast({ title: '未授权。手机号仅管理员和成团对接可见，不会公示', icon: 'none' });
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
        this.setData({ identity: item.key, identityLabel: item.label });
      },
    });
  },

  onPickBuilding(event) {
    const index = Number(event.detail.value);
    this.setData({ buildingIndex: index, unitLabel: this.data.buildings[index] });
  },

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async submit() {
    const { identity, nickname, unitLabel, roomLabel, partyName, partyCategory, submitting } = this.data;
    if (submitting) return;

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
        await bindParty(identity, partyName.trim(), partyCategory.trim());
      }
      wx.showToast({ title: '已保存', icon: 'success' });
      setTimeout(() => wx.navigateBack(), 800);
    } catch (error) {
      wx.showToast({ title: error.message, icon: 'none' });
    } finally {
      this.setData({ submitting: false });
    }
  },
});
