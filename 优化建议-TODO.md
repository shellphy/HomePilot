# 天青府小程序 · 产品优化 TODO

> 来源:多角色(业主/商家/物业)场景演绎评估。每条为独立可认领单元,可拆到不同线程分别完成。
> 关键文件定位已附,新线程可直接上手。优先级:P0 最高 → P2 较低。

---

## TODO-1 【已结案 · 不实现】商家团长登记明细

**调研结论:原判断是误读,且不该加字段。**

1. **不是缺口**:admin 的 `registrations`(`MODE_REGISTER` + 问卷 `modules`)是**征集**工具,团购用的是 `MODE_JOIN`,那个接口对团购返回空。牵头人其实**已有**「参与管理 · 仅你可见」名单(`contact_roster`):姓名 + 报名者自愿共享的手机号 + 跟进备注,足够用电话对接。
2. **不加房号**:房号能定位到具体住处,隐私敏感度**高于手机号**(手机号能换、房号换不了),不应共享给牵头人。
3. 曾试加 `room_label` 已**全部回退**,代码回到合并 main 后的原状。

→ 本条无需任何改动。联系名单能力已足够,保持现状。

---

## 额外清理(本次已完成)· admin 只保留审核类功能

**背景**:排查 TODO-1 时发现 admin 下挂着 3 个「专用业务接口」,违反「admin 只该有审核类功能、没有任何专用业务入口」的架构原则,已全部删除:

- `GET /admin/matters/{matter}/registrations`(全量登记者 PII 明细)—— 删除。与授权模型冲突,发起者看逐人明细走 `census-consented`。
- `GET /admin/matters/{matter}/census-text` + `PUT .../census-summary`(文本题归纳发布)—— **整个功能删除**(经确认)。填空题不再进公示聚合。

**已删/改**:`CensusSummaryAdminController`(删文件);`MatterAdminController::registrations`(删方法);`routes/api.php`(删 3 路由);`CensusAggregator`(填空题不再进聚合);前端删 `pages/admin/registrations`、`pages/admin/census-text`,清 `utils/api/admin.js`、`matter-form`、`census-insights` 相关入口与主题展示;删/改相关测试。262 测试全绿、PHPStan 0 error。

admin 现在只剩:事项审核队列 + 通过/驳回、相关方认证、社区设置。

---

## TODO-2 【P1】补齐商家↔管理员的入驻握手闭环

**问题**:四处断裂 —— ①入口不可见(藏在 我的→个人资料→身份);②提交后无「审核中」状态页;③被拒无理由通道(接口只收 `is_listed` 布尔);④商家侧 403「请联系管理员」无任何联系入口。

**方案**:
1. 首页 / 商家名录加「商家入驻」CTA。
2. 商家提交后给「审核中」状态页(复用事项 review-banner 模式)。
3. `PartyAdminController@update` 增加「驳回 + 理由」,与事项审核对齐。
4. 商家侧 403 文案挂真实联系方式 / 一键通知管理员。

**关键文件**:
- `miniprogram/pages/profile-form/index.{js,wxml}`(当前唯一入驻入口,line 32-36 身份切换)
- `miniprogram/pages/party/index.wxml:7,35`(「待认证」状态目前只在 party 详情露出)
- `app/Http/Controllers/Api/Admin/PartyAdminController.php:48-60`(只收 `is_listed`,需加驳回)
- `app/Http/Controllers/Api/PartyController.php:113-165`(`store`)
- `MatterController.php:230`(403「商家发起需先由管理员认证,请联系管理员」)

---

## TODO-3 【P1】首页信息流加类型筛选 + 露出问卷入口 + tab 红点

**问题**:600 户社区,首页是无筛选/无搜索/无分类的一条竖列;问卷被刻意排除在首页外,只活在「数据」tab,很多人永远发现不了;只有「我的」tab 有红点,新事项/新问卷零提醒。

**方案**:
1. 信息流顶部加类型 tab/chip(团购/活动/互助/维权)。
2. 首页给「正在征集」一个轻量露出卡(点进「数据」tab),别让问卷彻底隐身。
3. 给「小区」「数据」tab 加「有新内容」红点。

**关键文件**:
- `miniprogram/pages/community/index.{js,wxml}`(信息流,line 34-46;line 58-59 注释「census 不进小区流」)
- `miniprogram/pages/insights/index.{js,wxml}`(数据 tab,征集列表)
- `miniprogram/custom-tab-bar/index.{js,wxml}`(目前仅 my 有 badge,line 26-35)

---

## TODO-4 【P1】把「让发起者看到我的问卷」授权做重、做清楚

**问题**:一勾即把手机号 + 全部逐题答案交给指定商家,却在填完 32 题后紧挨「完成」按钮冒出来,还是非原生自定义勾选框,极易被无意识点过。公益工具的信任底线。

**方案**:
1. 换原生 checkbox 或加二次确认弹窗,明确复述「谁能看到什么」。
2. 考虑把授权从「填问卷时」挪到「查看/分享报告时」再做(冷静态决定)。
3. 长期:字段级授权(如只给预算、不给电话)。

**关键文件**:
- `miniprogram/pages/census-form/index.wxml:82-89`(`.consent-box` 自定义勾选,末模块才出现)
- `miniprogram/pages/census-form/index.js:22-23,125-128,190-194`(`my_visible_to_initiator` 逻辑)
- `miniprogram/pages/census-report/index.{js,wxml}`(可作为「查看/分享时授权」的新落点)
- `app/Http/Controllers/Api/CensusController.php:140-160`(`consented`,后端授权收窄)

---

## TODO-5 【P1】小程序内管理员授权入口(替代纯 CLI)

**问题**:成为/移除管理员 100% 靠 `admin:grant` CLI,物业无法自助,每次是开发工单,且无审计。

**方案**:做一个「超级管理员」种子账号,在「社区设置」里提供管理员 邀请/移交/移除 + 授权记录。

**关键文件**:
- `app/Console/Commands/GrantAdmin.php`(现有 CLI,line 12/18-27)
- `routes/api.php:85-96`(`admin` 中间件路由组)
- `miniprogram/pages/admin/settings/index.{js,wxml}`(设置页,可加管理员管理入口)
- `app/Http/Controllers/Api/Admin/SettingAdminController.php`

---

## TODO-6 【P2】统一表单保存反馈,消除「自动保存」错觉

**问题**:个人资料头像/手机号即时存、其余按钮存,用户改头像看到「已更新」以为全页自动存,漏保存楼栋号。房号是否必填也不明确。

**方案**:二选一 —— 全页统一「点保存才生效」(头像纳入),或全字段即时存;别混用。顺带明确房号必填规则。

**关键文件**:
- `miniprogram/pages/profile-form/index.js:80-89`(头像即时存)、`:98-105`(手机号即时存)、`:200-213`(保存按钮)
- `miniprogram/pages/profile-form/index.wxml`(头像 line 5-10;楼栋号 line 42 必填;房号 line 54-57 未必填)
- `miniprogram/behaviors/dirty.js`(离开拦截)

---

## TODO-7 【P2】高犹豫场景(维权联名/互助)开放「问一句」出口

**问题**:维权联名/互助恰是业主最想问一句再决定的场景,却既无「问AI」也无「大家都在问」,且无任何说明,静默留白。

**方案**:至少给这些类型开放「大家都在问」(可不接 AI),让邻居能问、能「同问」聚合诉求;若刻意不开,页面给一句解释而非空白。

**关键文件**:
- `miniprogram/pages/matter/index.wxml:38-42`(qa-panel 目前只对 groupbuy/activity 开)、`:47-53`(ai 按钮同样受限)
- `miniprogram/components/qa-panel/*`
- `app/Http/Controllers/Api/MatterQuestionController.php`(问答权限)

---

## 次要观察(暂不单列,可择机并入上面条目)

- 团购「登记意向→确认参团」两段式共享有 5 种按钮文案 + 反复确认,业主难分清是否占上名额。(`components/groupbuy-detail/index.wxml:273-315`)
- 点 census 事项 `redirectTo` 到 insights 页,返回栈丢失。(`miniprogram/pages/matter/index.js:95-98`)
- 数据 tab 无全局空态;聚合柱状图按最高项归一(100%≠过半)易误读。(`miniprogram/pages/census-insights/index.js:6-14`)
- 首页 slogan 未配时渲染成光秃秃「 · 」。(`miniprogram/pages/community/index.wxml:4-6`)
