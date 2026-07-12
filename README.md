# HomePilot · 天青府家园

服务单个小区（招商天青府，约 600 户）的微信小程序——一个**小区数字公共空间**，把业主群里散落的表态沉淀成可行动、可追溯的集体事实。装修团购是它跑通的第一个场景。

## 核心模型

一切皆**事务（Matter）**，业主通过**表态**参与，结果**公示**沉淀：

- **事务类型**：团购 / 活动 / 互助拼车 / 维权联名 / 公告 / 征集摸底
- **表态模式**：登记（事前）· 接龙（事中承诺）· 评价（事后）
- **身份**：户是原子，人通过与户的关系获得身份；相关方（商家/物业等）经管理员认证后公示。身份只影响展示，不是参与门槛
- **公开团长模式**：人人可发起，发起人即团长，管理员审核后公示
- **AI 答疑**：在事务详情里就地提问，大模型结合小区上下文作答

新场景 = 事务类型 + 表态模式 + 公示模板的组合，尽量配置而非开发。

## 技术栈

- **前端**：原生微信小程序 + TDesign（`miniprogram/`），管理端也在小程序内
- **后端**：Laravel 13 + Sanctum，SQLite（`app/` `routes/` `database/`）
- **AI**：laravel/ai（DeepSeek）
- **测试**：Pest（后端为主战场；小程序界面需在微信开发者工具人工验证）

## 目录

```
miniprogram/    微信小程序（pages/ components/ utils/）
app/            Laravel 领域模型与控制器（Matters/ Models/ Http/）
routes/api.php  小程序调用的 API
database/       迁移与种子
tests/          Pest 测试
```

## 本地开发

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev          # 起后端 + vite
php artisan test          # 跑测试
```

小程序端用微信开发者工具打开 `miniprogram/`，在 `.env` 配好 `WECHAT_APPID/SECRET`，工具里勾选「不校验合法域名」联调。
