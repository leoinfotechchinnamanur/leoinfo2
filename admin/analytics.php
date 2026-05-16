<?php
// admin/analytics.php – Platform Analytics Dashboard

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Analytics';

// Daily stats (last 30 days)
$dailyStats = [];
try {
    $dailyStats = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_users,
            SUM(CASE WHEN reference_type IN ('like_given', 'comment_given') THEN ABS(amount) ELSE 0 END) as interactions
        FROM users
        LEFT JOIN coin_transactions ON DATE(coin_transactions.created_at) = DATE(users.created_at)
        WHERE users.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();
} catch (Exception $e) {}

// Top earners
$topEarners = [];
try {
    $topEarners = $pdo->query("
        SELECT user_id, name, coin_balance,
               (SELECT COUNT(*) FROM user_posts WHERE user_id = users.user_id) as posts
        FROM users
        ORDER BY coin_balance DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {}

// Coin flow (last 7 days)
$coinFlow = [];
try {
    $coinFlow = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as inflow,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as outflow
        FROM coin_transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --bg: #08080c;
        --card: #0f0f14;
        --border: #1a1a22;
        --text: #a1a1aa;
        --bright: #ffffff;
        --accent: #6366f1;
        --green: #10b981;
        --red: #ef4444;
    }
    .analytics-wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .analytics-header { margin-bottom: 20px; }
    .analytics-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }

    .chart-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .chart-card h2 {
        font-size: 18px;
        color: var(--bright);
        margin-bottom: 16px;
        font-weight: 700;
    }

    .bar-chart {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .bar-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .bar-label {
        width: 80px;
        font-size: 12px;
        color: var(--text);
        text-align: right;
        font-weight: 500;
    }
    .bar-track {
        flex: 1;
        height: 24px;
        background: #1a1a22;
        border-radius: 6px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 6px;
        transition: width 0.5s ease;
    }
    .bar-value {
        width: 60px;
        font-size: 12px;
        font-weight: 700;
        color: var(--bright);
    }

    .top-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .top-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #1a1a22;
        border-radius: 10px;
    }
    .top-rank {
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--accent);
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 800; font-size: 14px;
    }
    .top-info { flex: 1; }
    .top-name { font-weight: 700; color: var(--bright); font-size: 14px; }
    .top-meta { font-size: 12px; color: var(--text); }
    .top-coins { color: var(--green); font-weight: 800; font-size: 16px; }

    @media (max-width: 768px) {
        .bar-label { width: 60px; font-size: 11px; }
        .bar-value { width: 50px; font-size: 11px; }
    }
</style>

<div class="analytics-wrap">
    <div class="analytics-header">
        <h1><span class="emoji">📊</span> Platform Analytics</h1>
    </div>

    <!-- Daily Activity -->
    <div class="chart-card">
        <h2>📅 Daily Activity (Last 30 Days)</h2>
        <div class="bar-chart">
            <?php 
            $maxUsers = max(array_column($dailyStats, 'new_users')) ?: 1;
            foreach ($dailyStats as $day): 
                $pct = ($day['new_users'] / $maxUsers) * 100;
            ?>
            <div class="bar-row">
                <div class="bar-label"><?= date('M d', strtotime($day['date'])) ?></div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?= $pct ?>%; background: var(--accent);"></div>
                </div>
                <div class="bar-value"><?= $day['new_users'] ?> users</div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($dailyStats)): ?>
                <p style="color: var(--text); text-align: center;">No data yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Coin Flow -->
    <div class="chart-card">
        <h2>💰 Coin Flow (Last 7 Days)</h2>
        <div class="bar-chart">
            <?php 
            $maxFlow = max(array_merge(array_column($coinFlow, 'inflow'), array_column($coinFlow, 'outflow'))) ?: 1;
            foreach ($coinFlow as $flow): 
            ?>
            <div class="bar-row">
                <div class="bar-label"><?= date('M d', strtotime($flow['date'])) ?></div>
                <div style="flex: 1; display: flex; gap: 4px;">
                    <div class="bar-track" style="flex: 1;">
                        <div class="bar-fill" style="width: <?= ($flow['inflow'] / $maxFlow) * 100 ?>%; background: var(--green);"></div>
                    </div>
                    <div class="bar-track" style="flex: 1;">
                        <div class="bar-fill" style="width: <?= ($flow['outflow'] / $maxFlow) * 100 ?>%; background: var(--red);"></div>
                    </div>
                </div>
                <div class="bar-value">+<?= number_format($flow['inflow'], 0) ?> / -<?= number_format($flow['outflow'], 0) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($coinFlow)): ?>
                <p style="color: var(--text); text-align: center;">No data yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Earners -->
    <div class="chart-card">
        <h2>🏆 Top Coin Holders</h2>
        <div class="top-list">
            <?php foreach ($topEarners as $i => $u): ?>
            <div class="top-item">
                <div class="top-rank"><?= $i + 1 ?></div>
                <div class="top-info">
                    <div class="top-name"><?= htmlspecialchars($u['name']) ?></div>
                    <div class="top-meta"><?= $u['posts'] ?> posts</div>
                </div>
                <div class="top-coins">🪙 <?= number_format($u['coin_balance'], 0) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topEarners)): ?>
                <p style="color: var(--text); text-align: center;">No users yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>