<?php
// user/wallet.php – User Wallet & Transaction History
// Dark theme, mobile responsive, shows coins, badges, gifts, tokens

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'My Wallet';

// Load user data
$inventory = getUserInventory($user['user_id']);
$tokens = getUserTokens($user['user_id']);

// Get recent transactions
$transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CASE 
                   WHEN t.amount > 0 THEN 'income'
                   WHEN t.amount < 0 THEN 'expense'
                   ELSE 'neutral'
               END as txn_type
        FROM coin_transactions t
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id']]);
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {}

// Get coin packages for buying
$packages = [];
try {
    $packages = $pdo->query("SELECT * FROM coin_packages WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
} catch (Exception $e) {}

// Get gift history
$giftHistory = getGiftHistory($user['user_id'], 'all');

// Get unread notifications
$notifications = getUnreadNotifications($user['user_id']);

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
        --yellow: #f59e0b;
        --purple: #a855f7;
    }

    .wallet-wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
    .wallet-header { margin-bottom: 20px; }
    .wallet-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }

    /* Balance Hero */
    .balance-card {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 32px;
        text-align: center;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .balance-card .label { color: var(--text); font-size: 13px; text-transform: uppercase; letter-spacing: 2px; font-weight: 600; }
    .balance-card .amount { font-size: 48px; font-weight: 800; color: var(--green); margin: 12px 0; text-shadow: 0 0 30px rgba(16,185,129,0.3); }
    .balance-card .subtitle { color: var(--text); font-size: 14px; }

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-mini {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        text-align: center;
    }
    .stat-mini .emoji { font-size: 24px; display: block; margin-bottom: 6px; }
    .stat-mini .value { font-size: 20px; font-weight: 800; color: var(--bright); }
    .stat-mini .label { font-size: 11px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

    /* Tabs */
    .tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
    }
    .tab-btn {
        padding: 8px 16px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text);
        font-size: 13px;
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

    /* Transaction List */
    .txn-list { display: flex; flex-direction: column; gap: 8px; }
    .txn-item {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s;
    }
    .txn-item:hover { border-color: var(--accent); }
    .txn-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0;
    }
    .txn-icon.income { background: rgba(16,185,129,0.15); }
    .txn-icon.expense { background: rgba(239,68,68,0.15); }
    .txn-icon.neutral { background: rgba(99,102,241,0.15); }
    .txn-details { flex: 1; min-width: 0; }
    .txn-title { font-size: 14px; color: var(--bright); font-weight: 600; margin-bottom: 2px; }
    .txn-meta { font-size: 12px; color: var(--text); }
    .txn-amount {
        font-size: 16px; font-weight: 800;
        flex-shrink: 0;
    }
    .txn-amount.income { color: var(--green); }
    .txn-amount.expense { color: var(--red); }
    .txn-amount.neutral { color: var(--text); }

    /* Inventory Grid */
    .items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
    }
    .item-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        text-align: center;
        transition: all 0.2s;
    }
    .item-card:hover { border-color: var(--accent); transform: translateY(-2px); }
    .item-card .emoji { font-size: 40px; display: block; margin-bottom: 8px; }
    .item-card h4 { font-size: 14px; color: var(--bright); font-weight: 700; margin-bottom: 4px; }
    .item-card p { font-size: 12px; color: var(--text); }
    .item-card .qty { 
        display: inline-block; background: var(--accent); color: white;
        padding: 2px 8px; border-radius: 8px; font-size: 11px; font-weight: 700; margin-top: 6px;
    }

    /* Buy Coins Section */
    .packages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
    }
    .pkg-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        transition: all 0.2s;
        cursor: pointer;
    }
    .pkg-card:hover { border-color: var(--green); transform: translateY(-2px); }
    .pkg-card .coins { font-size: 32px; font-weight: 800; color: var(--green); }
    .pkg-card .bonus { font-size: 13px; color: var(--yellow); font-weight: 600; margin: 4px 0; }
    .pkg-card .price { font-size: 20px; font-weight: 700; color: var(--bright); margin: 8px 0; }
    .pkg-card button {
        width: 100%; padding: 10px; border-radius: 10px; border: none;
        background: var(--green); color: white; font-weight: 700; cursor: pointer;
    }

    /* Notification Bell */
    .notif-bell {
        position: relative;
        cursor: pointer;
        padding: 8px;
    }
    .notif-bell .badge {
        position: absolute;
        top: 0; right: 0;
        background: var(--red); color: white;
        font-size: 10px; font-weight: 700;
        padding: 2px 6px; border-radius: 10px;
        min-width: 18px; text-align: center;
    }

    @media (max-width: 768px) {
        .stats-row { grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .stat-mini { padding: 12px; }
        .stat-mini .emoji { font-size: 20px; }
        .stat-mini .value { font-size: 16px; }
        .balance-card .amount { font-size: 36px; }
        .items-grid { grid-template-columns: repeat(2, 1fr); }
        .packages-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="wallet-wrap">
    <div class="wallet-header">
        <h1><span class="emoji">💰</span> My Wallet</h1>
    </div>

    <!-- Balance Hero -->
    <div class="balance-card">
        <div class="label">Available Balance</div>
        <div class="amount">🪙 <?= number_format($user['coin_balance'] ?? 0, 2) ?></div>
        <div class="subtitle">AkkuApps Coin Economy</div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-row">
        <div class="stat-mini">
            <span class="emoji">🎖️</span>
            <div class="value"><?= count($inventory['badges']) ?></div>
            <div class="label">Badges</div>
        </div>
        <div class="stat-mini">
            <span class="emoji">🎁</span>
            <div class="value"><?= count($inventory['gifts']) ?></div>
            <div class="label">Gifts</div>
        </div>
        <div class="stat-mini">
            <span class="emoji">🏆</span>
            <div class="value"><?= count($tokens) ?></div>
            <div class="label">Tokens</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('transactions')">📊 Transactions</button>
        <button class="tab-btn" onclick="showTab('inventory')">🎒 Inventory</button>
        <button class="tab-btn" onclick="showTab('buy')">💳 Buy Coins</button>
        <button class="tab-btn" onclick="showTab('gifts')">🎁 Gift History</button>
    </div>

    <!-- TRANSACTIONS TAB -->
    <div id="tab-transactions" class="tab-content active">
        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">Recent Transactions</h2>
        <div class="txn-list">
            <?php foreach ($transactions as $t): 
                $type = $t['txn_type'];
                $emoji = match($type) {
                    'income' => '⬇️',
                    'expense' => '⬆️',
                    default => '➡️'
                };
                $title = match($t['reference_type']) {
                    'like_given' => 'Liked a post',
                    'like_received' => 'Like reward',
                    'comment_given' => 'Commented',
                    'comment_received' => 'Comment reward',
                    'post_view' => 'View reward',
                    'purchase' => 'Purchase',
                    'upi_deposit' => 'UPI Deposit',
                    'gift_send_fee' => 'Gift sent',
                    'post_create' => 'Post creation fee',
                    'gift_conversion' => 'Gift converted',
                    'token_conversion' => 'Token converted',
                    'subscription' => 'Subscription',
                    'subscription_earned' => 'Sub earnings',
                    'repost_received' => 'Repost reward',
                    'admin_add' => 'Admin bonus',
                    default => ucfirst(str_replace('_', ' ', $t['reference_type']))
                };
            ?>
            <div class="txn-item">
                <div class="txn-icon <?= $type ?>"><?= $emoji ?></div>
                <div class="txn-details">
                    <div class="txn-title"><?= $title ?></div>
                    <div class="txn-meta"><?= date('M d, H:i', strtotime($t['created_at'])) ?> • Balance: <?= number_format($t['balance_after'], 2) ?></div>
                </div>
                <div class="txn-amount <?= $type ?>">
                    <?= $type === 'income' ? '+' : ($type === 'expense' ? '-' : '') ?><?= number_format(abs($t['amount']), 2) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <p style="color: var(--text); text-align: center; padding: 40px;">No transactions yet. Start interacting!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- INVENTORY TAB -->
    <div id="tab-inventory" class="tab-content">
        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">My Collection</h2>

        <h3 style="font-size: 14px; color: var(--text); margin: 16px 0 10px; text-transform: uppercase; letter-spacing: 1px;">Badges</h3>
        <div class="items-grid">
            <?php foreach ($inventory['badges'] as $b): ?>
            <div class="item-card">
                <span class="emoji"><?= $b['icon_url'] ?: '🎖️' ?></span>
                <h4><?= htmlspecialchars($b['name']) ?></h4>
                <p class="rarity rarity-<?= $b['rarity'] ?>" style="font-size: 10px; text-transform: uppercase; font-weight: 700;"><?= ucfirst($b['rarity']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($inventory['badges'])): ?>
                <p style="color: var(--text); grid-column: 1/-1; text-align: center;">No badges yet. Visit the shop!</p>
            <?php endif; ?>
        </div>

        <h3 style="font-size: 14px; color: var(--text); margin: 16px 0 10px; text-transform: uppercase; letter-spacing: 1px;">Gifts</h3>
        <div class="items-grid">
            <?php foreach ($inventory['gifts'] as $g): ?>
            <div class="item-card">
                <span class="emoji">🎁</span>
                <h4><?= htmlspecialchars($g['name']) ?></h4>
                <p>🪙 <?= number_format($g['coin_price'], 0) ?></p>
                <span class="qty">x<?= $g['quantity'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($inventory['gifts'])): ?>
                <p style="color: var(--text); grid-column: 1/-1; text-align: center;">No gifts yet. Send or receive some!</p>
            <?php endif; ?>
        </div>

        <h3 style="font-size: 14px; color: var(--text); margin: 16px 0 10px; text-transform: uppercase; letter-spacing: 1px;">Tokens</h3>
        <div class="items-grid">
            <?php foreach ($tokens as $t): ?>
            <div class="item-card">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: <?= $t['color_hex'] ?>; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 20px; font-weight: 800; color: white;">
                    <?= $t['symbol'] ?>
                </div>
                <h4><?= htmlspecialchars($t['name']) ?></h4>
                <p>🪙 <?= number_format($t['coin_value'], 0) ?> each</p>
                <span class="qty">x<?= $t['quantity'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($tokens)): ?>
                <p style="color: var(--text); grid-column: 1/-1; text-align: center;">No tokens yet. Buy some!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- BUY COINS TAB -->
    <div id="tab-buy" class="tab-content">
        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">Buy Coins via UPI</h2>
        <div class="packages-grid">
            <?php foreach ($packages as $p): ?>
            <div class="pkg-card" onclick="buyPackage(<?= $p['package_id'] ?>)">
                <div class="coins">🪙 <?= number_format($p['coin_amount'], 0) ?></div>
                <?php if ($p['bonus_coins'] > 0): ?>
                    <div class="bonus">+<?= number_format($p['bonus_coins'], 0) ?> Bonus</div>
                <?php endif; ?>
                <div class="price">₹<?= number_format($p['price_inr'], 2) ?></div>
                <button onclick="event.stopPropagation(); buyPackage(<?= $p['package_id'] ?>)">Buy Now</button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($packages)): ?>
                <p style="color: var(--text); grid-column: 1/-1; text-align: center;">No packages available. Check back later!</p>
            <?php endif; ?>
        </div>

        <div id="payment-modal" style="display: none; margin-top: 20px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px;">
            <h3 style="color: var(--bright); margin-bottom: 12px;">Complete Payment</h3>
            <div id="payment-details"></div>
        </div>
    </div>

    <!-- GIFT HISTORY TAB -->
    <div id="tab-gifts" class="tab-content">
        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">Gift History</h2>
        <div class="txn-list">
            <?php foreach ($giftHistory as $g): 
                $isSender = $g['sender_id'] === $user['user_id'];
            ?>
            <div class="txn-item">
                <div class="txn-icon <?= $isSender ? 'expense' : 'income' ?>">🎁</div>
                <div class="txn-details">
                    <div class="txn-title"><?= htmlspecialchars($g['gift_name']) ?></div>
                    <div class="txn-meta">
                        <?= $isSender ? 'To' : 'From' ?> <?= htmlspecialchars($isSender ? $g['receiver_name'] : $g['sender_name']) ?>
                        • <?= date('M d, H:i', strtotime($g['created_at'])) ?>
                    </div>
                </div>
                <div class="txn-amount <?= $isSender ? 'expense' : 'income' ?>">
                    <?= $isSender ? '-' : '+' ?>🪙 <?= number_format($g['coin_amount'], 0) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($giftHistory)): ?>
                <p style="color: var(--text); text-align: center; padding: 40px;">No gift history yet. Send a gift to a friend!</p>
            <?php endif; ?>
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

async function buyPackage(packageId) {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Processing...';

    try {
        const formData = new FormData();
        formData.append('package_id', packageId);
        formData.append('csrf_token', '<?= generateCSRFToken() ?>');

        const res = await fetch('/api/buy-coins.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const modal = document.getElementById('payment-modal');
            const details = document.getElementById('payment-details');
            details.innerHTML = `
                <p style="color: var(--text); margin-bottom: 12px;">${data.message}</p>
                <div style="background: #1a1a22; padding: 16px; border-radius: 10px; margin-bottom: 12px;">
                    <p style="color: var(--bright); font-weight: 700; margin-bottom: 8px;">UPI ID: ${data.upi_id}</p>
                    <p style="color: var(--green); font-size: 24px; font-weight: 800;">₹${data.amount_inr}</p>
                    <p style="color: var(--text); font-size: 13px; margin-top: 8px;">You'll get: 🪙 ${data.total_coins} coins</p>
                </div>
                <a href="${data.upi_url}" style="display: block; width: 100%; padding: 12px; background: var(--green); color: white; text-align: center; border-radius: 10px; text-decoration: none; font-weight: 700; margin-bottom: 8px;">Open UPI App</a>
                <p style="color: var(--text); font-size: 12px; text-align: center;">Payment ID: ${data.payment_id}</p>
            `;
            modal.style.display = 'block';
        } else {
            alert(data.error || 'Failed to create payment');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }

    btn.disabled = false;
    btn.textContent = 'Buy Now';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>