<?php
// ✅ Standalone index.php - Dark, Tiny UI, Draggable Menu
$pageTitle = "AkkuApps.in 🚀";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        :root {
            --bg: #08080c;
            --card: #0f0f14;
            --border: #1a1a22;
            --text: #a1a1aa;
            --text-bright: #ffffff;
            --accent: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.25);
            --font: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg); color: var(--text); font-family: var(--font);
            font-size: 12.5px; line-height: 1.35; min-height: 100vh;
            display: flex; flex-direction: column;
        }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 10px; width: 100%; }

        /* Header */
        header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid var(--border); margin-bottom: 14px;
        }
        .logo { font-size: 14px; font-weight: 800; color: var(--text-bright); letter-spacing: -0.4px; }
        .logo span { color: var(--accent); }
        .nav { display: flex; gap: 8px; }
        .nav a { background: var(--card); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border); font-size: 10px; }
        .nav a:hover { border-color: var(--accent); color: var(--text-bright); }

        /* Hero */
        .hero { text-align: center; padding: 12px 0 16px; }
        .hero h1 { font-size: 16px; font-weight: 700; color: var(--text-bright); margin-bottom: 5px; }
        .hero p { font-size: 11px; opacity: 0.85; max-width: 480px; margin: 0 auto; }

        /* Grid */
        .grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 8px; margin-bottom: 18px;
        }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 7px;
            padding: 9px; transition: transform 0.15s, border-color 0.15s; cursor: pointer;
        }
        .card:hover { transform: translateY(-2px); border-color: var(--accent); background: #121218; }
        .card .ico { font-size: 18px; margin-bottom: 5px; display: block; }
        .card h3 { font-size: 12.5px; color: var(--text-bright); font-weight: 600; margin-bottom: 2px; }
        .card p { font-size: 10.5px; opacity: 0.75; }
        .tag {
            display: inline-block; font-size: 8.5px; background: #15151d;
            padding: 2px 5px; border-radius: 4px; margin-top: 5px; color: var(--accent);
        }

        /* Draggable Popup Menu */
        #dock {
            position: fixed; bottom: 16px; right: 16px; background: var(--card);
            border: 1px solid var(--border); border-radius: 10px; padding: 5px;
            width: 130px; box-shadow: 0 6px 24px rgba(0,0,0,0.6); z-index: 9999;
            transition: opacity 0.2s, transform 0.2s; cursor: grab;
        }
        #dock:active { cursor: grabbing; }
        #dock.hide { opacity: 0; pointer-events: none; transform: scale(0.92); }
        .dock-btn {
            width: 100%; height: 32px; border-radius: 7px; background: var(--accent);
            color: white; border: none; font-size: 12px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .dock-list { display: none; flex-direction: column; gap: 3px; margin-top: 4px; }
        .dock-list.open { display: flex; }
        .dock-item {
            background: transparent; border: none; color: var(--text); padding: 5px 6px;
            border-radius: 5px; text-align: left; font-size: 10px; cursor: pointer;
            display: flex; align-items: center; gap: 5px;
        }
        .dock-item:hover { background: #15151d; color: var(--text-bright); }

        footer {
            margin-top: auto; padding: 10px 0; border-top: 1px solid var(--border);
            text-align: center; font-size: 9.5px; opacity: 0.55;
        }
        @media (max-width: 550px) {
            .grid { grid-template-columns: 1fr; }
            #dock { bottom: 10px; right: 10px; width: 120px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <div class="logo">akkuapps<span>.in</span></div>
            <nav class="nav">
                <a href="/auth/login.php">🔑 Login</a>
                <a href="/auth/register.php">✨ Join</a>
            </nav>
        </header>

        <div class="hero">
            <h1>🚀 Welcome to AkkuApps</h1>
            <p>🌐 Multi-core digital hub: Tech, Games, AI, Sales & Learning.<br>
            💎 Compact UI • 🪙 Coin Economy • ⚡ Lightning Fast</p>
        </div>

        <div class="grid">
            <a href="/laptops" class="card">
                <span class="ico">💻</span>
                <h3>Desktop & Laptop Sales</h3>
                <p>Repairs, upgrades & custom builds. Warranty & support.</p>
                <span class="tag">🛠️ Services</span>
            </a>
            <a href="/blog" class="card">
                <span class="ico">📰</span>
                <h3>Tech Blog & News</h3>
                <p>Tutorials, reviews & industry updates. Fresh daily.</p>
                <span class="tag">📖 Read</span>
            </a>
            <a href="/games" class="card">
                <span class="ico">🎮</span>
                <h3>Game Arena</h3>
                <p>Play, compete & earn 🪙 coins. Leaderboards & rewards.</p>
                <span class="tag">🏆 Play</span>
            </a>
            <a href="https://chatbot.akkuapps.in/" class="card">
                <span class="ico">🤖</span>
                <h3>AI Studio</h3>
                <p>Chat, generate & edit media. Multi-model powered.</p>
                <span class="tag">✨ AI Tools</span>
            </a>
            <a href="/study" class="card">
                <span class="ico">📚</span>
                <h3>Study Materials & Links</h3>
                <p>PDFs, cheat sheets, curated links & resources.</p>
                <span class="tag">🎓 Learn</span>
            </a>
            <a href="https://chatbot.akkuapps.in/ImageEditor" class="card">
                <span class="ico">🖼️</span>
                <h3>Image Editor</h3>
                <p>Layers, filters, AI brushes & export. Pro tools free.</p>
                <span class="tag">🎨 Create</span>
            </a>
            <a href="/downloads" class="card">
                <span class="ico">📦</span>
                <h3>Software & Links</h3>
                <p>Windows, mobile apps, source code & utilities.</p>
                <span class="tag">💾 Download</span>
            </a>
        </div>
    </div>

    <!-- 📌 Draggable Popup Menu -->
    <div id="dock">
        <button class="dock-btn" onclick="toggleDock()">☰ Quick Nav</button>
        <div class="dock-list" id="dock-list">
            <button class="dock-item" onclick="location.href='/blog'">📰 Tech News</button>
            <button class="dock-item" onclick="location.href='/games'">🎮 Games</button>
            <button class="dock-item" onclick="location.href='/downloads'">📦 Software</button>
            <button class="dock-item" onclick="location.href='/study'">📚 Study</button>
            <button class="dock-item" onclick="location.href='/laptops'">💻 PC Sales</button>
            <button class="dock-item" onclick="window.open('https://chatbot.akkuapps.in/', '_blank')">🤖 AI Studio</button>
            <button class="dock-item" onclick="location.href='/refund-policy.php'">📜 Policies</button>
        </div>
    </div>

    <footer>
        © <?php echo date('Y'); ?> AkkuApps.in | Powered by 🌟 Leo Infotech<br>
        🪙 Coin Economy Active | 🌙 Dark Mode • 📱 Tiny UI
    </footer>

    <script>
        function toggleDock() { document.getElementById('dock-list').classList.toggle('open'); }

        const dock = document.getElementById('dock');
        let drag = false, ox = 0, oy = 0;

        // Mouse Drag
        dock.addEventListener('mousedown', e => {
            if (e.target.closest('.dock-item') || e.target.tagName === 'BUTTON') return;
            drag = true; ox = e.clientX - dock.offsetLeft; oy = e.clientY - dock.offsetTop;
            dock.style.cursor = 'grabbing';
        });
        document.addEventListener('mousemove', e => {
            if (!drag) return;
            dock.style.left = `${e.clientX - ox}px`; dock.style.top = `${e.clientY - oy}px`;
            dock.style.right = 'auto'; dock.style.bottom = 'auto';
        });
        document.addEventListener('mouseup', () => { drag = false; dock.style.cursor = 'grab'; });

        // Touch Drag (Mobile)
        dock.addEventListener('touchstart', e => {
            if (e.target.closest('.dock-item') || e.target.tagName === 'BUTTON') return;
            drag = true; ox = e.touches[0].clientX - dock.offsetLeft; oy = e.touches[0].clientY - dock.offsetTop;
        }, {passive: true});
        document.addEventListener('touchmove', e => {
            if (!drag) return; e.preventDefault();
            dock.style.left = `${e.touches[0].clientX - ox}px`; dock.style.top = `${e.touches[0].clientY - oy}px`;
            dock.style.right = 'auto'; dock.style.bottom = 'auto';
        }, {passive: false});
        document.addEventListener('touchend', () => { drag = false; });
    </script>
</body>
</html>