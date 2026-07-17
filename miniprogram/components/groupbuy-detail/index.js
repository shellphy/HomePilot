// 团购详情体：该类型的全部行为（报名/评价/流转/成交公示入口）都在组件内，
// 数据变更后向页面发 refresh 事件，由页面重新拉取。
const matters = require('../../utils/api/matters');
const profile = require('../../utils/api/profile');
const { pillClass, joinPercent, stateOptions, starsOf } = require('../../utils/constants');
const { guardProfileError } = require('../../utils/profile-guard');
const { splitByTerms } = require('../../utils/term-match');
const { requestSubscribe } = require('../../utils/subscribe');
const { unbindParty } = require('../../utils/me');

Component({
  options: {
    styleIsolation: 'apply-shared',
  },

  properties: {
    matter: Object,
    joined: Boolean,
    isInitiator: Boolean,
    myReview: Object,
    contacts: Array, // 团长视角：同意共享的参团者联系方式（成团后）
    contactRoster: Array,
    initiatorContact: Object, // 参团者视角：团长联系方式（成团后且自己同意过共享）
    isParty: Boolean, // 相关方身份不参与接龙，报名区改为解释 + 切回业主入口
    partyLabel: String, // 当前相关方身份的显示名（解释文案用）
    myShareContact: Boolean, // 我报名时的共享意愿：成团后没共享的看不到团长电话，给补开入口
    myJoinStage: String, // 我的承诺档位（intent=登记意向 / confirmed=确认参团）
  },

  data: {
    pillClass: '',
    percent: 0,
    nextState: null, // 状态机的下一站（终态时为 null）
    nextIsFinal: false, // 下一站是否终态：终态不可回退，确认弹窗要说清后果
    intentPhase: false, // 意向阶段（意向征集/谈判中）：报名只是登记兴趣，量词用「感兴趣」
    intentCount: 0, // 登记过意向、还没确认参团的人数（接龙中提醒团长）
    needConfirm: false, // 我登记过意向且已进入接龙中：给「确认参团」入口
    reviews: [],
    reviewRating: 0,
    reviewContent: '',
    submitting: false,
    submittingReview: false,
    // 成团后与团长互通手机号的意愿：报名前用开关选择，默认同意
    shareContact: true,
    // 「买前必懂」的就地解释：文案里命中术语的段可点，弹出对应决策卡
    pitchSegments: [],
    termRows: [],
    finalRows: [],
    activeTerm: null,
    aiChatEnabled: false, // AI 答疑开关，由 /options 下发
    rosterKeyword: '',
    filteredRoster: [],
  },

  lifetimes: {
    attached() {
      profile.getAiFeatures().then((ai) => this.setData({ aiChatEnabled: !!ai.chat }));
    },
  },

  observers: {
    matter(matter) {
      if (!matter) return;
      // 状态只能沿状态机推进一步（与后端守卫一致），算出下一站；终态时为 null（按钮隐藏）
      const states = stateOptions(matter.states);
      const stateIndex = states.findIndex((state) => state.value === matter.state);
      const nextState = stateIndex >= 0 ? states[stateIndex + 1] || null : null;
      // 头部计数口径跟着阶段走：意向期看兴趣热度，接龙后看确认人数，收场后是历史记录
      const confirmedPhase = matter.state === 'open' || matter.state === 'done';
      let headNoun = '感兴趣';
      if (confirmedPhase) {
        headNoun = '已确认参团';
      } else if (matter.state === 'aborted') {
        headNoun = '报名过';
      }
      const glossaryTerms = (matter.glossary || []).map((entry) => entry.term);
      this.setData(
        {
          pillClass: pillClass(matter.state),
          percent: joinPercent(matter),
          pitchSegments: splitByTerms(matter.body, glossaryTerms),
          termRows: (matter.terms || []).map((row) => ({
            label: row.label,
            segments: splitByTerms(row.value, glossaryTerms),
          })),
          finalRows: (matter.final_terms || []).map((row) => ({
            label: row.label,
            segments: splitByTerms(row.value, glossaryTerms),
          })),
          nextState,
          nextIsFinal: !!nextState && stateIndex + 2 === states.length,
          intentPhase: matter.state === 'seeking' || matter.state === 'negotiating',
          intentCount: Math.max(0, (matter.join_count || 0) - (matter.confirmed_count || 0)),
          headCount: confirmedPhase && matter.confirmed_count != null ? matter.confirmed_count : matter.join_count,
          headNoun,
          reviews: (matter.reviews || []).map((review) => ({ ...review, stars: starsOf(review.rating) })),
        },
        () => this.measureDock(),
      );
    },
    'matter.state, joined, myJoinStage': function (state, joined, myJoinStage) {
      this.setData({ needConfirm: !!joined && myJoinStage === 'intent' && state === 'open' }, () => this.measureDock());
    },
    myReview(myReview) {
      this.setData({
        reviewRating: (myReview && myReview.rating) || 0,
        reviewContent: (myReview && myReview.content) || '',
      });
    },
    'contactRoster, rosterKeyword': function (contactRoster, rosterKeyword) {
      const keyword = (rosterKeyword || '').trim().toLowerCase();
      this.setData({
        filteredRoster: (contactRoster || []).filter((row) => !keyword
          || row.name.toLowerCase().includes(keyword)
          || (row.phone || '').includes(keyword)
          || (row.leader_note || '').toLowerCase().includes(keyword)),
      });
    },
  },

  methods: {
    refresh() {
      this.triggerEvent('refresh');
    },

    onRosterSearch(event) {
      this.setData({ rosterKeyword: event.detail.value });
    },

    editParticipant(event) {
      const row = this.data.filteredRoster[event.currentTarget.dataset.index];
      wx.showModal({
        title: row.contact_status === 'contacted' ? '更新联系备注' : '标记已联系',
        editable: true,
        placeholderText: '例如：已进群，周三量房',
        content: row.leader_note || '',
        success: async ({ confirm, content }) => {
          if (!confirm) return;
          await matters.updateParticipant(this.data.matter.id, row.stance_id, {
            contact_status: 'contacted',
            leader_note: (content || '').trim(),
          });
          wx.showToast({ title: '已更新', icon: 'success' });
          this.refresh();
        },
      });
    },

    // 量出吸底操作条的实际高度上报给页面，让页面按需精确预留底部空间：
    // 操作条高度随状态变（互通开关行 / 确认参团双按钮），固定值要么遮内容要么留空白。
    measureDock() {
      this.createSelectorQuery()
        .select('.cta-dock')
        .boundingClientRect((rect) => {
          this.triggerEvent('dockmeasure', { height: rect ? Math.ceil(rect.height) : 0 });
        })
        .exec();
    },

    toggleJoin() {
      if (this.data.submitting) return;

      if (this.data.joined) {
        // 一键误触就掉出名单太伤，取消前确认
        wx.showModal({
          title: '取消报名？',
          content: '你会从接龙名单里移除，之后可以随时再报。',
          confirmText: '取消报名',
          cancelText: '再想想',
          success: ({ confirm }) => {
            if (confirm) this.doLeave();
          },
        });
        return;
      }

      // 共享意愿在页面开关里选好，弹窗只做最终确认，点「再想想」不会报名
      const { matter, intentPhase, shareContact } = this.data;
      const survey = matter.needs_survey;
      let content;
      if (survey && intentPhase) {
        // 逐人报价团：报名的意义是约商家单独出方案，联系互通从谈判中就开始
        content = shareContact
          ? '这是逐人报价的团购：进入谈判后，团长/商家会拿到你的手机号，联系你沟通需求，出你的专属方案（手机号只在你们双方之间可见）。'
          : '你选择了不互通手机号，商家没法主动联系你沟通需求、出方案，需要你主动联系团长对接。';
      } else {
        content = shareContact
          ? '成团后你的手机号将与团长互通（只在你和团长之间可见，不会公开展示），方便建群对接、安排上门。'
          : '你选择了不互通手机号，成团后建群、对接需要你主动联系团长。';
      }
      if (intentPhase && !survey) {
        content += '\n现在登记的是意向，条件谈出来进入接龙后，需要你回来确认参团才算数。';
      }
      let title = '确认参团';
      if (intentPhase) {
        title = survey ? '约商家出方案' : '登记意向';
      }
      wx.showModal({
        title,
        content,
        confirmText: intentPhase ? '登记' : '确认参团',
        cancelText: '再想想',
        success: ({ confirm }) => {
          if (confirm) this.doJoin(shareContact);
        },
      });
    },

    // 登记过意向的人在进入接龙后确认参团：升级承诺档位，计入成团人数
    confirmJoin() {
      if (this.data.submitting) return;
      wx.showModal({
        title: '确认参团？',
        content: '确认后你将计入成团人数，成团即按公示条件执行。',
        confirmText: '确认参团',
        cancelText: '再想想',
        success: async ({ confirm }) => {
          if (!confirm) return;
          this.setData({ submitting: true });
          try {
            await requestSubscribe();
            // 沿用我登记时的共享意愿，确认动作不悄悄改变隐私选择
            await matters.join(this.data.matter.id, this.properties.myShareContact);
            wx.showToast({ title: '已确认参团', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          } finally {
            this.setData({ submitting: false });
          }
        },
      });
    },

    onShareContactChange(event) {
      this.setData({ shareContact: event.detail.value });
    },

    async doJoin(shareContact) {
      this.setData({ submitting: true });
      try {
        // 报名的这一下顺手收一次订阅授权：之后谈判/成团/公示的动静才有额度可推
        await requestSubscribe();
        await matters.join(this.data.matter.id, shareContact);
        wx.showModal({
          title: this.data.intentPhase ? '已登记' : '已确认参团',
          content: this.data.intentPhase
            ? '你已经在名单里了。谈判结果和进度都会更新在本页，进入接龙后记得回来确认参团。'
            : '你已经在接龙名单里了。成团进展会更新在本页，有动静记得回来看看。',
          showCancel: false,
          confirmText: '好的',
        });
        this.refresh();
      } catch (error) {
        this.handleJoinError(error);
      } finally {
        this.setData({ submitting: false });
      }
    },

    async doLeave() {
      this.setData({ submitting: true });
      try {
        await matters.leave(this.data.matter.id);
        wx.showToast({ title: '已取消报名', icon: 'none' });
        this.refresh();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ submitting: false });
      }
    },

    // 业主没选楼栋号会被后端拦下（errors.profile）：引导去个人资料补全，回来即可报名
    handleJoinError(error) {
      if (!guardProfileError(error, '报名前请先在个人资料里选好楼栋号。')) {
        wx.showToast({ title: error.message, icon: 'none' });
      }
    },

    // 术语弹层里的追问：带着这个词自动向 AI 提问（宿主页面的半屏面板），业主不用打字
    askTermAi() {
      const { matter, activeTerm } = this.data;
      if (!activeTerm) return;
      const question = `「${activeTerm.term}」按我家的情况该怎么选？`;
      this.setData({ activeTerm: null });
      const pages = getCurrentPages();
      const page = pages[pages.length - 1];
      if (page && page.openAiChat) {
        page.openAiChat({ matterId: matter.id, matterTitle: matter.title, question });
      }
    },

    // 就地解释：点中文案里的术语，弹出「买前必懂」对应的决策卡
    showGlossary(event) {
      const { term } = event.currentTarget.dataset;
      if (!term) return;
      const entry = (this.data.matter.glossary || []).find((row) => row.term === term);
      if (entry) this.setData({ activeTerm: entry });
    },

    onTermPopupChange(event) {
      if (!event.detail.visible) this.setData({ activeTerm: null });
    },

    // 联系电话点击给两个动作：拨打（对齐商家名录的一键拨号）或复制（建群粘贴用）
    onPhoneTap(event) {
      const { phone } = event.currentTarget.dataset;
      wx.showActionSheet({
        itemList: [`拨打 ${phone}`, '复制号码'],
        success: ({ tapIndex }) => {
          if (tapIndex === 0) {
            wx.makePhoneCall({ phoneNumber: phone });
          } else {
            wx.setClipboardData({ data: phone });
          }
        },
      });
    },

    previewImage(event) {
      const { urls, current } = event.currentTarget.dataset;
      wx.previewImage({ urls, current });
    },

    // ---- 评价（仅参团业主，成团后）----

    onRateChange(event) {
      this.setData({ reviewRating: event.detail.value });
    },

    onReviewInput(event) {
      this.setData({ reviewContent: event.detail.value });
    },

    async submitReview() {
      const { reviewRating, reviewContent, submittingReview } = this.data;
      if (submittingReview) return;
      if (!reviewRating) return wx.showToast({ title: '请先打个分', icon: 'none' });

      this.setData({ submittingReview: true });
      try {
        await requestSubscribe();
        await matters.review(this.data.matter.id, reviewRating, reviewContent.trim());
        wx.showToast({ title: '评价已发布', icon: 'success' });
        this.refresh();
      } catch (error) {
        wx.showToast({ title: error.message, icon: 'none' });
      } finally {
        this.setData({ submittingReview: false });
      }
    },

    // ---- 以下仅发起人可见的操作 ----

    goEdit() {
      wx.navigateTo({ url: `/pages/matter-form/index?id=${this.data.matter.id}` });
    },

    goProgress() {
      wx.navigateTo({ url: `/pages/matter-update/index?id=${this.data.matter.id}` });
    },

    goDeal() {
      wx.navigateTo({ url: `/pages/groupbuy-deal/index?id=${this.data.matter.id}` });
    },

    // 状态只能推进到下一站（跳步/回退后端会拦）；进终态是不可逆动作，确认时把后果讲清楚
    flipState() {
      const { nextState, nextIsFinal } = this.data;
      if (!nextState) return;

      let content;
      if (nextIsFinal) {
        content = `「${nextState.label}」是最后一步：确认后与同意共享的参团者互通手机号、开放评价，且不能再改回来（弄错了需要联系管理员）。`;
      } else if (nextState.value === 'open') {
        // 两段表态：进接龙后意向要本人确认才算数，推进前给团长打好预防针
        content = `进入「${nextState.label}」后，登记过意向的邻居需要回来确认参团，确认的人数才计入成团目标。`;
      } else {
        content = `团购将从「${this.data.matter.state_label}」进入「${nextState.label}」，之后不能退回上一步。`;
      }

      wx.showModal({
        title: `进入「${nextState.label}」？`,
        content,
        confirmText: '确认推进',
        cancelText: '再想想',
        // 不可逆的最后一步用红色确认，让分量在弹窗上就能感知
        ...(nextIsFinal ? { confirmColor: '#e34d59' } : {}),
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.flipState(this.data.matter.id, nextState.value);
            wx.showToast({ title: '状态已更新', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 团购没走到底的收场出口（人数不够/谈判没谈成）：不触发联系互通与评价，也不可再改
    abortMatter() {
      const { matter } = this.data;
      if (!matter.abort_state) return;

      wx.showModal({
        title: `按「${matter.abort_state.label}」收场？`,
        content:
          '这个团购将关闭报名、封存名单，不开放评价和联系方式互通，且不能再改回来（弄错了需要联系管理员）。没谈成不丢人，给邻居一个交代比挂着强。',
        confirmText: '确认收场',
        cancelText: '再想想',
        confirmColor: '#e34d59',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.flipState(matter.id, matter.abort_state.value);
            wx.showToast({ title: '已收场', icon: 'none' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 成团后没共享联系方式的参团者：补开共享，与团长互见电话（双向对等）
    enableShare() {
      wx.showModal({
        title: '开启联系方式共享？',
        content: '开启后你和团长可互见手机号（只在你们双方之间可见，不会公开展示），方便进群对接。',
        confirmText: '开启共享',
        cancelText: '再想想',
        success: async ({ confirm }) => {
          if (!confirm) return;
          try {
            await matters.join(this.data.matter.id, true);
            wx.showToast({ title: '已开启共享', icon: 'success' });
            this.refresh();
          } catch (error) {
            wx.showToast({ title: error.message, icon: 'none' });
          }
        },
      });
    },

    // 相关方身份不参与接龙：去个人资料页切回业主身份
    goSwitchIdentity() {
      wx.showModal({
        title: '切回业主身份？',
        content: '切回后即可报名；相关方档案会保留，之后可在个人资料里再次切换。',
        confirmText: '切回报名',
        success: async ({ confirm }) => {
          if (!confirm) return;
          await unbindParty();
          this.refresh();
        },
      });
    },
  },
});
