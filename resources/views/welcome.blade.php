<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>知行合一 —— 让知识回归行动</title>
    <meta name="description" content="知行合一致力于连接知识与实践，通过阅读、思考与行动，陪伴每个人把所学变成所行。">
    <meta name="keywords" content="知行合一,学习成长,知识管理,读书笔记,实践,自我提升">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --bg: #0f1f17;
            --bg-soft: #14291e;
            --panel: rgba(255, 255, 255, 0.035);
            --border: rgba(111, 211, 165, 0.16);
            --accent: #2f9e6f;
            --accent-soft: #6fd3a5;
            --text: #e8f1eb;
            --muted: #9db3a7;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(47, 158, 111, 0.22), transparent 60%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg-soft) 100%);
            background-attachment: fixed;
            min-height: 100vh;
            line-height: 1.7;
        }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 24px; }

        /* 顶部导航 */
        header {
            position: sticky; top: 0; z-index: 10;
            backdrop-filter: blur(12px);
            background: rgba(15, 31, 23, 0.72);
            border-bottom: 1px solid var(--border);
        }
        .nav { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 700; letter-spacing: 0.08em; }
        .brand .mark {
            width: 34px; height: 34px; border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-soft));
            display: flex; align-items: center; justify-content: center;
            color: #0f1f17; font-size: 18px; font-weight: 700;
        }
        .nav-links { display: flex; gap: 28px; font-size: 14px; color: var(--muted); }
        .nav-links a:hover { color: var(--accent-soft); }
        @media (max-width: 640px) { .nav-links { display: none; } }

        /* Hero */
        .hero { text-align: center; padding: 96px 0 72px; }
        .hero .logo {
            width: 76px; height: 76px; border-radius: 22px; margin: 0 auto 30px;
            background: linear-gradient(135deg, var(--accent), var(--accent-soft));
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; font-weight: 700; color: #0f1f17;
            box-shadow: 0 14px 44px rgba(47, 158, 111, 0.35);
        }
        .hero h1 { font-size: clamp(40px, 8vw, 72px); letter-spacing: 0.16em; font-weight: 700; }
        .hero .subtitle { margin-top: 18px; font-size: clamp(16px, 3.5vw, 20px); color: var(--accent-soft); letter-spacing: 0.06em; }
        .hero .lead { margin: 28px auto 0; max-width: 600px; color: var(--muted); font-size: 16px; }
        .cta { margin-top: 38px; display: inline-flex; gap: 8px; align-items: center;
            padding: 12px 28px; border-radius: 999px; font-size: 15px; font-weight: 600;
            color: #0f1f17; background: linear-gradient(135deg, var(--accent), var(--accent-soft));
            box-shadow: 0 10px 30px rgba(47, 158, 111, 0.3); transition: transform 0.15s ease; }
        .cta:hover { transform: translateY(-2px); }

        /* 通用区块 */
        section { padding: 64px 0; }
        .section-head { text-align: center; margin-bottom: 44px; }
        .section-head .eyebrow { font-size: 13px; letter-spacing: 0.22em; text-transform: uppercase; color: var(--accent-soft); }
        .section-head h2 { margin-top: 12px; font-size: clamp(26px, 5vw, 34px); letter-spacing: 0.06em; }
        .section-head p { margin: 14px auto 0; max-width: 560px; color: var(--muted); font-size: 15px; }

        /* 卡片网格 */
        .grid { display: grid; gap: 20px; grid-template-columns: repeat(3, 1fr); }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
        .card {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 18px; padding: 30px 26px; transition: border-color 0.2s ease, transform 0.2s ease;
        }
        .card:hover { border-color: rgba(111, 211, 165, 0.4); transform: translateY(-3px); }
        .card .icon {
            width: 46px; height: 46px; border-radius: 13px; margin-bottom: 18px;
            display: flex; align-items: center; justify-content: center; font-size: 22px;
            background: rgba(47, 158, 111, 0.14); border: 1px solid var(--border);
        }
        .card h3 { font-size: 19px; letter-spacing: 0.03em; }
        .card p { margin-top: 10px; color: var(--muted); font-size: 14.5px; }

        /* 理念条 */
        .creed { border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
        .creed .wrap { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; text-align: center; }
        @media (max-width: 640px) { .creed .wrap { grid-template-columns: 1fr; gap: 32px; } }
        .creed .num { font-size: 40px; font-weight: 700; color: var(--accent-soft); letter-spacing: 0.04em; }
        .creed .lbl { margin-top: 6px; color: var(--muted); font-size: 14px; }

        /* 关于 */
        .about .wrap { max-width: 720px; text-align: center; }
        .about blockquote { font-size: clamp(20px, 4vw, 26px); line-height: 1.9; letter-spacing: 0.04em; }
        .about blockquote span { color: var(--accent-soft); }
        .about cite { display: block; margin-top: 22px; color: var(--muted); font-style: normal; font-size: 14px; }
        .about .text { margin-top: 34px; color: var(--muted); font-size: 15.5px; text-align: left; }

        /* 联系 */
        .contact .wrap { max-width: 720px; }
        .contact-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        @media (max-width: 640px) { .contact-grid { grid-template-columns: 1fr; } }
        .contact-item { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; }
        .contact-item .k { font-size: 13px; color: var(--muted); letter-spacing: 0.08em; }
        .contact-item .v { margin-top: 6px; font-size: 16px; }

        /* 页脚 */
        footer { border-top: 1px solid var(--border); padding: 34px 0 40px; text-align: center; color: var(--muted); font-size: 13px; line-height: 2; }
        footer a:hover { color: var(--accent-soft); }
        .status { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 18px;
            padding: 8px 18px; border-radius: 999px; background: rgba(47, 158, 111, 0.1);
            border: 1px solid var(--border); color: var(--accent-soft); font-size: 13px; }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent-soft); animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(111, 211, 165, 0.5); }
            70% { box-shadow: 0 0 0 9px rgba(111, 211, 165, 0); }
            100% { box-shadow: 0 0 0 0 rgba(111, 211, 165, 0); }
        }
    </style>
</head>
<body>
    <header>
        <div class="wrap nav">
            <div class="brand"><span class="mark">知</span>知行合一</div>
            <nav class="nav-links">
                <a href="#philosophy">理念</a>
                <a href="#features">我们在做</a>
                <a href="#about">关于</a>
                <a href="#contact">联系</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap">
                <div class="logo">知</div>
                <h1>知行合一</h1>
                <p class="subtitle">让知识回归行动</p>
                <p class="lead">
                    我们相信，读过的书、想通的道理，只有落到行动上才真正属于自己。
                    知行合一，陪你把「知道」变成「做到」。
                </p>
                <a class="cta" href="#features">了解我们在做什么 →</a>
            </div>
        </section>

        <section class="creed">
            <div class="wrap">
                <div>
                    <div class="num">知</div>
                    <div class="lbl">广泛阅读，独立思考</div>
                </div>
                <div>
                    <div class="num">行</div>
                    <div class="lbl">动手实践，持续复盘</div>
                </div>
                <div>
                    <div class="num">合</div>
                    <div class="lbl">知行相长，日拱一卒</div>
                </div>
            </div>
        </section>

        <section id="philosophy">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">Our Philosophy</div>
                    <h2>知是行之始，行是知之成</h2>
                    <p>知与行从来不是两件事。真正的理解诞生于实践，而每一次行动又反过来加深理解。</p>
                </div>
                <div class="grid">
                    <div class="card">
                        <div class="icon">📖</div>
                        <h3>以知启行</h3>
                        <p>从一本书、一个概念、一次思考出发，把碎片化的信息整理成能指导行动的认知。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🌱</div>
                        <h3>以行验知</h3>
                        <p>再好的道理都要在真实世界里被检验。动手去做，用结果反馈修正自己的判断。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🔁</div>
                        <h3>知行相长</h3>
                        <p>读—做—复盘，形成正向循环。一点一滴的积累，最终汇聚成实实在在的改变。</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="features">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">What We Do</div>
                    <h2>我们在做的事</h2>
                    <p>围绕「学以致用」，我们希望做一些帮助个人成长的小工具与内容。</p>
                </div>
                <div class="grid">
                    <div class="card">
                        <div class="icon">✍️</div>
                        <h3>读书与思考</h3>
                        <p>分享读书笔记、思维方法与优质内容，让好的观点更容易被看见、被理解。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🗂️</div>
                        <h3>知识整理</h3>
                        <p>帮助你把零散的所学沉淀下来，建立属于自己的知识体系，随时可回顾、可调用。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🎯</div>
                        <h3>行动陪伴</h3>
                        <p>把目标拆成可执行的小步骤，记录进展、坚持复盘，让改变真正发生。</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">About</div>
                    <h2>关于知行合一</h2>
                </div>
                <blockquote>
                    「知者行之始，<span>行者知之成</span>。」
                </blockquote>
                <cite>—— 王阳明《传习录》</cite>
                <p class="text">
                    「知行合一」出自明代思想家王阳明。他认为，知而不行，只是未知；真正的知，
                    必然包含行动。我们把这句古老的箴言作为名字，是想提醒自己：不做「思想上的巨人、行动上的矮子」。
                </p>
                <p class="text">
                    这个网站还很年轻，我们会一点点把它做起来——先从记录、分享与陪伴做起，
                    和每一位愿意「知行合一」的朋友一起成长。感谢你的到来。
                </p>
            </div>
        </section>

        <section id="contact" class="contact">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">Contact</div>
                    <h2>联系我们</h2>
                    <p>有任何想法、建议或合作意向，欢迎与我们取得联系。</p>
                </div>
                <div class="contact-grid">
                    <div class="contact-item">
                        <div class="k">邮箱</div>
                        <div class="v">hello@example.com</div>
                    </div>
                    <div class="contact-item">
                        <div class="k">合作 / 反馈</div>
                        <div class="v">欢迎来信，我们会尽快回复</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <div class="status"><span class="dot"></span>网站持续建设中</div>
            <div>&copy; {{ date('Y') }} 知行合一 · 保留所有权利</div>
            <div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">备案号审核中</a></div>
        </div>
    </footer>
</body>
</html>
