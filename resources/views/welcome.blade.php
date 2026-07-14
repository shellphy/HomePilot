<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>知行合一</title>
    <meta name="description" content="知行合一 —— 让知识回归行动。">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --bg: #0f1f17;
            --bg-soft: #14291e;
            --accent: #2f9e6f;
            --accent-soft: #6fd3a5;
            --text: #e8f1eb;
            --muted: #9db3a7;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(47, 158, 111, 0.25), transparent 60%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg-soft) 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 24px;
        }
        .logo {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-soft));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            font-weight: 700;
            color: #0f1f17;
            margin-bottom: 28px;
            box-shadow: 0 12px 40px rgba(47, 158, 111, 0.35);
        }
        h1 {
            font-size: clamp(38px, 8vw, 68px);
            letter-spacing: 0.15em;
            font-weight: 700;
        }
        .subtitle {
            margin-top: 18px;
            font-size: clamp(15px, 3.5vw, 19px);
            color: var(--muted);
            letter-spacing: 0.05em;
        }
        .tagline {
            margin-top: 40px;
            max-width: 560px;
            line-height: 1.9;
            color: var(--muted);
            font-size: 15px;
        }
        .status {
            margin-top: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 999px;
            background: rgba(47, 158, 111, 0.12);
            border: 1px solid rgba(111, 211, 165, 0.25);
            color: var(--accent-soft);
            font-size: 14px;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-soft);
            box-shadow: 0 0 0 0 rgba(111, 211, 165, 0.6);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(111, 211, 165, 0.5); }
            70% { box-shadow: 0 0 0 10px rgba(111, 211, 165, 0); }
            100% { box-shadow: 0 0 0 0 rgba(111, 211, 165, 0); }
        }
        footer {
            padding: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.8;
        }
        footer a { color: var(--muted); text-decoration: none; }
        footer a:hover { color: var(--accent-soft); }
    </style>
</head>
<body>
    <main>
        <div class="logo">知</div>
        <h1>知行合一</h1>
        <p class="subtitle">让知识回归行动</p>
        <p class="tagline">
            知者行之始，行者知之成。<br>
            我们相信真正的理解源于实践，一点一滴的行动汇聚成改变。<br>
            网站正在建设中，敬请期待。
        </p>
        <div class="status">
            <span class="dot"></span>
            网站建设中
        </div>
    </main>
    <footer>
        <div>&copy; {{ date('Y') }} 知行合一 · 保留所有权利</div>
        <div><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">备案号审核中</a></div>
    </footer>
</body>
</html>
