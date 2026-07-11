// 个人资料：设置列表式行布局，头像、昵称固定；身份（业主/商家…，选项由服务端下发，
// 点击行弹 action-sheet 切换）决定下方行——业主填楼栋/房号/微信/手机，商家填名称/主营。
// 保存时按身份落库（含身份切换）。
const { uploadImage } = require('../../utils/request');
const profile = require('../../utils/api/profile');
const { getMe, updateMe, bindParty, unbindParty } = require('../../utils/me');
const load = require('../../behaviors/load');

Page({
  behaviors: [load],

  data: {
    avatar: '',
    nickname: '',
    identity: 'resident', // 'resident' 或相关方类型 key
    identityLabel: '业主',
    identities: [],       // [{key, label}]，业主 + 服务端下发的可自助入驻类型
    // 业主字段
    unitLabel: '',
    roomLabel: '',
    wechatId: '',
    phone: '',
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
      this.setData({
        avatar: me.avatar || '',
        nickname: me.nickname || '',
        identity,
        identities,
        // 当前身份不在可自助入驻列表时（管理员认证的类型），显示服务端给的 label
        identityLabel: current ? current.label : (me.party && me.party.label) || '业主',
        unitLabel: me.unit_label || '',
        roomLabel: me.room_label || '',
        wechatId: me.wechat_id || '',
        phone: me.phone || '',
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

  onInput(event) {
    this.setData({ [event.currentTarget.dataset.field]: event.detail.value });
  },

  async submit() {
    const { identity, nickname, unitLabel, roomLabel, wechatId, phone, partyName, partyCategory, submitting } = this.data;
    if (submitting) return;

    if (identity === 'resident' && !unitLabel.trim()) {
      return wx.showToast({ title: '请填写楼栋号', icon: 'none' });
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
          unit_label: unitLabel.trim(),
          room_label: roomLabel.trim(),
          wechat_id: wechatId.trim(),
          phone: phone.trim(),
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
