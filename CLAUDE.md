# HomePilot

本小区（业主群 100+ 户）的装修团购小程序。核心：团长（项目所有者本人）公开发起团购，业主报名接龙、登记装修意向，登记数据汇总成"小区装修进度地图"。

**产品事实来源**：`HomePilot产品定义.md`（V0.3）。改需求先改它。
**交互原型**：`原型v0.3.html`（五屏，浏览器直接打开），页面样式和文案以它为基准。

## 目录分工

| 目录 | 作用 |
|---|---|
| `backend/` | Laravel 13 + Filament 5。对小程序提供 API。**人人可发起团购，发起人即该团团长**（projects.initiator_id），发起/编辑/发进度都在小程序内完成，权限=发起人本人；**Filament 负责管理员审核上架（is_approved）**、看登记/问卷明细、报表兜底。进阶问卷题库在 config/homepilot.php 的 survey 键，改题不发版。数据库 SQLite。另有自己的 CLAUDE.md（laravel/boost 生成） |
| `miniprogram/` | 原生微信小程序（官方空模板起步），**唯一的开发目标** |
| `miniprogram-template-components/` | TDesign 组件示例，**只读参考，禁止修改** |
| `miniprogram-template-demo/` | tdesign-miniprogram-starter 完整示例，**只读参考，禁止修改**（页面结构、custom-tab-bar、请求封装的写法都参照它） |

## 铁律（永久有效）

1. **小程序不碰钱**：不代收款、不担保交易，报名仅为意向登记，签约付款业主直接对商家。
2. 管理端只给团长用，业主永远不进 Filament；业主端一律微信登录（openid）。
3. 不做：评论区、社区、商城、商家入驻、AI 画像问卷。
4. 所有面向业主的文案：中文、口语化、不用行业黑话（"半包"这类术语必须配大白话解释）。

## 技术要点

- 业主 = `residents` 表（openid 识别）；`users` 表只放 Filament 管理员（团长）。
- 小程序登录：code2session，本地/测试环境用 `Http::fake` / 假驱动，不真调微信。
- 小程序 UI 用 tdesign-miniprogram，用法先查两个 template 目录再查文档。
- 业务逻辑尽量抽成纯函数/服务类，方便测试。

## 常用命令

```bash
cd backend && php artisan test        # Pest 测试
cd backend && vendor/bin/pint --dirty # 代码格式化（提交前跑）
cd backend && vendor/bin/phpstan      # 静态分析
cd backend && php artisan serve --host=0.0.0.0   # 本地起 API，必须带 --host（否则可能只监听 IPv6，wx.uploadFile 走 IPv4 会连不上）；工具里勾"不校验合法域名"
```

小程序只能在微信开发者工具里预览；改完小程序代码后提醒用户在工具里刷新验证。

## 部署（尚未进行）

腾讯云轻量 2核2G4M（Debian 13）+ 已备案域名 + HTTPS。功能成型后一次性部署。
