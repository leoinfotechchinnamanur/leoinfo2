<?php
// admin/collectionbox.php — AkkuCollectionBox Treasury Dashboard
// Admin-only: View treasury, redistribute, burn coins, export reports
// Mobile responsive + dark theme matching economy.php

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'AkkuCollectionBox — Treasury';
include __DIR__ . '/../includes/header.php';

// Initialize treasury
// ✅ CORRECT
$treasury = new AkkuCollectionBox($pdo, (int)$user['user_id']);

// Handle admin actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // ========== REDISTRIBUTE COINS ==========
    if ($action === 'redistribute') {
        $amount = floatval($_POST['redist_amount'] ?? 0);
        $targetType = $_POST['redist_target'] ?? '';
        $reason = trim($_POST['redist_reason'] ?? '');

        if ($amount <= 0) {
            $error = 'Amount must be greater than 0';
        } elseif (empty($reason)) {
            $error = 'Reason is required for redistribution';
        } else {
            try {
                $pdo->beginTransaction();

                $currentBalance = $treasury->getBalance();
                if ($currentBalance < $amount) {
                    throw new Exception("Insufficient treasury balance. Available: " . number_format($currentBalance, 2));
                }

                $recipients = 0;

                if ($targetType === 'all_users') {
                    // Distribute equally to all active users
                    $users = $pdo->query("SELECT user_id FROM users WHERE status = 'active' OR status IS NULL")->fetchAll();
                    $perUser = round($amount / count($users), 4);

                    foreach ($users as $u) {
                        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
                            ->execute([$perUser, $u['user_id']]);

                        $pdo->prepare("
                            INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                            VALUES (?, ?, 'treasury_redist', ?, (SELECT coin_balance FROM users WHERE user_id = ?), ?, NOW())
                        ")->execute([generateUUID(), $u['user_id'], $perUser, $u['user_id'], "Treasury redistribution: $reason"]);
                    }
                    $recipients = count($users);

                } elseif ($targetType === 'specific_user') {
                    $targetUserId = intval($_POST['redist_user_id'] ?? 0);
                    if ($targetUserId <= 0) {
                        throw new Exception('Invalid user ID');
                    }

                    $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
                        ->execute([$amount, $targetUserId]);

                    $pdo->prepare("
                        INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                        VALUES (?, ?, 'treasury_redist', ?, (SELECT coin_balance FROM users WHERE user_id = ?), ?, NOW())
                    ")->execute([generateUUID(), $targetUserId, $amount, $targetUserId, "Treasury redistribution: $reason"]);
                    $recipients = 1;
                }

                // Record treasury outflow
                $newBalance = $currentBalance - $amount;
                $pdo->prepare("
                    INSERT INTO akku_collection_box 
                    (transaction_type, source_user_id, fee_amount, balance_after, description, admin_id)
                    VALUES ('redistribution', ?, ?, ?, ?, ?)
                ")->execute([$user['user_id'], -$amount, $newBalance, "Redistribution: $reason ($recipients recipients)", $user['user_id']]);

                $pdo->commit();
                $message = "✅ Redistributed " . number_format($amount, 2) . " coins to " . $recipients . " recipient(s)";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Redistribution failed: ' . $e->getMessage();
            }
        }
    }

    // ========== BURN COINS ==========
    elseif ($action === 'burn') {
        $amount = floatval($_POST['burn_amount'] ?? 0);
        $reason = trim($_POST['burn_reason'] ?? '');

        if ($amount <= 0) {
            $error = 'Burn amount must be greater than 0';
        } elseif (empty($reason)) {
            $error = 'Burn reason is required';
        } else {
            try {
                $currentBalance = $treasury->getBalance();
                if ($currentBalance < $amount) {
                    throw new Exception("Insufficient balance. Available: " . number_format($currentBalance, 2));
                }

                $newBalance = $currentBalance - $amount;
                $pdo->prepare("
                    INSERT INTO akku_collection_box 
                    (transaction_type, source_user_id, fee_amount, balance_after, description, admin_id)
                    VALUES ('burn', ?, ?, ?, ?, ?)
                ")->execute([$user['user_id'], -$amount, $newBalance, "Burned: $reason", $user['user_id']]);

                $message = "🔥 Burned " . number_format($amount, 2) . " coins permanently";
            } catch (Exception $e) {
                $error = 'Burn failed: ' . $e->getMessage();
            }
        }
    }

    // ========== ADJUST BALANCE (emergency) ==========
    elseif ($action === 'adjust_balance') {
        $newBalance = floatval($_POST['new_balance'] ?? 0);
        $reason = trim($_POST['adjust_reason'] ?? '');

        if (empty($reason)) {
            $error = 'Reason required for balance adjustment';
        } else {
            $currentBalance = $treasury->getBalance();
            $diff = $newBalance - $currentBalance;

            $pdo->prepare("
                INSERT INTO akku_collection_box 
                (transaction_type, source_user_id, fee_amount, balance_after, description, admin_id)
                VALUES ('admin_adjustment', ?, ?, ?, ?, ?)
            ")->execute([$user['user_id'], $diff, $newBalance, "Admin adjustment: $reason", $user['user_id']]);

            $message = "⚖️ Balance adjusted from " . number_format($currentBalance, 2) . " to " . number_format($newBalance, 2);
        }
    }
}

// Load data
$currentBalance = $treasury->getBalance();
$report = $treasury->getTreasuryReport('all');
$periodReport = $treasury->getTreasuryReport('MONTH');

// Recent transactions
$recentTxns = [];
try {
    $recentTxns = $pdo->query("
        SELECT cb.*, u.name as source_name, u2.name as target_name
        FROM akku_collection_box cb
        LEFT JOIN users u ON cb.source_user_id = u.user_id
        LEFT JOIN users u2 ON cb.target_user_id = u2.user_id
        ORDER BY cb.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (Exception $e) {}

// Daily stats (last 30 days)
$dailyStats = [];
try {
    $dailyStats = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as txns,
            SUM(CASE WHEN fee_amount > 0 THEN fee_amount ELSE 0 END) as income,
            SUM(CASE WHEN fee_amount < 0 THEN ABS(fee_amount) ELSE 0 END) as outflow
        FROM akku_collection_box
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();
} catch (Exception $e) {}

// Top revenue sources
$topSources = [];
try {
    $topSources = $pdo->query("
        SELECT 
            transaction_type,
            COUNT(*) as count,
            SUM(fee_amount) as total
        FROM akku_collection_box
        WHERE fee_amount > 0
        GROUP BY transaction_type
        ORDER BY total DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {}

$csrf_token = generateCSRFToken();
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
        --yellow: #f59e0b;
        --purple: #a855f7;
        --orange: #f97316;
    }

    .cb-wrap { max-width: 1400px; margin: 0 auto; padding: 16px; }
    .cb-header { margin-bottom: 24px; }
    .cb-header h1 { font-size: 28px; color: var(--bright); font-weight: 800; }
    .cb-header p { color: var(--text); font-size: 14px; margin-top: 4px; }

    /* Balance Hero */
    .balance-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .balance-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%);
        animation: pulse 4s ease-in-out infinite;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 0.8; }
    }
    .balance-hero .label { 
        color: var(--text); 
        font-size: 13px; 
        text-transform: uppercase; 
        letter-spacing: 2px; 
        font-weight: 600;
        position: relative;
    }
    .balance-hero .amount { 
        font-size: 56px; 
        font-weight: 800; 
        color: var(--green);
        margin: 12px 0;
        position: relative;
        text-shadow: 0 0 30px rgba(16,185,129,0.3);
    }
    .balance-hero .subtitle { 
        color: var(--text); 
        font-size: 14px;
        position: relative;
    }

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
        transition: all 0.2s;
    }
    .stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
    .stat-card .icon { font-size: 32px; margin-bottom: 12px; }
    .stat-card .value { font-size: 28px; font-weight: 800; color: var(--bright); }
    .stat-card .label { font-size: 12px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
    .stat-card .change { font-size: 12px; margin-top: 6px; font-weight: 600; }
    .change-up { color: var(--green); }
    .change-down { color: var(--red); }

    /* Tabs */
    .tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
    }
    .tab-btn {
        padding: 10px 18px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tab-btn:hover, .tab-btn.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Cards */
    .form-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .form-card h2 {
        font-size: 18px;
        color: var(--bright);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }
    .form-card h2 .emoji { font-size: 22px; }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .form-group { margin-bottom: 12px; }
    .form-group label {
        display: block;
        font-size: 13px;
        color: var(--text);
        margin-bottom: 6px;
        font-weight: 500;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: #1a1a22;
        color: var(--bright);
        font-size: 14px;
        font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }

    .submit-btn {
        padding: 12px 24px;
        border-radius: 10px;
        border: none;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        color: white;
        background: var(--accent);
        margin-top: 8px;
        transition: opacity 0.2s;
    }
    .submit-btn:hover { opacity: 0.9; }
    .submit-btn.red { background: var(--red); }
    .submit-btn.orange { background: var(--orange); }
    .submit-btn.purple { background: var(--purple); }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .data-table th {
        text-align: left;
        padding: 12px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
        color: var(--bright);
    }
    .data-table tr:hover td { background: #15151d; }
    .data-table .type-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .type-gift_sending { background: rgba(168,85,247,0.2); color: #c084fc; }
    .type-gift_conversion { background: rgba(99,102,241,0.2); color: #818cf8; }
    .type-game_commission { background: rgba(16,185,129,0.2); color: #34d399; }
    .type-token_conversion { background: rgba(245,158,11,0.2); color: #fbbf24; }
    .type-redistribution { background: rgba(239,68,68,0.2); color: #f87171; }
    .type-burn { background: rgba(249,115,22,0.2); color: #fb923c; }
    .type-admin_adjustment { background: rgba(161,161,170,0.2); color: #d4d4d8; }

    .amount-positive { color: var(--green); font-weight: 700; }
    .amount-negative { color: var(--red); font-weight: 700; }

    /* Charts placeholder */
    .chart-container {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 20px;
        height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text);
    }

    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid var(--green); color: var(--green); }
    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid var(--red); color: var(--red); }

    /* Revenue breakdown bars */
    .revenue-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }
    .revenue-bar .bar-label {
        width: 140px;
        font-size: 13px;
        color: var(--text);
        font-weight: 500;
    }
    .revenue-bar .bar-track {
        flex: 1;
        height: 24px;
        background: #1a1a22;
        border-radius: 6px;
        overflow: hidden;
    }
    .revenue-bar .bar-fill {
        height: 100%;
        border-radius: 6px;
        transition: width 0.5s ease;
    }
    .revenue-bar .bar-value {
        width: 80px;
        text-align: right;
        font-size: 13px;
        font-weight: 700;
        color: var(--bright);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .cb-wrap { padding: 10px; }
        .balance-hero { padding: 24px; }
        .balance-hero .amount { font-size: 36px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 16px; }
        .form-grid { grid-template-columns: 1fr; }
        .tabs { gap: 6px; }
        .tab-btn { padding: 8px 12px; font-size: 12px; }
        .data-table { font-size: 11px; }
        .data-table th, .data-table td { padding: 8px; }
        .revenue-bar .bar-label { width: 100px; font-size: 11px; }
    }
</style>

<div class="cb-wrap">
    <div class="cb-header">
        <h1><span class="emoji">🏦</span> AkkuCollectionBox — Treasury</h1>
        <p>Central commission accumulator & coin redistribution hub</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Balance Hero -->
    <div class="balance-hero">
        <div class="label">💰 Current Treasury Balance</div>
        <div class="amount">🪙 <?= number_format($currentBalance, 2) ?></div>
        <div class="subtitle">AkkuCollectionBox — All fees & commissions accumulate here</div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon">📊</div>
            <div class="value"><?= number_format($report['total_transactions'] ?? 0, 0) ?></div>
            <div class="label">Total Transactions</div>
        </div>
        <div class="stat-card">
            <div class="icon">💵</div>
            <div class="value"><?= number_format($report['total_collected'] ?? 0, 2) ?></div>
            <div class="label">All-Time Collected</div>
        </div>
        <div class="stat-card">
            <div class="icon">📅</div>
            <div class="value"><?= number_format($periodReport['total_collected'] ?? 0, 2) ?></div>
            <div class="label">This Month</div>
        </div>
        <div class="stat-card">
            <div class="icon">🔥</div>
            <div class="value"><?= number_format(array_sum(array_column(array_filter($recentTxns, fn($t) => $t['transaction_type'] === 'burn'), 'fee_amount')), 2) ?></div>
            <div class="label">Total Burned</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('overview')">📊 Overview</button>
        <button class="tab-btn" onclick="showTab('transactions')">📋 Transactions</button>
        <button class="tab-btn" onclick="showTab('actions')">⚡ Actions</button>
        <button class="tab-btn" onclick="showTab('daily')">📈 Daily Stats</button>
    </div>

    <!-- OVERVIEW TAB -->
    <div id="tab-overview" class="tab-content active">
        <div class="form-card">
            <h2><span class="emoji">📊</span> Revenue Breakdown by Source</h2>
            <?php 
            $maxTotal = max(array_column($topSources, 'total')) ?: 1;
            $colors = ['#a855f7', '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#f97316', '#06b6d4', '#ec4899'];
            foreach ($topSources as $i => $src): 
                $pct = ($src['total'] / $maxTotal) * 100;
                $color = $colors[$i % count($colors)];
                $label = str_replace('_', ' ', ucwords($src['transaction_type']));
            ?>
            <div class="revenue-bar">
                <div class="bar-label"><?= $label ?></div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?= $pct ?>%; background: <?= $color ?>"></div>
                </div>
                <div class="bar-value">🪙 <?= number_format($src['total'], 2) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topSources)): ?>
                <p style="color: var(--text); text-align: center;">No revenue data yet. Start collecting fees!</p>
            <?php endif; ?>
        </div>

        <div class="form-card">
            <h2><span class="emoji">ℹ️</span> How AkkuCollectionBox Works</h2>
            <div style="color: var(--text); line-height: 1.8; font-size: 14px;">
                <p><strong>🎁 Gift Sending:</strong> 10 coins flat fee per gift sent. Fee comes from sender wallet.</p>
                <p><strong>🔄 Gift Conversion:</strong> 10% of gift value deducted when converting gift → coins.</p>
                <p><strong>🎮 Game Commission:</strong> 10% of game winnings auto-routed here.</p>
                <p><strong>🏆 Token Conversion:</strong> 10% fee on token → coin conversions.</p>
                <p><strong>🆔 Post ID Charges:</strong> Fees for premium post features.</p>
                <p><strong>⚠️ Penalties:</strong> Fines for policy violations.</p>
                <p><strong>🎫 AkkuTickets:</strong> Conversion fees from ticket → coin.</p>
                <p style="margin-top: 12px; padding: 12px; background: rgba(99,102,241,0.1); border-radius: 8px;">
                    <strong>Fee-Free:</strong> Real Money→Coins (UPI) and Admin Shop purchases are NOT taxed.
                </p>
            </div>
        </div>
    </div>

    <!-- TRANSACTIONS TAB -->
    <div id="tab-transactions" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">📋</span> Recent Treasury Transactions</h2>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTxns as $txn): ?>
                        <tr>
                            <td>
                                <span class="type-badge type-<?= $txn['transaction_type'] ?>">
                                    <?= str_replace('_', ' ', $txn['transaction_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($txn['source_name'] ?? 'System') ?></td>
                            <td><?= htmlspecialchars($txn['target_name'] ?? '-') ?></td>
                            <td class="<?= $txn['fee_amount'] >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $txn['fee_amount'] >= 0 ? '+' : '' ?><?= number_format($txn['fee_amount'], 2) ?>
                            </td>
                            <td><?= number_format($txn['balance_after'], 2) ?></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($txn['description'] ?? '') ?>
                            </td>
                            <td><?= date('M d, H:i', strtotime($txn['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentTxns)): ?>
                            <tr><td colspan="7" style="text-align: center; color: var(--text);">No transactions yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ACTIONS TAB -->
    <div id="tab-actions" class="tab-content">
        <!-- Redistribute -->
        <div class="form-card">
            <h2><span class="emoji">🎁</span> Redistribute Coins</h2>
            <form method="POST" onsubmit="return confirm('Confirm redistribution?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="redistribute">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Amount to Distribute</label>
                        <input type="number" name="redist_amount" step="0.01" min="1" required placeholder="e.g. 1000">
                    </div>
                    <div class="form-group">
                        <label>Target</label>
                        <select name="redist_target" id="redistTarget" onchange="toggleUserId()">
                            <option value="all_users">All Active Users (split equally)</option>
                            <option value="specific_user">Specific User</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="userIdField" style="display: none;">
                    <label>Target User ID</label>
                    <input type="number" name="redist_user_id" placeholder="Enter user ID">
                </div>
                <div class="form-group">
                    <label>Reason / Memo</label>
                    <input type="text" name="redist_reason" required placeholder="e.g. Weekly community reward">
                </div>
                <button type="submit" class="submit-btn purple">🎁 Redistribute Coins</button>
            </form>
        </div>

        <!-- Burn -->
        <div class="form-card">
            <h2><span class="emoji">🔥</span> Burn Coins</h2>
            <p style="color: var(--text); margin-bottom: 16px; font-size: 13px;">
                Permanently remove coins from circulation. This reduces total supply.
            </p>
            <form method="POST" onsubmit="return confirm('WARNING: This permanently destroys coins. Confirm?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="burn">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Amount to Burn</label>
                        <input type="number" name="burn_amount" step="0.01" min="1" required placeholder="e.g. 500">
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <input type="text" name="burn_reason" required placeholder="e.g. Quarterly token burn">
                    </div>
                </div>
                <button type="submit" class="submit-btn red">🔥 Burn Coins</button>
            </form>
        </div>

        <!-- Adjust Balance -->
        <div class="form-card">
            <h2><span class="emoji">⚖️</span> Emergency Balance Adjustment</h2>
            <p style="color: var(--text); margin-bottom: 16px; font-size: 13px;">
                Only use this to correct discrepancies. All adjustments are logged.
            </p>
            <form method="POST" onsubmit="return confirm('Confirm balance adjustment?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="adjust_balance">

                <div class="form-grid">
                    <div class="form-group">
                        <label>New Balance</label>
                        <input type="number" name="new_balance" step="0.01" required placeholder="e.g. 10000">
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <input type="text" name="adjust_reason" required placeholder="e.g. Fix calculation error">
                    </div>
                </div>
                <button type="submit" class="submit-btn orange">⚖️ Adjust Balance</button>
            </form>
        </div>
    </div>

    <!-- DAILY STATS TAB -->
    <div id="tab-daily" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">📈</span> Daily Activity (Last 30 Days)</h2>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Income</th>
                            <th>Outflow</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyStats as $day): 
                            $net = $day['income'] - $day['outflow'];
                        ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                            <td><?= number_format($day['txns'], 0) ?></td>
                            <td class="amount-positive">+<?= number_format($day['income'], 2) ?></td>
                            <td class="amount-negative">-<?= number_format($day['outflow'], 2) ?></td>
                            <td class="<?= $net >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $net >= 0 ? '+' : '' ?><?= number_format($net, 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dailyStats)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text);">No daily data yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    event.target.classList.add('active');
}

function toggleUserId() {
    const target = document.getElementById('redistTarget').value;
    document.getElementById('userIdField').style.display = target === 'specific_user' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>