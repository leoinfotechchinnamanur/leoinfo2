<?php if (!defined('AKKUAPPS_LOADED')) exit;
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}
$user = getCurrentUser();
$isLoggedIn = !empty($user);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'AkkuApps'; ?> - AkkuApps.in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' }
    </script>
    <style>
        :root {
            --bg: #08080c;
            --card: #0f0f14;
            --border: #1a1a22;
            --text: #a1a1aa;
            --text-bright: #ffffff;
            --accent: #6366f1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            min-height: 100vh;
        }
        .site-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 16px; border-bottom: 1px solid var(--border);
            background: var(--card); position: sticky; top: 0; z-index: 100;
            flex-wrap: wrap; gap: 8px;
        }
        .site-logo { font-size: 18px; font-weight: 800; color: var(--text-bright); text-decoration: none; }
        .site-logo span { color: var(--accent); }
        .site-nav { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .site-nav a, .site-nav button {
            padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border);
            background: transparent; color: var(--text); font-size: 12px;
            cursor: pointer; text-decoration: none; font-family: inherit;
            transition: all 0.2s; font-weight: 500;
        }
        .site-nav a:hover, .site-nav button:hover {
            border-color: var(--accent); color: var(--text-bright);
        }
        .user-chip {
            display: flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            border: 1px solid var(--border); background: #15151d;
        }
        .user-chip-avatar {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--accent); display: flex; align-items: center;
            justify-content: center; color: white; font-weight: bold; font-size: 11px;
        }
        .user-chip-coins { color: var(--accent); font-weight: 700; font-size: 11px; }
        
        /* CRITICAL FIX: Remove flex from body, use normal flow */
        .content-wrap { 
            max-width: 1100px; 
            margin: 0 auto; 
            padding: 16px; 
            width: 100%;
            min-height: 200px; /* DEBUG: ensure it's visible */
            border: 2px dashed red; /* DEBUG: visible border */
        }
        
        .site-footer {
            text-align: center;
            padding: 16px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--text);
            opacity: 0.7;
        }
        
        @media (max-width: 640px) {
            .site-header { padding: 10px 12px; }
            .site-logo { font-size: 16px; }
            .site-nav a, .site-nav button { padding: 5px 10px; font-size: 11px; }
            .user-chip { padding: 4px 8px; }
            .user-chip-avatar { width: 22px; height: 22px; font-size: 10px; }
            .content-wrap { padding: 12px; }
        }
    </style>
    <script>
    // FIX Medium 3: Function to update header coin display after AJAX transactions
    function updateHeaderCoins(newBalance) {
        const coinEl = document.querySelector('.user-chip-coins');
        if (coinEl) {
            coinEl.textContent = '🪙' + parseFloat(newBalance).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2});
        }
    }
    </script>
</head>
<body>

<header class="site-header">
    <a href="/" class="site-logo">akkuapps<span>.in</span></a>
    <nav class="site-nav">
        <?php if ($isLoggedIn): ?>
            <div class="user-chip">
                <div class="user-chip-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <span style="color:var(--text-bright); font-size:12px; font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="user-chip-coins">🪙<?php echo number_format($user['coin_balance'] ?? 0, 0); ?></span>
            </div>
            <a href="/user/dashboard.php">Dashboard</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="/admin/dashboard.php">🛡️ Admin</a>
            <?php endif; ?>
            <a href="/auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="/auth/login.php">🔑 Login</a>
            <a href="/auth/register.php">✨ Join</a>
        <?php endif; ?>
    </nav>
</header>

<div class="content-wrap">