<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>知行合一 —— 我的读书与思考笔记</title>
    <meta name="description" content="知行合一,我的个人网站。在这里记录读过的书、想通的道理,以及把想法落到行动上的点滴。">
    <meta name="keywords" content="知行合一,个人博客,读书笔记,学习记录,随笔,自我成长">
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
                <a href="#notes">在记什么</a>
                <a href="#about">关于</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="wrap">
                <div class="logo">知</div>
                <h1>知行合一</h1>
                <p class="subtitle">我的读书与思考笔记</p>
                <p class="lead">
                    这是我的个人网站。我在这里记录读过的书、想通的道理,
                    也提醒自己:懂了的道理,要真的去做,才算真懂。
                </p>
                <a class="cta" href="#about">关于这个小站 →</a>
            </div>
        </section>

        <section class="creed">
            <div class="wrap">
                <div>
                    <div class="num">知</div>
                    <div class="lbl">多读书,独立想</div>
                </div>
                <div>
                    <div class="num">行</div>
                    <div class="lbl">动手做,常复盘</div>
                </div>
                <div>
                    <div class="num">合</div>
                    <div class="lbl">知行相长,日拱一卒</div>
                </div>
            </div>
        </section>

        <section id="philosophy">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">Idea</div>
                    <h2>为什么叫「知行合一」</h2>
                    <p>对我来说,知和行从来不是两件事。道理只有落到行动上,才真正长在自己身上。</p>
                </div>
                <div class="grid">
                    <div class="card">
                        <div class="icon">📖</div>
                        <h3>以知启行</h3>
                        <p>从一本书、一个概念出发,把零散的信息整理成能指导自己行动的认知。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🌱</div>
                        <h3>以行验知</h3>
                        <p>再好的道理也要拿到生活里试一试。动手去做,用结果反过来修正自己的判断。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🔁</div>
                        <h3>知行相长</h3>
                        <p>读、做、复盘,循环往复。一点一滴慢慢积累,时间会给出答案。</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="notes">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">Notes</div>
                    <h2>我在这里记些什么</h2>
                    <p>都是些自己的记录,不成体系,慢慢写、慢慢补。</p>
                </div>
                <div class="grid">
                    <div class="card">
                        <div class="icon">✍️</div>
                        <h3>读书笔记</h3>
                        <p>读过觉得有意思的书,摘一点、想一点,写下当时的理解和疑问。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🗂️</div>
                        <h3>学习记录</h3>
                        <p>学新东西时的整理和总结,方便自己以后回头翻,也算给记忆留个备份。</p>
                    </div>
                    <div class="card">
                        <div class="icon">🌤️</div>
                        <h3>日常随笔</h3>
                        <p>一些生活里的小感想、想通的小道理,随手记下来,提醒自己别只想不做。</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="about">
            <div class="wrap">
                <div class="section-head">
                    <div class="eyebrow">About</div>
                    <h2>关于这个小站</h2>
                </div>
                <blockquote>
                    「知者行之始,<span>行者知之成</span>。」
                </blockquote>
                <cite>—— 王阳明《传习录》</cite>
                <p class="text">
                    「知行合一」出自明代思想家王阳明。他认为,知而不行,只是未知;真正的知,
                    必然包含行动。我拿它给自己的小站命名,是想经常提醒自己:别做「思想上的巨人、行动上的矮子」。
                </p>
                <p class="text">
                    这里是我个人搭的一个小网站,纯粹出于兴趣,记录自己读书、学习和思考的点滴,
                    不做商业用途。网站还很简陋,我会一点点把它补充起来。谢谢你顺路来看看。
                </p>
            </div>
        </section>
    </main>

    <footer>
        <div class="wrap">
            <div class="status"><span class="dot"></span>网站持续更新中</div>
            <div>&copy; {{ date('Y') }} 知行合一 · 个人网站</div>
            <div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">鄂ICP备2023000342号-1</a></div>
        </div>
    </footer>
</body>
</html>
