<?php
// index.php – Main landing page with auth awareness
// FIX: 2x larger fonts and emojis
// FIX: Fully mobile responsive
// FIX: Better touch targets
define('AKKUAPPS_LOADED', true);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "AkkuApps.in 🚀";
$user = getCurrentUser();
$isLoggedIn = !empty($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
            font-size: 16px; line-height: 1.5; min-height: 100vh;
            display: flex; flex-direction: column;
        }
        a { text-decoration: none; color: inherit; transition: 0.2s; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 20px; width: 100%; }

        /* Header - 2x larger */
        header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 0; border-bottom: 1px solid var(--border); margin-bottom: 24px;
            flex-wrap: wrap; gap: 12px;
        }
        .logo { font-size: 24px; font-weight: 800; color: var(--text-bright); letter-spacing: -0.5px; }
        .logo span { color: var(--accent); }
        .nav { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .nav a, .nav button {
            background: var(--card); padding: 8px 16px; border-radius: 10px;
            border: 1px solid var(--border); font-size: 14px; cursor: pointer;
            color: var(--text); font-family: inherit; font-weight: 500;
        }
        .nav a:hover, .nav button:hover { border-color: var(--accent); color: var(--text-bright); }
        
        /* User avatar in header - 2x larger */
        .user-menu {
            display: flex; align-items: center; gap: 10px;
            background: var(--card); padding: 8px 16px; border-radius: 30px;
            border: 1px solid var(--border);
        }
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: var(--accent); display: flex; align-items: center;
            justify-content: center; color: white; font-weight: bold; font-size: 14px;
        }
        .user-coins {
            color: var(--accent); font-weight: 700; font-size: 14px;
        }

        /* Hero - 2x larger */
        .hero { text-align: center; padding: 32px 0 28px; }
        .hero h1 { font-size: 32px; font-weight: 800; color: var(--text-bright); margin-bottom: 12px; }
        .hero p { font-size: 16px; opacity: 0.85; max-width: 560px; margin: 0 auto; line-height: 1.6; }

        /* Grid - 2x larger cards */
        .grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px; margin-bottom: 32px;
        }
        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 14px;
            padding: 24px; transition: transform 0.15s, border-color 0.15s; cursor: pointer;
        }
        .card:hover { transform: translateY(-3px); border-color: var(--accent); background: #121218; }
        .card .ico { font-size: 40px; margin-bottom: 12px; display: block; }
        .card h3 { font-size: 20px; color: var(--text-bright); font-weight: 700; margin-bottom: 8px; }
        .card p { font-size: 14px; opacity: 0.8; line-height: 1.5; }
        .tag {
            display: inline-block; font-size: 12px; background: #15151d;
            padding: 4px 10px; border-radius: 6px; margin-top: 12px; color: var(--accent);
            font-weight: 600;
        }

        /* Draggable Popup Menus - 2x larger */
        .dock {
            position: fixed; background: var(--card);
            border: 1px solid var(--border); border-radius: 14px; padding: 8px;
            width: 200px; box-shadow: 0 8px 32px rgba(0,0,0,0.6); z-index: 9999;
            transition: opacity 0.2s, transform 0.2s; cursor: grab;
        }
        .dock:active { cursor: grabbing; }
        .dock.hide { opacity: 0; pointer-events: none; transform: scale(0.92); }
        .dock-btn {
            width: 100%; height: 48px; border-radius: 10px;
            color: white; border: none; font-size: 16px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .dock-list { display: none; flex-direction: column; gap: 4px; margin-top: 6px; }
        .dock-list.open { display: flex; }
        .dock-item {
            background: transparent; border: none; color: var(--text); padding: 12px;
            border-radius: 8px; text-align: left; font-size: 14px; cursor: pointer;
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
        .dock-item:hover { background: #15151d; color: var(--text-bright); }
        .dock-item .emoji { font-size: 20px; }
        
        #dock-main { bottom: 24px; right: 24px; }
        #dock-tools { bottom: 24px; left: 24px; }
        #dock-user { top: 100px; right: 24px; }

        footer {
            margin-top: auto; padding: 20px 0; border-top: 1px solid var(--border);
            text-align: center; font-size: 14px; opacity: 0.6;
        }

        /* ========== MOBILE RESPONSIVE ========== */
        @media (max-width: 768px) {
            body { font-size: 14px; }
            .wrap { padding: 12px; }
            
            header {
                padding: 12px 0;
                justify-content: center;
                text-align: center;
            }
            .logo { font-size: 20px; width: 100%; text-align: center; margin-bottom: 10px; }
            .nav { width: 100%; justify-content: center; }
            .nav a, .nav button { padding: 6px 12px; font-size: 12px; }
            
            .user-menu { padding: 6px 12px; }
            .user-avatar { width: 28px; height: 28px; font-size: 11px; }
            
            .hero { padding: 20px 0; }
            .hero h1 { font-size: 22px; }
            .hero p { font-size: 13px; }
            
            .grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .card { padding: 18px; }
            .card .ico { font-size: 32px; }
            .card h3 { font-size: 17px; }
            .card p { font-size: 13px; }
            
            .dock {
                width: 160px;
                padding: 6px;
            }
            .dock-btn { height: 40px; font-size: 13px; }
            .dock-item { padding: 10px; font-size: 12px; }
            
            #dock-main { bottom: 12px; right: 12px; }
            #dock-tools { bottom: 12px; left: 12px; }
            #dock-user { top: 80px; right: 12px; }
        }
        
        @media (max-width: 380px) {
            .hero h1 { font-size: 18px; }
            .card .ico { font-size: 28px; }
            .dock { width: 140px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <div class="logo">akkuapps<span>.in</span></div>
            <nav class="nav">
                <?php if ($isLoggedIn): ?>
                    <div class="user-menu">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <span style="color:var(--text-bright); font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></span>
                        <span class="user-coins">🪙<?php echo number_format($user['coin_balance'] ?? 0); ?></span>
                    </div>
                    <a href="/user/dashboard.php">Dashboard</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="/admin/dashboard.php">Admin</a>
                    <?php endif; ?>
                    <a href="/auth/logout.php">Logout</a>
                <?php else: ?>
                    <a href="/auth/login.php">🔑 Login</a>
                    <a href="/auth/register.php">✨ Join</a>
                <?php endif; ?>
            </nav>
        </header>

        <div class="hero">
            <h1>🚀 Welcome to AkkuApps<?php echo $isLoggedIn ? ', ' . htmlspecialchars($user['name']) : ''; ?></h1>
            <p>🌐 Multi-core digital hub: Tech, Games, AI, Sales & Learning.<br>
            💎 Compact UI • 🪙 Coin Economy • ⚡ Lightning Fast</p>
        </div>

        <div class="grid">
            <a href="/ComputerSales" class="card">
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

    <!-- 📌 Draggable Quick Nav Menu 1: Main Navigation -->
    <div class="dock" id="dock-main">
        <button class="dock-btn" style="background:var(--accent);" onclick="toggleDock('dock-main')">
            <span class="emoji">☰</span> Quick Nav
        </button>
        <div class="dock-list" id="dock-main-list">
            <button class="dock-item" onclick="location.href='/blog'">
                <span class="emoji">📰</span> Tech News
            </button>
            <button class="dock-item" onclick="location.href='/games'">
                <span class="emoji">🎮</span> Games
            </button>
            <button class="dock-item" onclick="location.href='/downloads'">
                <span class="emoji">📦</span> Software
            </button>
            <button class="dock-item" onclick="location.href='/study'">
                <span class="emoji">📚</span> Study
            </button>
            <button class="dock-item" onclick="location.href='/ComputerSales'">
                <span class="emoji">💻</span> PC Sales
            </button>
            <button class="dock-item" onclick="window.open('https://chatbot.akkuapps.in/', '_blank')">
                <span class="emoji">🤖</span> AI Studio
            </button>
        </div>
    </div>

    <!-- 📌 Draggable Quick Nav Menu 2: Tools -->
    <div class="dock" id="dock-tools">
        <button class="dock-btn" style="background:#10b981;" onclick="toggleDock('dock-tools')">
            <span class="emoji">🛠️</span> Tools
        </button>
        <div class="dock-list" id="dock-tools-list">
            <button class="dock-item" onclick="window.open('https://chatbot.akkuapps.in/ImageEditor', '_blank')">
                <span class="emoji">🖼️</span> Image Editor
            </button>
            <button class="dock-item" onclick="location.href='/api/mobile-api.php'">
                <span class="emoji">📱</span> Mobile API
            </button>
            <button class="dock-item" onclick="location.href='/leochatbot'">
                <span class="emoji">💬</span> Leo Chat
            </button>
            <button class="dock-item" onclick="location.href='/games/leaderboard.php'">
                <span class="emoji">🏆</span> Leaderboard
            </button>
            <button class="dock-item" onclick="location.href='/refund-policy.php'">
                <span class="emoji">📜</span> Policies
            </button>
        </div>
    </div>

    <!-- 📌 Draggable Quick Nav Menu 3: User Actions -->
    <div class="dock" id="dock-user">
        <button class="dock-btn" style="background:#f59e0b;" onclick="toggleDock('dock-user')">
            <span class="emoji">👤</span> User
        </button>
        <div class="dock-list" id="dock-user-list">
            <?php if ($isLoggedIn): ?>
                <button class="dock-item" onclick="location.href='/user/dashboard.php'">
                    <span class="emoji">📊</span> Dashboard
                </button>
                <button class="dock-item" onclick="location.href='/user/profile.php'">
                    <span class="emoji">👤</span> Profile
                </button>
                <button class="dock-item" onclick="location.href='/user/wallet.php'">
                    <span class="emoji">💰</span> Wallet
                </button>
                <button class="dock-item" onclick="location.href='/user/settings.php'">
                    <span class="emoji">⚙️</span> Settings
                </button>
                <button class="dock-item" onclick="location.href='/auth/logout.php'">
                    <span class="emoji">🚪</span> Logout
                </button>
            <?php else: ?>
                <button class="dock-item" onclick="location.href='/auth/login.php'">
                    <span class="emoji">🔑</span> Login
                </button>
                <button class="dock-item" onclick="location.href='/auth/register.php'">
                    <span class="emoji">✨</span> Register
                </button>
            <?php endif; ?>
            <?php if ($isLoggedIn && $user['role'] === 'admin'): ?>
                <button class="dock-item" onclick="location.href='/admin/dashboard.php'">
                    <span class="emoji">🛡️</span> Admin
                </button>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        © <?php echo date('Y'); ?> AkkuApps.in | Powered by 🌟 Leo Infotech<br>
        🪙 Coin Economy Active | 🌙 Dark Mode • 📱 Responsive UI
        <?php if ($isLoggedIn): ?>
            | Logged in as <?php echo htmlspecialchars($user['name']); ?>
        <?php endif; ?>
    </footer>

    <script>
        function toggleDock(dockId) {
            const list = document.getElementById(dockId + '-list');
            list.classList.toggle('open');
        }

        // Make all docks draggable
        document.querySelectorAll('.dock').forEach(dock => {
            let drag = false, ox = 0, oy = 0;

            dock.addEventListener('mousedown', e => {
                if (e.target.closest('.dock-item') || e.target.closest('.dock-btn')) return;
                drag = true;
                ox = e.clientX - dock.offsetLeft;
                oy = e.clientY - dock.offsetTop;
                dock.style.cursor = 'grabbing';
            });
            document.addEventListener('mousemove', e => {
                if (!drag) return;
                dock.style.left = (e.clientX - ox) + 'px';
                dock.style.top = (e.clientY - oy) + 'px';
                dock.style.right = 'auto';
                dock.style.bottom = 'auto';
            });
            document.addEventListener('mouseup', () => {
                drag = false;
                dock.style.cursor = 'grab';
            });

            dock.addEventListener('touchstart', e => {
                if (e.target.closest('.dock-item') || e.target.closest('.dock-btn')) return;
                drag = true;
                ox = e.touches[0].clientX - dock.offsetLeft;
                oy = e.touches[0].clientY - dock.offsetTop;
            }, {passive: true});
            document.addEventListener('touchmove', e => {
                if (!drag) return;
                e.preventDefault();
                dock.style.left = (e.touches[0].clientX - ox) + 'px';
                dock.style.top = (e.touches[0].clientY - oy) + 'px';
                dock.style.right = 'auto';
                dock.style.bottom = 'auto';
            }, {passive: false});
            document.addEventListener('touchend', () => {
                drag = false;
            });
        });
    </script>
</body>
</html>