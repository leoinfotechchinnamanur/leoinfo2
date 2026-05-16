<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
$user = getCurrentUser();
$postCost = 2.00;
        $canCreatePost = ($user['coin_balance'] ?? 0) >= $postCost;

        
$inventory = getUserInventory($user['user_id']);
$tokens = getUserTokens($user['user_id']);

?>

<style>
    :root {
        --dash-bg: #08080c;
        --dash-card: #0f0f14;
        --dash-border: #1a1a22;
        --dash-text: #a1a1aa;
        --dash-bright: #ffffff;
        --dash-accent: #6366f1;
        --dash-green: #10b981;
    }
    .dashboard-wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
    .profile-banner {
        background: var(--dash-card); border: 1px solid var(--dash-border);
        border-radius: 16px; padding: 24px; margin-bottom: 24px;
        display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
    }
    .profile-avatar {
        width: 72px; height: 72px; border-radius: 50%;
        background: var(--dash-accent); display: flex;
        align-items: center; justify-content: center;
        color: white; font-size: 32px; font-weight: bold; flex-shrink: 0;
    }
    .profile-info h1 { font-size: 24px; color: var(--dash-bright); font-weight: 700; margin-bottom: 6px; }
    .profile-info p { color: var(--dash-text); font-size: 14px; margin-bottom: 8px; }
    .role-badge {
        display: inline-block; padding: 4px 14px; border-radius: 20px;
        font-size: 12px; background: var(--dash-accent); color: white; font-weight: 600;
    }
    .coin-display {
        margin-left: auto; text-align: center; padding: 16px 28px;
        background: #15151d; border-radius: 14px;
        border: 1px solid var(--dash-border); min-width: 140px;
    }
    .coin-display .emoji { font-size: 28px; display: block; margin-bottom: 4px; }
    .coin-display .amount { font-size: 32px; font-weight: 800; color: var(--dash-accent); }
    .coin-display .label {
        font-size: 12px; color: var(--dash-text);
        text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;
    }
    .quick-stats {
        display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;
    }
    .quick-stat {
        flex: 1; min-width: 100px; background: var(--dash-card);
        border: 1px solid var(--dash-border); border-radius: 12px;
        padding: 14px; text-align: center;
    }
    .quick-stat .emoji { font-size: 24px; }
    .quick-stat .value { font-size: 20px; font-weight: 800; color: var(--dash-bright); }
    .quick-stat .label { font-size: 11px; color: var(--dash-text); }
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 16px;
    }
    .dash-card {
        background: var(--dash-card); border: 1px solid var(--dash-border);
        border-radius: 14px; padding: 24px 16px; text-align: center;
        transition: all 0.2s; cursor: pointer; text-decoration: none;
        color: inherit; display: flex; flex-direction: column;
        align-items: center; min-height: 140px; justify-content: center;
    }
    .dash-card:hover {
        border-color: var(--dash-accent); transform: translateY(-3px); background: #121218;
    }
    .dash-card .emoji { font-size: 40px; margin-bottom: 12px; display: block; }
    .dash-card h3 { font-size: 16px; color: var(--dash-bright); margin-bottom: 6px; font-weight: 600; }
    .dash-card p { font-size: 12px; color: var(--dash-text); }
    .dock {
        position: fixed; background: var(--dash-card);
        border: 1px solid var(--dash-border); border-radius: 14px;
        padding: 8px; width: 180px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        z-index: 9999; cursor: grab;
    }
    .dock:active { cursor: grabbing; }
    .dock-btn {
        width: 100%; height: 44px; border-radius: 10px;
        color: white; border: none; font-size: 14px; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .dock-list { display: none; flex-direction: column; gap: 4px; margin-top: 6px; }
    .dock-item {
        background: transparent; border: none; color: var(--dash-text);
        padding: 10px; border-radius: 8px; text-align: left;
        font-size: 13px; cursor: pointer;
        display: flex; align-items: center; gap: 8px;
    }
    .dock-item:hover { background: #15151d; color: var(--dash-bright); }
    #dock-user { bottom: 20px; right: 20px; }
    #dock-site { bottom: 20px; left: 20px; }
    @media (max-width: 640px) {
        .dashboard-wrap { padding: 10px; }
        .profile-banner { padding: 16px; gap: 14px; text-align: center; justify-content: center; }
        .profile-avatar { width: 56px; height: 56px; font-size: 24px; }
        .profile-info h1 { font-size: 18px; }
        .coin-display { margin-left: 0; width: 100%; padding: 12px; }
        .coin-display .amount { font-size: 24px; }
        .dashboard-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .dash-card { padding: 16px 10px; min-height: 110px; }
        .dash-card .emoji { font-size: 28px; margin-bottom: 8px; }
        .dash-card h3 { font-size: 13px; }
        .dash-card p { font-size: 10px; }
        .quick-stats { gap: 8px; }
        .quick-stat { padding: 10px; min-width: 80px; }
        .dock { width: 150px; padding: 6px; }
        .dock-btn { height: 38px; font-size: 12px; }
        .dock-item { padding: 8px; font-size: 11px; }
        #dock-user { bottom: 10px; right: 10px; }
        #dock-site { bottom: 10px; left: 10px; }
    }
    @media (max-width: 380px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-wrap">

    <div class="profile-banner">
        <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="profile-info">
            <h1>Welcome, <?= htmlspecialchars($user['name']) ?>! 👋</h1>
            <p><?= htmlspecialchars($user['email']) ?></p>
            <span class="role-badge"><?= ucfirst($user['role']) ?></span>
        </div>
        <div class="coin-display">
            <span class="emoji">🪙</span>
            <div class="amount"><?= number_format($user['coin_balance'] ?? 0, 2) ?></div>
            <div class="label">Coin Balance</div>
        </div>
    </div>

    <div class="quick-stats">
        <div class="quick-stat">
            <span class="emoji">🎖️</span>
            <div class="value"><?= count($inventory['badges']) ?></div>
            <div class="label">Badges</div>
        </div>
        <div class="quick-stat">
            <span class="emoji">🏆</span>
            <div class="value"><?= count($tokens) ?></div>
            <div class="label">Tokens</div>
        </div>
        <div class="quick-stat">
            <span class="emoji">🎁</span>
            <div class="value"><?= count($inventory['gifts']) ?></div>
            <div class="label">Gifts</div>
        </div>
        <div class="quick-stat">
            <span class="emoji">📝</span>
            <div class="value">New</div>
            <div class="label">Feed</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <a href="/user/posts/create.php" class="dash-card">
            <span class="emoji">✍️</span><h3>Create Post</h3><p>Share your thoughts</p>
        </a>

                <a href="<?= $canCreatePost ? '/user/posts/create.php' : '/user/wallet.php' ?>" class="dash-card" style="<?= !$canCreatePost ? 'border-color:#ef4444; opacity:0.7;' : '' ?>">
            <span class="emoji">✍️</span>
            <h3>Create Post</h3>
            <p>
                <?php if ($canCreatePost): ?>
                    Share your thoughts (-<?= $postCost ?> 🪙)
                <?php else: ?>
                    <span style="color:#ef4444;">Need <?= $postCost ?> coins</span>
                <?php endif; ?>
            </p>
        </a>
        <a href="/user/creator-dashboard.php" class="dash-card">
            <span class="emoji">📊</span><h3>Creator Dashboard</h3><p>Earnings & analytics</p>
        </a>
        <a href="/user/donations.php" class="dash-card">
            <span class="emoji">💳</span><h3>Donation Cards</h3><p>Send gift cards</p>
        </a>
        <a href="/user/posts/feed.php" class="dash-card">
            <span class="emoji">📱</span><h3>Feed</h3><p>Explore & interact</p>
        </a>
        <a href="/user/wallet.php" class="dash-card">
            <span class="emoji">💰</span><h3>Wallet</h3><p>Coins & inventory</p>
        </a>
        <a href="/games" class="dash-card">
            <span class="emoji">🎮</span><h3>Play Games</h3><p>Earn coins & compete</p>
        </a>
        <a href="/downloads" class="dash-card">
            <span class="emoji">📦</span><h3>Downloads</h3><p>Software & resources</p>
        </a>
        <a href="/user/profile.php" class="dash-card">
            <span class="emoji">👤</span><h3>Profile</h3><p>Edit your info</p>
        </a>
        <a href="/user/settings.php" class="dash-card">
            <span class="emoji">⚙️</span><h3>Settings</h3><p>Account preferences</p>
        </a>
        <a href="/user/posts/feed.php?type=following" class="dash-card">
            <span class="emoji">👥</span><h3>Following</h3><p>Your network</p>
        </a>
        <a href="/user/inventory.php" class="dash-card">
            <span class="emoji">🎒</span><h3>Inventory</h3><p>Badges & tokens</p>
        </a>
        <a href="/auth/logout.php" class="dash-card" style="border-color:#ef4444;">
            <span class="emoji">🚪</span><h3 style="color:#ef4444;">Logout</h3><p>Sign out safely</p>
        </a>
    </div>

</div>

<div class="dock" id="dock-user">
    <button class="dock-btn" style="background:var(--dash-accent);" onclick="toggleDock('dock-user')">
        <span class="emoji">👤</span> User Nav
    </button>
    <div class="dock-list" id="dock-user-list">
        <button class="dock-item" onclick="location.href='/user/wallet.php'"><span class="emoji">💰</span> Wallet</button>
        <button class="dock-item" onclick="location.href='/user/posts/feed.php'"><span class="emoji">📱</span> Feed</button>
        <button class="dock-item" onclick="location.href='/user/profile.php'"><span class="emoji">👤</span> Profile</button>
        <button class="dock-item" onclick="location.href='/user/settings.php'"><span class="emoji">⚙️</span> Settings</button>
        <button class="dock-item" onclick="location.href='/auth/logout.php'"><span class="emoji">🚪</span> Logout</button>
    </div>
</div>

<div class="dock" id="dock-site">
    <button class="dock-btn" style="background:#10b981;" onclick="toggleDock('dock-site')">
        <span class="emoji">🌐</span> Site Nav
    </button>
    <div class="dock-list" id="dock-site-list">
        <button class="dock-item" onclick="location.href='/'"><span class="emoji">🏠</span> Home</button>
        <button class="dock-item" onclick="location.href='/blog'"><span class="emoji">📰</span> Blog</button>
        <button class="dock-item" onclick="location.href='/games'"><span class="emoji">🎮</span> Games</button>
        <button class="dock-item" onclick="window.open('https://chatbot.akkuapps.in/', '_blank')"><span class="emoji">🤖</span> AI Studio</button>
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
        dock.style.cursor = 'grabbing';
    });
    document.addEventListener('mousemove', e => {
        if (!drag) return;
        dock.style.left = (e.clientX - ox) + 'px'; dock.style.top = (e.clientY - oy) + 'px';
        dock.style.right = 'auto'; dock.style.bottom = 'auto';
    });
    document.addEventListener('mouseup', () => { drag = false; dock.style.cursor = 'grab'; });
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