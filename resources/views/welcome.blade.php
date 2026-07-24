<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>武汉润予科技有限公司 — 软件定制开发服务商</title>
    <meta name="description" content="武汉润予科技有限公司，专注小程序、APP、Web 与企业系统的定制开发与软件外包服务。">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        :root {
            --bg: #ffffff;
            --fg: #1a1d21;
            --muted: #5b6470;
            --line: #e7eaee;
            --brand: #17795e;
            --brand-soft: #eef6f2;
            --radius: 14px;
            --maxw: 1080px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: var(--fg);
            background: var(--bg);
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: var(--maxw); margin: 0 auto; padding: 0 24px; }

        /* Header */
        header {
            position: sticky; top: 0; z-index: 10;
            background: rgba(255,255,255,.86);
            backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid var(--line);
        }
        .nav { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; }
        .logo {
            width: 32px; height: 32px; border-radius: 8px;
            background: linear-gradient(135deg, var(--brand), #2fae86);
            display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 15px;
        }
        .nav-links { display: flex; gap: 28px; font-size: 15px; color: var(--muted); }
        .nav-links a:hover { color: var(--fg); }

        /* Hero */
        .hero { padding: 96px 0 72px; }
        .eyebrow {
            display: inline-block; font-size: 13px; letter-spacing: .5px; color: var(--brand);
            background: var(--brand-soft); padding: 6px 12px; border-radius: 999px; margin-bottom: 22px;
        }
        .hero h1 { font-size: 44px; line-height: 1.25; font-weight: 800; letter-spacing: -.5px; max-width: 16em; }
        .hero p { margin-top: 20px; font-size: 18px; color: var(--muted); max-width: 34em; }
        .cta { margin-top: 34px; display: flex; gap: 14px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600;
            padding: 12px 22px; border-radius: 10px; transition: .18s;
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: #12654d; }
        .btn-ghost { border: 1px solid var(--line); color: var(--fg); }
        .btn-ghost:hover { border-color: var(--brand); color: var(--brand); }

        /* Sections */
        section { padding: 64px 0; }
        .section-head { max-width: 40em; margin-bottom: 44px; }
        .section-head h2 { font-size: 30px; font-weight: 800; letter-spacing: -.3px; }
        .section-head p { margin-top: 12px; color: var(--muted); font-size: 16px; }

        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card {
            border: 1px solid var(--line); border-radius: var(--radius); padding: 28px 26px;
            transition: .2s; background: #fff;
        }
        .card:hover { border-color: var(--brand); transform: translateY(-3px); box-shadow: 0 12px 30px -18px rgba(23,121,94,.4); }
        .card .ic {
            width: 44px; height: 44px; border-radius: 11px; background: var(--brand-soft);
            display: grid; place-items: center; margin-bottom: 18px; font-size: 22px;
        }
        .card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .card p { font-size: 14.5px; color: var(--muted); }

        /* Stats */
        .stats { background: var(--brand-soft); border-radius: 20px; padding: 44px 32px; }
        .stats .grid { grid-template-columns: repeat(4, 1fr); gap: 16px; text-align: center; }
        .stat .num { font-size: 34px; font-weight: 800; color: var(--brand); }
        .stat .lbl { font-size: 14px; color: var(--muted); margin-top: 4px; }

        /* About */
        .about { display: grid; grid-template-columns: 1.1fr 1fr; gap: 48px; align-items: center; }
        .about h2 { font-size: 30px; font-weight: 800; margin-bottom: 18px; }
        .about p { color: var(--muted); margin-bottom: 14px; }
        .about ul { list-style: none; margin-top: 20px; display: grid; gap: 12px; }
        .about li { display: flex; gap: 10px; font-size: 15px; }
        .about li::before { content: "✓"; color: var(--brand); font-weight: 800; }
        .about-visual {
            aspect-ratio: 4/3; border-radius: 20px;
            background: linear-gradient(135deg, #17795e, #2fae86 70%, #7fd0b6);
            display: grid; place-items: center; color: #fff;
        }
        .about-visual span { font-size: 22px; font-weight: 800; letter-spacing: 2px; opacity: .95; }

        /* Contact */
        .contact { text-align: center; background: #101418; color: #fff; border-radius: 20px; padding: 60px 32px; }
        .contact h2 { font-size: 30px; font-weight: 800; }
        .contact p { color: #9aa4af; margin-top: 12px; font-size: 16px; }
        .contact .lines { margin-top: 28px; display: inline-flex; flex-direction: column; gap: 8px; font-size: 15px; }
        .contact .lines b { color: #fff; font-weight: 600; }
        .contact .lines span { color: #c3ccd4; }

        /* Footer */
        footer { border-top: 1px solid var(--line); padding: 34px 0; margin-top: 24px; }
        .foot { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 13.5px; color: var(--muted); }
        .foot a:hover { color: var(--brand); }

        @media (max-width: 820px) {
            .nav-links { display: none; }
            .hero { padding: 64px 0 48px; }
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .grid, .stats .grid { grid-template-columns: 1fr 1fr; }
            .about { grid-template-columns: 1fr; }
            .about-visual { order: -1; }
        }
        @media (max-width: 520px) {
            .grid, .stats .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <div class="wrap nav">
            <div class="brand">
                <div class="logo">润</div>
                <span>润予科技</span>
            </div>
            <nav class="nav-links">
                <a href="#services">服务</a>
                <a href="#about">关于我们</a>
                <a href="#contact">联系我们</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap">
                <span class="eyebrow">软件定制开发 · 技术外包服务</span>
                <h1>用可靠的技术，把你的想法做成产品</h1>
                <p>武汉润予科技有限公司专注于小程序、移动应用、Web 系统与企业信息化的定制开发，为客户提供从需求梳理、设计研发到上线运维的一站式软件外包服务。</p>
                <div class="cta">
                    <a class="btn btn-primary" href="#contact">咨询合作</a>
                    <a class="btn btn-ghost" href="#services">了解服务</a>
                </div>
            </div>
        </section>

        <section id="services">
            <div class="wrap">
                <div class="section-head">
                    <h2>我们能做什么</h2>
                    <p>覆盖主流技术栈，按项目制或人力外包灵活合作，交付即用。</p>
                </div>
                <div class="grid">
                    <div class="card">
                        <div class="ic">📱</div>
                        <h3>微信小程序开发</h3>
                        <p>电商、社区、工具类小程序定制，涵盖设计、开发、审核上线与后续迭代。</p>
                    </div>
                    <div class="card">
                        <div class="ic">💻</div>
                        <h3>移动应用开发</h3>
                        <p>iOS / Android 原生与跨平台应用开发，兼顾体验与性能。</p>
                    </div>
                    <div class="card">
                        <div class="ic">🌐</div>
                        <h3>Web 与后台系统</h3>
                        <p>企业官网、管理后台、业务中台的前后端一体化开发。</p>
                    </div>
                    <div class="card">
                        <div class="ic">🏢</div>
                        <h3>企业信息化定制</h3>
                        <p>结合业务流程定制 OA、CRM、进销存等信息化系统。</p>
                    </div>
                    <div class="card">
                        <div class="ic">🔌</div>
                        <h3>接口与系统集成</h3>
                        <p>第三方平台对接、支付集成、数据同步与系统迁移。</p>
                    </div>
                    <div class="card">
                        <div class="ic">🛠️</div>
                        <h3>技术人力外包</h3>
                        <p>提供前端、后端、测试等专业人才，驻场或远程支持项目交付。</p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="wrap">
                <div class="stats">
                    <div class="grid">
                        <div class="stat"><div class="num">50+</div><div class="lbl">交付项目</div></div>
                        <div class="stat"><div class="num">8年</div><div class="lbl">行业经验</div></div>
                        <div class="stat"><div class="num">30+</div><div class="lbl">合作客户</div></div>
                        <div class="stat"><div class="num">98%</div><div class="lbl">按期交付率</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="about">
            <div class="wrap about">
                <div>
                    <h2>关于润予科技</h2>
                    <p>武汉润予科技有限公司是一家专注于软件定制开发的技术服务商，团队成员来自互联网一线，拥有丰富的产品研发与项目管理经验。</p>
                    <p>我们相信好的软件源于对业务的理解与对细节的打磨，坚持以清晰的沟通、规范的流程和可维护的代码，帮助客户稳步落地每一个项目。</p>
                    <ul>
                        <li>专属项目经理，需求响应及时</li>
                        <li>规范开发流程，代码可交付可维护</li>
                        <li>上线后持续维护，长期技术保障</li>
                    </ul>
                </div>
                <div class="about-visual"><span>RUNYU TECH</span></div>
            </div>
        </section>

        <section id="contact">
            <div class="wrap">
                <div class="contact">
                    <h2>开始你的项目</h2>
                    <p>无论是从零开发还是已有系统的升级维护，欢迎与我们联系。</p>
                    <div class="lines">
                        <div><b>公司名称：</b><span>武汉润予科技有限公司</span></div>
                        <div><b>商务邮箱：</b><span>hi@runforyou.app</span></div>
                        <div><b>公司地址：</b><span>湖北省武汉市</span></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap foot">
            <div>© 2026 武汉润予科技有限公司　保留所有权利</div>
            <div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">鄂ICP备2026037231号-2</a></div>
        </div>
    </footer>
</body>
</html>
