<?php
// user/creator-dashboard.php — Creator analytics & earnings
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Creator Dashboard';

// Only creators (users with subscribers or posts) see this, but allow all for now
// Get subscriber stats
$subscriberStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_subscribers,
            SUM(CASE WHEN status = 'active' AND expires_at > NOW() THEN 1 ELSE 0 END) as active_subscribers,
            SUM(price_paid) as total_earnings
        FROM creator_subscriptions
        WHERE creator_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $subscriberStats = $stmt->fetch();
} catch (Exception $e) {}

// Monthly earnings (last 6 months)
$monthlyEarnings = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as subs,
            SUM(price_paid) as earnings
        FROM creator_subscriptions
        WHERE creator_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute([$user['user_id']]);
    $monthlyEarnings = $stmt->fetchAll();
} catch (Exception $e) {}

// Top posts by earnings
$topPosts = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.post_id,
            p.content,
            p.view_count,
            p.likes_count,
            p.comments_count,
            p.coins_earned,
            COUNT(DISTINCT l.user_id) as unique_likers,
            COUNT(DISTINCT c.comment_id) as total_comments
        FROM user_posts p
        LEFT JOIN post_likes l ON p.post_id = l.post_id
        LEFT JOIN post_comments c ON p.post_id = c.post_id
        WHERE p.user_id = ? AND p.status = 'active'
        GROUP BY p.post_id
        ORDER BY p.coins_earned DESC, p.view_count DESC
        LIMIT 10
    ");
    $stmt->execute([$user['user_id']]);
    $topPosts = $stmt->fetchAll();
} catch (Exception $e) {}

// Recent transactions (creator-related)
$recentTxns = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM coin_transactions 
        WHERE user_id = ? AND reference_type IN ('subscription_earned', 'like_received', 'comment_received', 'post_view', 'repost_received')
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user['user_id']]);
    $recentTxns = $stmt->fetchAll();
} catch (Exception $e) {}

// Subscription tiers/pricing info
$subscriptionPrice = 50; // Default, could be configurable

include __DIR__ . '/../includes/header.php';
?>

<style>
:root {
    --bg: #08080c; --card: #0f0f14; --border: #1a1a22;
    --text: #a1a1aa; --bright: #ffffff; --accent: #6366f1;
    --green: #10b981; --purple: #a855f7; --yellow: #f59e0b;
}
.cr-wrap { max-width: 1100px; margin: 0 auto; padding: 16px; }
.cr-header { margin-bottom: 24px; }
.cr-header h1 { font-size: 28px; color: var(--bright); font-weight: 800; }
.cr-header p { color: var(--text); font-size: 14px; margin-top: 4px; }

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s;
}
.stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
.stat-card .emoji { font-size: 32px; display: block; margin-bottom: 10px; }
.stat-card .value { font-size: 32px; font-weight: 800; color: var(--bright); margin: 8px 0; }
.stat-card .label { font-size: 12px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
.stat-card.accent .value { color: var(--accent); }
.stat-card.green .value { color: var(--green); }
.stat-card.purple .value { color: var(--purple); }
.stat-card.yellow .value { color: var(--yellow); }

/* Sections */
.section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}
.section h2 {
    font-size: 18px;
    color: var(--bright);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
}

/* Chart bars */
.month-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.month-bar .month { width: 60px; font-size: 13px; color: var(--text); font-weight: 600; }
.month-bar .bar-track { flex: 1; height: 28px; background: #1a1a22; border-radius: 6px; overflow: hidden; }
.month-bar .bar-fill { height: 100%; border-radius: 6px; transition: width 0.5s ease; }
.month-bar .amount { width: 80px; text-align: right; font-size: 14px; font-weight: 700; color: var(--green); }

/* Post table */
.post-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.post-table th { text-align: left; padding: 12px; color: var(--text); border-bottom: 1px solid var(--border); font-weight: 600; font-size: 11px; text-transform: uppercase; }
.post-table td { padding: 12px; border-bottom: 1px solid var(--border); color: var(--bright); }
.post-table tr:hover td { background: #15151d; }
.post-preview { max-width: 250px; font-size: 13px; color: var(--text); line-height: 1.4; }

/* Transaction list */
.txn-list { display: flex; flex-direction: column; gap: 8px; }
.txn-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #15151d;
    border-radius: 10px;
}
.txn-icon { font-size: 20px; }
.txn-details { flex: 1; }
.txn-title { font-size: 13px; color: var(--bright); font-weight: 600; }
.txn-meta { font-size: 12px; color: var(--text); }
.txn-amount { font-size: 14px; font-weight: 800; color: var(--green); }

/* CTA */
.cta-box {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    text-align: center;
    margin-bottom: 20px;
}
.cta-box h3 { font-size: 20px; color: var(--bright); margin-bottom: 8px; }
.cta-box p { color: var(--text); margin-bottom: 16px; }
.cta-btn {
    display: inline-block;
    padding: 12px 28px;
    border-radius: 10px;
    background: var(--accent);
    color: white;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 16px; }
    .stat-card .value { font-size: 24px; }
    .post-table { font-size: 11px; }
    .post-table th, .post-table td { padding: 8px; }
}
</style>

<div class="cr-wrap">
    <div class="cr-header">
        <h1><span class="emoji">📊</span> Creator Dashboard</h1>
        <p>Track your earnings, subscribers, and top-performing content</p>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card accent">
            <span class="emoji">👥</span>
            <div class="value"><?= number_format($subscriberStats['active_subscribers'] ?? 0) ?></div>
            <div class="label">Active Subscribers</div>
        </div>
        <div class="stat-card">
            <span class="emoji">📊</span>
            <div class="value"><?= number_format($subscriberStats['total_subscribers'] ?? 0) ?></div>
            <div class="label">Total Subscribers</div>
        </div>
        <div class="stat-card green">
            <span class="emoji">🪙</span>
            <div class="value"><?= number_format($subscriberStats['total_earnings'] ?? 0, 0) ?></div>
            <div class="label">Total Earnings</div>
        </div>
        <div class="stat-card purple">
            <span class="emoji">📱</span>
            <div class="value"><?= number_format($user['coin_balance'] ?? 0, 0) ?></div>
            <div class="label">Current Balance</div>
        </div>
    </div>

    <!-- Subscription CTA -->
    <div class="cta-box">
        <h3>💎 Subscription Price: 🪙 <?= number_format($subscriptionPrice, 0) ?> / month</h3>
        <p>Subscribers get exclusive access to your premium content. You keep 90% (platform fee: 10%).</p>
        <a href="/user/settings.php" class="cta-btn">⚙️ Manage Subscription Settings</a>
    </div>

    <!-- Monthly Earnings -->
    <div class="section">
        <h2><span class="emoji">📈</span> Monthly Earnings (Last 6 Months)</h2>
        <?php 
        $maxEarnings = max(array_column($monthlyEarnings, 'earnings')) ?: 1;
        foreach ($monthlyEarnings as $m): 
            $pct = ($m['earnings'] / $maxEarnings) * 100;
            $monthName = date('M Y', strtotime($m['month'] . '-01'));
        ?>
        <div class="month-bar">
            <div class="month"><?= $monthName ?></div>
            <div class="bar-track">
                <div class="bar-fill" style="width: <?= $pct ?>%; background: var(--green)"></div>
            </div>
            <div class="amount">+<?= number_format($m['earnings'], 0) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($monthlyEarnings)): ?>
            <p style="color: var(--text); text-align: center; padding: 20px;">No subscription earnings yet. Promote your profile!</p>
        <?php endif; ?>
    </div>

    <!-- Top Posts -->
    <div class="section">
        <h2><span class="emoji">🔥</span> Top Performing Posts</h2>
        <div style="overflow-x: auto;">
            <table class="post-table">
                <thead>
                    <tr>
                        <th>Post Preview</th>
                        <th>Views</th>
                        <th>Likes</th>
                        <th>Comments</th>
                        <th>Coins Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPosts as $post): ?>
                    <tr>
                        <td>
                            <div class="post-preview">
                                <?= nl2br(htmlspecialchars(substr($post['content'], 0, 80))) ?>
                                <?= strlen($post['content']) > 80 ? '...' : '' ?>
                            </div>
                        </td>
                        <td>👁️ <?= number_format($post['view_count']) ?></td>
                        <td>❤️ <?= number_format($post['likes_count']) ?></td>
                        <td>💬 <?= number_format($post['total_comments']) ?></td>
                        <td style="color: var(--green); font-weight: 700;">🪙 <?= number_format($post['coins_earned'] ?? 0, 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topPosts)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text); padding: 20px;">No posts yet. <a href="/user/posts/create.php" style="color: var(--accent);">Create one!</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Earnings -->
    <div class="section">
        <h2><span class="emoji">💰</span> Recent Earnings Activity</h2>
        <div class="txn-list">
            <?php foreach ($recentTxns as $t): 
                $emoji = match($t['reference_type']) {
                    'subscription_earned' => '💎',
                    'like_received' => '❤️',
                    'comment_received' => '💬',
                    'post_view' => '👁️',
                    'repost_received' => '🔄',
                    default => '🪙'
                };
                $title = match($t['reference_type']) {
                    'subscription_earned' => 'Subscription Earnings',
                    'like_received' => 'Like Reward',
                    'comment_received' => 'Comment Reward',
                    'post_view' => 'View Reward',
                    'repost_received' => 'Repost Reward',
                    default => 'Earning'
                };
            ?>
            <div class="txn-item">
                <span class="txn-icon"><?= $emoji ?></span>
                <div class="txn-details">
                    <div class="txn-title"><?= $title ?></div>
                    <div class="txn-meta"><?= date('M d, H:i', strtotime($t['created_at'])) ?> • <?= htmlspecialchars($t['description'] ?? '') ?></div>
                </div>
                <div class="txn-amount">+<?= number_format($t['amount'], 2) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentTxns)): ?>
                <p style="color: var(--text); text-align: center; padding: 20px;">No earnings yet. Start creating content!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>