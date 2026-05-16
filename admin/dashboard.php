<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
$totalPosts = $pdo->query("SELECT COUNT(*) FROM user_posts")->fetchColumn() ?? 0;
$totalGames = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE status = 'completed'")->fetchColumn() ?? 0;
$todayLogins = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()")->fetchColumn() ?? 0;
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM upi_payments WHERE status = 'pending'")->fetchColumn() ?? 0;
$totalCoins = $pdo->query("SELECT SUM(coin_balance) FROM users")->fetchColumn() ?? 0;

$stats = getEconomyStats();
$collectionBox = new AkkuCollectionBox($pdo);
$boxBalance = $collectionBox->getBalance();
?>

<style>
    :root {
        --admin-bg: #08080c;
        --admin-card: #0f0f14;
        --admin-border: #1a1a22;
        --admin-text: #a1a1aa;
        --admin-bright: #ffffff;
        --admin-accent: #6366f1;
        --admin-green: #10b981;
        --admin-yellow: #f59e0b;
        --admin-purple: #a855f7;
    }
    .admin-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
    .admin-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
    }
    .admin-header h1 { font-size: 28px; color: var(--admin-bright); font-weight: 800; }
    .admin-header p { color: var(--admin-text); font-size: 14px; margin-top: 4px; }
    .role-badge {
        padding: 6px 18px; border-radius: 24px;
        font-size: 13px; background: var(--admin-accent); color: white; font-weight: 700;
    }
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px; margin-bottom: 28px;
    }
    .stat-card {
        background: var(--admin-card); border: 1px solid var(--admin-border);
        border-radius: 14px; padding: 24px; transition: all 0.2s;
    }
    .stat-card:hover { border-color: var(--admin-accent); transform: translateY(-3px); }
    .stat-card .emoji { font-size: 32px; display: block; margin-bottom: 8px; }
    .stat-value { font-size: 36px; font-weight: 800; color: var(--admin-accent); margin: 8px 0; }
    .stat-label {
        font-size: 13px; color: var(--admin-text);
        text-transform: uppercase; letter-spacing: 1px; font-weight: 600;
    }
    .stat-card.alert { border-color: var(--admin-yellow); }
    .stat-card.alert .stat-value { color: var(--admin-yellow); }
    .stat-card.success { border-color: var(--admin-green); }
    .stat-card.success .stat-value { color: var(--admin-green); }
    .stat-card.purple { border-color: var(--admin-purple); }
    .stat-card.purple .stat-value { color: var(--admin-purple); }
    .sections-wrap {
        display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
    }
    .admin-section {
        background: var(--admin-card); border: 1px solid var(--admin-border);
        border-radius: 14px; padding: 24px;
    }
    .admin-section h2 {
        font-size: 20px; color: var(--admin-bright); margin-bottom: 16px;
        display: flex; align-items: center; gap: 10px; font-weight: 700;
    }
    .admin-section h2 .emoji { font-size: 24px; }
    .admin-link {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px; border-radius: 10px;
        color: var(--admin-text); text-decoration: none;
        transition: all 0.2s; border: 1px solid transparent;
        margin-bottom: 8px; font-size: 15px; font-weight: 500;
    }
    .admin-link:hover {
        background: #15151d; border-color: var(--admin-border); color: var(--admin-bright);
    }
    .admin-link .emoji { font-size: 22px; }
    .badge {
        margin-left: auto; background: var(--admin-accent); color: white;
        padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700;
    }
    .badge.alert { background: var(--admin-yellow); color: #000; }
    .badge.success { background: var(--admin-green); }
    .badge.purple { background: var(--admin-purple); }
    .dock {
        position: fixed; background: var(--admin-card);
        border: 1px solid var(--admin-border); border-radius: 14px;
        padding: 8px; width: 200px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        z-index: 9999; cursor: grab;
    }
    .dock:active { cursor: grabbing; }
    .dock-btn {
        width: 100%; height: 48px; border-radius: 10px;
        color: white; border: none; font-size: 15px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .dock-list { display: none; flex-direction: column; gap: 4px; margin-top: 6px; }
    .dock-item {
        background: transparent; border: none; color: var(--admin-text);
        padding: 12px; border-radius: 8px; text-align: left;
        font-size: 14px; cursor: pointer;
        display: flex; align-items: center; gap: 10px; font-weight: 500;
    }
    .dock-item:hover { background: #15151d; color: var(--admin-bright); }
    #dock-admin { bottom: 24px; right: 24px; }
    #dock-quick { bottom: 24px; left: 24px; }
    @media (max-width: 768px) {
        .admin-wrap { padding: 12px; }
        .admin-header h1 { font-size: 22px; }
        .admin-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 16px; }
        .stat-card .emoji { font-size: 24px; }
        .stat-value { font-size: 28px; }
        .sections-wrap { grid-template-columns: 1fr; gap: 12px; }
        .admin-section { padding: 16px; }
        .admin-section h2 { font-size: 16px; }
        .admin-link { padding: 10px 12px; font-size: 13px; }
        .dock { width: 160px; padding: 6px; }
        .dock-btn { height: 40px; font-size: 13px; }
        .dock-item { padding: 10px; font-size: 12px; }
        #dock-admin { bottom: 12px; right: 12px; }
        #dock-quick { bottom: 12px; left: 12px; }
    }
    @media (max-width: 380px) {
        .admin-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="admin-wrap">

    <div class="admin-header">
        <div>
            <h1><span class="emoji">🛡️</span> Admin Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <span class="role-badge"><?= ucfirst($user['role']) ?></span>
    </div>

    <div class="admin-grid">
        <div class="stat-card">
            <span class="emoji">👥</span>
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
        </div>
        <div class="stat-card">
            <span class="emoji">📝</span>
            <div class="stat-label">Total Posts</div>
            <div class="stat-value"><?= number_format($totalPosts) ?></div>
        </div>
        <div class="stat-card">
            <span class="emoji">🎮</span>
            <div class="stat-label">Games Played</div>
            <div class="stat-value"><?= number_format($totalGames) ?></div>
        </div>
        <div class="stat-card">
            <span class="emoji">📅</span>
            <div class="stat-label">Today's Logins</div>
            <div class="stat-value"><?= number_format($todayLogins) ?></div>
        </div>
        <div class="stat-card success">
            <span class="emoji">🪙</span>
            <div class="stat-label">Total Coins</div>
            <div class="stat-value"><?= number_format($totalCoins, 0) ?></div>
        </div>
        <div class="stat-card alert">
            <span class="emoji">⏳</span>
            <div class="stat-label">Pending UPI</div>
            <div class="stat-value"><?= number_format($pendingPayments) ?></div>
        </div>
        <div class="stat-card purple">
            <span class="emoji">🏦</span>
            <div class="stat-label">CollectionBox</div>
            <div class="stat-value"><?= number_format($boxBalance, 0) ?></div>
        </div>
    </div>

    <div class="sections-wrap">
        <div class="admin-section">
            <h2><span class="emoji">🏦</span> Economy</h2>
            <a href="/admin/coins.php" class="admin-link">
                <span class="emoji">🪙</span> Coin Management <span class="badge">Admin</span>
            </a>
            <a href="/admin/economy.php" class="admin-link">
                <span class="emoji">🎁</span> Economy Manager <span class="badge success">NEW</span>
            </a>
            <a href="/admin/collectionbox.php" class="admin-link">
                <span class="emoji">🏦</span> AkkuCollectionBox <span class="badge purple">TREASURY</span>
            </a>
            <a href="/admin/games.php" class="admin-link">
                <span class="emoji">🎮</span> Game Commission <span class="badge">Admin</span>
            </a>
            <a href="/admin/payments.php" class="admin-link">
                <span class="emoji">💳</span> UPI Payments 
                <?php if ($pendingPayments > 0): ?>
                    <span class="badge alert"><?= $pendingPayments ?> pending</span>
                <?php endif; ?>
            </a>
        </div>
        <div class="admin-section">
            <h2><span class="emoji">📂</span> Management</h2>
            <a href="/admin/users.php" class="admin-link">
                <span class="emoji">👥</span> Users <span class="badge"><?= $totalUsers ?></span>
            </a>
            <a href="/admin/posts.php" class="admin-link">
                <span class="emoji">📝</span> User Posts <span class="badge"><?= $totalPosts ?></span>
            </a>
            <a href="/admin/comments.php" class="admin-link">
                <span class="emoji">💬</span> Comments
            </a>
            <a href="/admin/source-categories.php" class="admin-link">
                <span class="emoji">📁</span> Download Categories
            </a>
            <a href="/admin/source-items.php" class="admin-link">
                <span class="emoji">📦</span> Download Items
            </a>
        </div>
        <div class="admin-section">
            <h2><span class="emoji">⚙️</span> System</h2>
            <a href="/admin/analytics.php" class="admin-link">
                <span class="emoji">📊</span> Analytics
            </a>
            <a href="/admin/settings.php" class="admin-link">
                <span class="emoji">🔧</span> Site Settings
            </a>
            <a href="/admin/reset-sessions.php" class="admin-link">
                <span class="emoji">🔄</span> Reset Sessions
            </a>
            <a href="/api/diagnose.php" class="admin-link">
                <span class="emoji">🔍</span> System Diagnose
            </a>
            <a href="/" class="admin-link" style="color:var(--admin-accent);">
                <span class="emoji">🏠</span> Back to Site
            </a>
        </div>
    </div>

</div>

<div class="dock" id="dock-admin">
    <button class="dock-btn" style="background:var(--admin-accent);" onclick="toggleDock('dock-admin')">
        <span class="emoji">🛡️</span> Admin Nav
    </button>
    <div class="dock-list" id="dock-admin-list">
        <button class="dock-item" onclick="location.href='/admin/economy.php'"><span class="emoji">🏦</span> Economy</button>
        <button class="dock-item" onclick="location.href='/admin/collectionbox.php'"><span class="emoji">🏦</span> CollectionBox</button>
        <button class="dock-item" onclick="location.href='/admin/coins.php'"><span class="emoji">🪙</span> Coins</button>
        <button class="dock-item" onclick="location.href='/admin/users.php'"><span class="emoji">👥</span> Users</button>
        <button class="dock-item" onclick="location.href='/admin/posts.php'"><span class="emoji">📝</span> Posts</button>
        <button class="dock-item" onclick="location.href='/user/dashboard.php'"><span class="emoji">👤</span> User Panel</button>
    </div>
</div>

<div class="dock" id="dock-quick">
    <button class="dock-btn" style="background:#10b981;" onclick="toggleDock('dock-quick')">
        <span class="emoji">⚡</span> Quick Tools
    </button>
    <div class="dock-list" id="dock-quick-list">
        <button class="dock-item" onclick="location.href='/games'"><span class="emoji">🎮</span> Games</button>
        <button class="dock-item" onclick="window.open('https://chatbot.akkuapps.in/', '_blank')"><span class="emoji">🤖</span> AI Chat</button>
        <button class="dock-item" onclick="location.href='/blog'"><span class="emoji">📰</span> Blog</button>
        <button class="dock-item" onclick="location.href='/user/posts/feed.php'"><span class="emoji">📱</span> Feed</button>
    </div>
</div>

<script>
function toggleDock(dockId) {
    const list = document.getElementById(dockId + '-list');
    list.style.display = list.style.display === 'flex' ? 'none' : 'flex';
}
document.querySelectorAll('.dock').forEach(dock => {
    let drag = false, ox = 0, oy = 0;
    dock.addEventListener('mousedown', e => {
        if (e.target.closest('.dock-item') || e.target.closest('.dock-btn')) return;
        drag = true; ox = e.clientX - dock.offsetLeft; oy = e.clientY - dock.offsetTop;
    });
    document.addEventListener('mousemove', e => {
        if (!drag) return;
        dock.style.left = (e.clientX - ox) + 'px'; dock.style.top = (e.clientY - oy) + 'px';
        dock.style.right = 'auto'; dock.style.bottom = 'auto';
    });
    document.addEventListener('mouseup', () => { drag = false; });
    dock.addEventListener('touchstart', e => {
        if (e.target.closest('.dock-item') || e.target.closest('.dock-btn')) return;
        drag = true; ox = e.touches[0].clientX - dock.offsetLeft; oy = e.touches[0].clientY - dock.offsetTop;
    }, {passive: true});
    document.addEventListener('touchmove', e => {
        if (!drag) return; e.preventDefault();
        dock.style.left = (e.touches[0].clientX - ox) + 'px'; dock.style.top = (e.touches[0].clientY - oy) + 'px';
        dock.style.right = 'auto'; dock.style.bottom = 'auto';
    }, {passive: false});
    document.addEventListener('touchend', () => { drag = false; });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>