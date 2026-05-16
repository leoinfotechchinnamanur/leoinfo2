<?php
// admin/economy.php – Full economy control: Badges, Gifts, Tokens, Donation Cards
// Mobile responsive + 2x larger UI
// Admin can create/manage all economy items

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Economy Manager';
include __DIR__ . '/../includes/header.php';

// Handle all admin actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // ========== CREATE BADGE ==========
    if ($action === 'create_badge') {
        $badgeId = generateUUID();
        $name = trim($_POST['badge_name'] ?? '');
        $description = trim($_POST['badge_desc'] ?? '');
        $iconUrl = trim($_POST['badge_icon'] ?? '');
        $rarity = $_POST['badge_rarity'] ?? 'common';
        $price = floatval($_POST['badge_price'] ?? 0);

        if (empty($name)) {
            $error = 'Badge name is required';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO badges (badge_id, name, description, icon_url, rarity, coin_price, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([$badgeId, $name, $description, $iconUrl, $rarity, $price, $user['user_id']]);
                $message = "✅ Badge '$name' created!";
            } catch (Exception $e) {
                $error = 'Badge creation failed: ' . $e->getMessage();
            }
        }
    }

    // ========== CREATE GIFT ==========
    elseif ($action === 'create_gift') {
        $giftId = generateUUID();
        $name = trim($_POST['gift_name'] ?? '');
        $description = trim($_POST['gift_desc'] ?? '');
        $imageUrl = trim($_POST['gift_image'] ?? '');
        $price = floatval($_POST['gift_price'] ?? 0);
        $animation = trim($_POST['gift_animation'] ?? '');
        $sound = trim($_POST['gift_sound'] ?? '');

        if (empty($name) || empty($imageUrl)) {
            $error = 'Gift name and image URL are required';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO gifts (gift_id, name, description, image_url, coin_price, animation_url, sound_url, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([$giftId, $name, $description, $imageUrl, $price, $animation, $sound, $user['user_id']]);
                $message = "✅ Gift '$name' created! 🎁";
            } catch (Exception $e) {
                $error = 'Gift creation failed';
            }
        }
    }

    // ========== CREATE TOKEN ==========
    elseif ($action === 'create_token') {
        $tokenId = generateUUID();
        $name = trim($_POST['token_name'] ?? '');
        $symbol = trim($_POST['token_symbol'] ?? '');
        $coinValue = floatval($_POST['token_value'] ?? 0);
        $color = trim($_POST['token_color'] ?? '#FFD700');
        $iconUrl = trim($_POST['token_icon'] ?? '');

        if (empty($name) || empty($symbol) || $coinValue <= 0) {
            $error = 'Token name, symbol, and positive coin value are required';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO akku_tokens (token_id, name, symbol, coin_value, color_hex, icon_url, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([$tokenId, $name, $symbol, $coinValue, $color, $iconUrl, $user['user_id']]);
                $message = "✅ Token '$name' ($symbol) created! 🏆";
            } catch (Exception $e) {
                $error = 'Token creation failed';
            }
        }
    }

    // ========== CREATE DONATION CARD ==========
    elseif ($action === 'create_card') {
        $cardId = generateUUID();
        $name = trim($_POST['card_name'] ?? '');
        $template = trim($_POST['card_template'] ?? '');
        $imageUrl = trim($_POST['card_image'] ?? '');
        $minAmount = floatval($_POST['card_min'] ?? 0);
        $maxAmount = floatval($_POST['card_max'] ?? 0);

        if (empty($name) || $minAmount <= 0) {
            $error = 'Card name and minimum amount are required';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO donation_cards (card_id, name, message_template, image_url, min_amount, max_amount, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([$cardId, $name, $template, $imageUrl, $minAmount, $maxAmount, $user['user_id']]);
                $message = "✅ Donation card '$name' created! 💳";
            } catch (Exception $e) {
                $error = 'Card creation failed';
            }
        }
    }

    // ========== TOGGLE ITEM STATUS ==========
    elseif ($action === 'toggle_item') {
        $itemType = $_POST['item_type'] ?? '';
        $itemId = $_POST['item_id'] ?? '';
        $table = match($itemType) {
            'badge' => 'badges',
            'gift' => 'gifts',
            'token' => 'akku_tokens',
            'card' => 'donation_cards',
            default => null
        };
        if ($table) {
            $pdo->prepare("UPDATE $table SET is_active = NOT is_active WHERE {$itemType}_id = ?")
                ->execute([$itemId]);
            $message = "✅ Item status toggled";
        }
    }

    // ========== DELETE ITEM ==========
    elseif ($action === 'delete_item') {
        $itemType = $_POST['item_type'] ?? '';
        $itemId = $_POST['item_id'] ?? '';
        $table = match($itemType) {
            'badge' => 'badges',
            'gift' => 'gifts',
            'token' => 'akku_tokens',
            'card' => 'donation_cards',
            default => null
        };
        if ($table) {
            $pdo->prepare("DELETE FROM $table WHERE {$itemType}_id = ?")
                ->execute([$itemId]);
            $message = "🗑️ Item deleted";
        }
    }

    // ========== UPDATE COMMISSION SETTINGS ==========
    elseif ($action === 'update_commissions') {
        $settings = [
            'game_commission_rate' => floatval($_POST['game_commission'] ?? 10),
            'token_conversion_commission' => floatval($_POST['token_commission'] ?? 10),
            'gift_commission_rate' => floatval($_POST['gift_commission'] ?? 5),
            'post_like_cost' => floatval($_POST['like_cost'] ?? 1),
            'post_comment_cost' => floatval($_POST['comment_cost'] ?? 1),
            'post_like_reward' => floatval($_POST['like_reward'] ?? 1),
            'post_comment_reward' => floatval($_POST['comment_reward'] ?? 1),
        ];

        foreach ($settings as $key => $value) {
            setCommissionSetting($key, $value);
        }
        $message = "✅ Commission settings updated!";
    }

    // ========== VERIFY UPI PAYMENT ==========
    elseif ($action === 'verify_payment') {
        $paymentId = $_POST['payment_id'] ?? '';
        $utr = trim($_POST['utr_number'] ?? '');
        $notes = trim($_POST['verify_notes'] ?? '');

        $result = verifyPayment($paymentId, $user['user_id'], $utr, $notes);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['error'];
        }
    }

    // ========== REJECT UPI PAYMENT ==========
    elseif ($action === 'reject_payment') {
        $paymentId = $_POST['payment_id'] ?? '';
        $notes = trim($_POST['reject_notes'] ?? '');

        try {
            $pdo->prepare(
                "UPDATE upi_payments SET status = 'rejected', notes = ?, verified_by = ?, verified_at = NOW()
                 WHERE payment_id = ?"
            )->execute([$notes, $user['user_id'], $paymentId]);
            $message = "❌ Payment rejected";
        } catch (Exception $e) {
            $error = 'Rejection failed';
        }
    }
}

// Load all data
$badges = getBadges(false);
$gifts = getGifts(false);
$tokens = getTokens(false);
$cards = $pdo->query("SELECT * FROM donation_cards ORDER BY created_at DESC")->fetchAll();
$packages = $pdo->query("SELECT * FROM coin_packages ORDER BY sort_order")->fetchAll();
$pendingPayments = $pdo->query(
    "SELECT up.*, u.name as user_name, u.email, cp.name as package_name
     FROM upi_payments up
     JOIN users u ON up.user_id = u.user_id
     JOIN coin_packages cp ON up.package_id = cp.package_id
     WHERE up.status = 'pending'
     ORDER BY up.created_at DESC"
)->fetchAll();

$stats = getEconomyStats();
$commissionSettings = [];
foreach (['game_commission_rate', 'token_conversion_commission', 'gift_commission_rate', 'post_like_cost', 'post_comment_cost', 'post_like_reward', 'post_comment_reward'] as $key) {
    $commissionSettings[$key] = getCommissionSetting($key);
}

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
    }

    .eco-wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .eco-header { margin-bottom: 20px; }
    .eco-header h1 { font-size: 28px; color: var(--bright); font-weight: 800; }
    .eco-header p { color: var(--text); font-size: 14px; margin-top: 4px; }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-box {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
    }
    .stat-box .emoji { font-size: 32px; display: block; margin-bottom: 8px; }
    .stat-box .value { font-size: 28px; font-weight: 800; color: var(--accent); }
    .stat-box .label { font-size: 11px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; font-weight: 600; }

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

    /* Content Sections */
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Cards Grid */
    .items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
    .item-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        transition: all 0.2s;
        position: relative;
    }
    .item-card:hover { border-color: var(--accent); transform: translateY(-2px); }
    .item-card.inactive { opacity: 0.5; }
    .item-card .item-img {
        width: 80px; height: 80px; border-radius: 12px;
        object-fit: cover; margin-bottom: 12px;
        background: #1a1a22; display: flex; align-items: center; justify-content: center;
        font-size: 40px;
    }
    .item-card h3 { font-size: 16px; color: var(--bright); font-weight: 700; margin-bottom: 6px; }
    .item-card p { font-size: 12px; color: var(--text); margin-bottom: 8px; line-height: 1.4; }
    .item-card .price {
        font-size: 18px; font-weight: 800; color: var(--green);
    }
    .item-card .rarity {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .rarity-common { background: #374151; color: #9ca3af; }
    .rarity-rare { background: #1e3a5f; color: #60a5fa; }
    .rarity-epic { background: #4c1d6b; color: #c084fc; }
    .rarity-legendary { background: #713f12; color: #fbbf24; }

    .item-actions {
        display: flex;
        gap: 6px;
        margin-top: 12px;
    }
    .item-actions button {
        flex: 1;
        padding: 8px;
        border-radius: 8px;
        border: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        color: white;
    }
    .btn-toggle { background: var(--yellow); }
    .btn-delete { background: var(--red); }

    /* Forms */
    .form-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .form-card h2 {
        font-size: 20px;
        color: var(--bright);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }
    .form-card h2 .emoji { font-size: 24px; }
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
    .form-group textarea { min-height: 80px; resize: vertical; }

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
    }
    .submit-btn:hover { opacity: 0.9; }

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
    .status-pending { color: var(--yellow); font-weight: 700; }
    .status-verified { color: var(--green); font-weight: 700; }
    .status-rejected { color: var(--red); font-weight: 700; }

    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }
    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--green); color: var(--green); }
    .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--red); color: var(--red); }

    /* Token Preview */
    .token-preview {
        width: 60px; height: 60px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; font-weight: 800;
        color: white;
        margin-bottom: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .eco-wrap { padding: 10px; }
        .eco-header h1 { font-size: 22px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .stat-box { padding: 14px; }
        .stat-box .emoji { font-size: 24px; }
        .stat-box .value { font-size: 22px; }
        .items-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .item-card { padding: 14px; }
        .item-card .item-img { width: 60px; height: 60px; font-size: 30px; }
        .form-grid { grid-template-columns: 1fr; }
        .tabs { gap: 6px; }
        .tab-btn { padding: 8px 12px; font-size: 12px; }
        .data-table { font-size: 11px; }
        .data-table th, .data-table td { padding: 8px; }
    }

    @media (max-width: 380px) {
        .stats-grid { grid-template-columns: 1fr; }
        .items-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="eco-wrap">
    <div class="eco-header">
        <h1><span class="emoji">🏦</span> Economy Manager</h1>
        <p>Manage badges, gifts, tokens, donation cards, commissions & UPI payments</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-box">
            <span class="emoji">🪙</span>
            <div class="value"><?= number_format($stats['total_coins_in_circulation'] ?? 0, 0) ?></div>
            <div class="label">Total Coins</div>
        </div>
        <div class="stat-box">
            <span class="emoji">📊</span>
            <div class="value"><?= number_format($stats['total_transactions'] ?? 0, 0) ?></div>
            <div class="label">Transactions</div>
        </div>
        <div class="stat-box">
            <span class="emoji">🎁</span>
            <div class="value"><?= number_format($stats['total_gifts_sent'] ?? 0, 0) ?></div>
            <div class="label">Gifts Sent</div>
        </div>
        <div class="stat-box">
            <span class="emoji">🏆</span>
            <div class="value"><?= number_format($stats['total_token_conversions'] ?? 0, 0) ?></div>
            <div class="label">Conversions</div>
        </div>
        <div class="stat-box">
            <span class="emoji">💰</span>
            <div class="value"><?= number_format($stats['total_game_commission'] ?? 0, 2) ?></div>
            <div class="label">Game Commission</div>
        </div>
        <div class="stat-box">
            <span class="emoji">⏳</span>
            <div class="value"><?= $stats['pending_upi_payments'] ?? 0 ?></div>
            <div class="label">Pending UPI</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('badges')">🎖️ Badges</button>
        <button class="tab-btn" onclick="showTab('gifts')">🎁 Gifts</button>
        <button class="tab-btn" onclick="showTab('tokens')">🏆 Tokens</button>
        <button class="tab-btn" onclick="showTab('cards')">💳 Cards</button>
        <button class="tab-btn" onclick="showTab('packages')">📦 Packages</button>
        <button class="tab-btn" onclick="showTab('payments')">💳 UPI Payments</button>
        <button class="tab-btn" onclick="showTab('commissions')">⚙️ Commissions</button>
        <button class="tab-btn" onclick="location.href='/admin/collectionbox.php'">🏦 Treasury</button>
    </div>

    <!-- BADGES TAB -->
    <div id="tab-badges" class="tab-content active">
        <div class="form-card">
            <h2><span class="emoji">🎖️</span> Create New Badge</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create_badge">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="badge_name" required placeholder="e.g. Early Adopter">
                    </div>
                    <div class="form-group">
                        <label>Rarity</label>
                        <select name="badge_rarity">
                            <option value="common">Common</option>
                            <option value="rare">Rare</option>
                            <option value="epic">Epic</option>
                            <option value="legendary">Legendary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coin Price (0 = free)</label>
                        <input type="number" name="badge_price" step="0.01" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Icon URL / Emoji</label>
                        <input type="text" name="badge_icon" placeholder="https://... or 🎖️">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="badge_desc" placeholder="What this badge represents..."></textarea>
                </div>
                <button type="submit" class="submit-btn">🎖️ Create Badge</button>
            </form>
        </div>

        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">All Badges</h2>
        <div class="items-grid">
            <?php foreach ($badges as $b): ?>
            <div class="item-card <?= $b['is_active'] ? '' : 'inactive' ?>">
                <div class="item-img"><?= $b['icon_url'] ?: '🎖️' ?></div>
                <span class="rarity rarity-<?= $b['rarity'] ?>"><?= ucfirst($b['rarity']) ?></span>
                <h3><?= htmlspecialchars($b['name']) ?></h3>
                <p><?= htmlspecialchars($b['description'] ?? '') ?></p>
                <div class="price">🪙 <?= number_format($b['coin_price'], 0) ?></div>
                <div class="item-actions">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="toggle_item">
                        <input type="hidden" name="item_type" value="badge">
                        <input type="hidden" name="item_id" value="<?= $b['badge_id'] ?>">
                        <button type="submit" class="btn-toggle"><?= $b['is_active'] ? '⏸️ Disable' : '▶️ Enable' ?></button>
                    </form>
                    <form method="POST" style="flex:1;" onsubmit="return confirm('Delete this badge?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_type" value="badge">
                        <input type="hidden" name="item_id" value="<?= $b['badge_id'] ?>">
                        <button type="submit" class="btn-delete">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($badges)): ?>
                <p style="color: var(--text); text-align: center; grid-column: 1/-1;">No badges yet. Create one above!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- GIFTS TAB -->
    <div id="tab-gifts" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">🎁</span> Create New Gift</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create_gift">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="gift_name" required placeholder="e.g. Rose">
                    </div>
                    <div class="form-group">
                        <label>Coin Price</label>
                        <input type="number" name="gift_price" step="0.01" value="10" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="url" name="gift_image" required placeholder="https://...png">
                    </div>
                    <div class="form-group">
                        <label>Animation URL (optional)</label>
                        <input type="url" name="gift_animation" placeholder="https://...gif">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="gift_desc" placeholder="What this gift represents..."></textarea>
                </div>
                <button type="submit" class="submit-btn">🎁 Create Gift</button>
            </form>
        </div>

        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">All Gifts</h2>
        <div class="items-grid">
            <?php foreach ($gifts as $g): ?>
            <div class="item-card <?= $g['is_active'] ? '' : 'inactive' ?>">
                <div class="item-img"><?= $g['image_url'] ? '<img src="'.htmlspecialchars($g['image_url']).'" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">' : '🎁' ?></div>
                <h3><?= htmlspecialchars($g['name']) ?></h3>
                <p><?= htmlspecialchars($g['description'] ?? '') ?></p>
                <div class="price">🪙 <?= number_format($g['coin_price'], 0) ?></div>
                <div class="item-actions">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="toggle_item">
                        <input type="hidden" name="item_type" value="gift">
                        <input type="hidden" name="item_id" value="<?= $g['gift_id'] ?>">
                        <button type="submit" class="btn-toggle"><?= $g['is_active'] ? '⏸️' : '▶️' ?></button>
                    </form>
                    <form method="POST" style="flex:1;" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_type" value="gift">
                        <input type="hidden" name="item_id" value="<?= $g['gift_id'] ?>">
                        <button type="submit" class="btn-delete">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TOKENS TAB -->
    <div id="tab-tokens" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">🏆</span> Create New Token</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create_token">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="token_name" required placeholder="e.g. Gold Token">
                    </div>
                    <div class="form-group">
                        <label>Symbol (3-5 chars)</label>
                        <input type="text" name="token_symbol" required placeholder="GLD" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Coin Value</label>
                        <input type="number" name="token_value" step="1" value="1000" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="token_color" value="#FFD700">
                    </div>
                </div>
                <div class="form-group">
                    <label>Icon URL (optional)</label>
                    <input type="url" name="token_icon" placeholder="https://...png">
                </div>
                <button type="submit" class="submit-btn">🏆 Create Token</button>
            </form>
        </div>

        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">All Tokens</h2>
        <div class="items-grid">
            <?php foreach ($tokens as $t): ?>
            <div class="item-card <?= $t['is_active'] ? '' : 'inactive' ?>">
                <div class="token-preview" style="background: <?= $t['color_hex'] ?>;">
                    <?= $t['symbol'] ?>
                </div>
                <h3><?= htmlspecialchars($t['name']) ?></h3>
                <p><?= $t['coin_value'] ?> coins each</p>
                <div class="price">🪙 <?= number_format($t['coin_value'], 0) ?></div>
                <div class="item-actions">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="toggle_item">
                        <input type="hidden" name="item_type" value="token">
                        <input type="hidden" name="item_id" value="<?= $t['token_id'] ?>">
                        <button type="submit" class="btn-toggle"><?= $t['is_active'] ? '⏸️' : '▶️' ?></button>
                    </form>
                    <form method="POST" style="flex:1;" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_type" value="token">
                        <input type="hidden" name="item_id" value="<?= $t['token_id'] ?>">
                        <button type="submit" class="btn-delete">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CARDS TAB -->
    <div id="tab-cards" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">💳</span> Create Donation Card</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="create_card">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="card_name" required placeholder="e.g. Birthday Wishes">
                    </div>
                    <div class="form-group">
                        <label>Min Amount</label>
                        <input type="number" name="card_min" step="0.01" value="10" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Max Amount (0 = unlimited)</label>
                        <input type="number" name="card_max" step="0.01" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>Image URL</label>
                        <input type="url" name="card_image" placeholder="https://...png">
                    </div>
                </div>
                <div class="form-group">
                    <label>Message Template</label>
                    <textarea name="card_template" placeholder="Happy Birthday! Here's a gift for you..."></textarea>
                </div>
                <button type="submit" class="submit-btn">💳 Create Card</button>
            </form>
        </div>

        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">All Cards</h2>
        <div class="items-grid">
            <?php foreach ($cards as $c): ?>
            <div class="item-card <?= $c['is_active'] ? '' : 'inactive' ?>">
                <div class="item-img">💳</div>
                <h3><?= htmlspecialchars($c['name']) ?></h3>
                <p><?= htmlspecialchars($c['message_template'] ?? '') ?></p>
                <div class="price">🪙 <?= number_format($c['min_amount'], 0) ?> - <?= $c['max_amount'] > 0 ? number_format($c['max_amount'], 0) : '∞' ?></div>
                <div class="item-actions">
                    <form method="POST" style="flex:1;">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="toggle_item">
                        <input type="hidden" name="item_type" value="card">
                        <input type="hidden" name="item_id" value="<?= $c['card_id'] ?>">
                        <button type="submit" class="btn-toggle"><?= $c['is_active'] ? '⏸️' : '▶️' ?></button>
                    </form>
                    <form method="POST" style="flex:1;" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_type" value="card">
                        <input type="hidden" name="item_id" value="<?= $c['card_id'] ?>">
                        <button type="submit" class="btn-delete">🗑️</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PACKAGES TAB -->
    <div id="tab-packages" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">📦</span> Coin Packages</h2>
            <p style="color: var(--text); margin-bottom: 16px;">Users can buy these via UPI. Edit directly in database for now.</p>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Coins</th>
                            <th>Bonus</th>
                            <th>Price (₹)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= number_format($p['coin_amount'], 0) ?></td>
                            <td>+<?= number_format($p['bonus_coins'], 0) ?></td>
                            <td>₹<?= number_format($p['price_inr'], 2) ?></td>
                            <td><?= $p['is_active'] ? '✅ Active' : '⏸️ Disabled' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAYMENTS TAB -->
    <div id="tab-payments" class="tab-content">
        <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px; font-weight: 700;">
            ⏳ Pending UPI Payments (<?= count($pendingPayments) ?>)
        </h2>

        <?php if (empty($pendingPayments)): ?>
            <div class="form-card" style="text-align: center; color: var(--text);">
                <span class="emoji" style="font-size: 40px;">✅</span>
                <p>No pending payments!</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Coins</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayments as $pay): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($pay['user_name']) ?></strong><br>
                                <small style="color: var(--text);"><?= htmlspecialchars($pay['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($pay['package_name']) ?></td>
                            <td>₹<?= number_format($pay['price_inr'], 2) ?></td>
                            <td><?= number_format($pay['coin_amount'], 0) ?> 🪙</td>
                            <td><?= date('M d, H:i', strtotime($pay['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 6px; flex-direction: column;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="verify_payment">
                                    <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                    <input type="text" name="utr_number" placeholder="UTR Number" style="padding: 6px; border-radius: 6px; border: 1px solid var(--border); background: #1a1a22; color: white; font-size: 12px;">
                                    <input type="text" name="verify_notes" placeholder="Notes (optional)" style="padding: 6px; border-radius: 6px; border: 1px solid var(--border); background: #1a1a22; color: white; font-size: 12px;">
                                    <button type="submit" class="btn-toggle" style="padding: 8px; font-size: 12px;">✅ Verify & Add Coins</button>
                                </form>
                                <form method="POST" style="margin-top: 6px;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="reject_payment">
                                    <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                    <input type="text" name="reject_notes" placeholder="Rejection reason" style="padding: 6px; border-radius: 6px; border: 1px solid var(--border); background: #1a1a22; color: white; font-size: 12px; width: 100%; margin-bottom: 4px;">
                                    <button type="submit" class="btn-delete" style="padding: 8px; font-size: 12px; width: 100%;">❌ Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- COMMISSIONS TAB -->
    <div id="tab-commissions" class="tab-content">
        <div class="form-card">
            <h2><span class="emoji">⚙️</span> Economy Settings</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update_commissions">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Game Commission %</label>
                        <input type="number" name="game_commission" step="0.01" value="<?= $commissionSettings['game_commission_rate'] ?>" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label>Token Conversion Commission %</label>
                        <input type="number" name="token_commission" step="0.01" value="<?= $commissionSettings['token_conversion_commission'] ?>" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label>Gift Commission %</label>
                        <input type="number" name="gift_commission" step="0.01" value="<?= $commissionSettings['gift_commission_rate'] ?>" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label>Like Cost (coins)</label>
                        <input type="number" name="like_cost" step="0.01" value="<?= $commissionSettings['post_like_cost'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Comment Cost (coins)</label>
                        <input type="number" name="comment_cost" step="0.01" value="<?= $commissionSettings['post_comment_cost'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Like Reward to Owner (coins)</label>
                        <input type="number" name="like_reward" step="0.01" value="<?= $commissionSettings['post_like_reward'] ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Comment Reward to Owner (coins)</label>
                        <input type="number" name="comment_reward" step="0.01" value="<?= $commissionSettings['post_comment_reward'] ?>" min="0">
                    </div>
                </div>

                <button type="submit" class="submit-btn">💾 Save All Settings</button>
            </form>
        </div>

        <div class="form-card">
            <h2><span class="emoji">📊</span> How It Works</h2>
            <div style="color: var(--text); line-height: 1.8; font-size: 14px;">
                <p><strong>🎮 Game Commission:</strong> When user wins, admin gets X% of winnings automatically.</p>
                <p><strong>🏆 Token Conversion:</strong> When user converts token to coins, admin gets X% as fee.</p>
                <p><strong>🎁 Gift Commission:</strong> When user sends gift, receiver gets (100-X)%, admin gets X%.</p>
                <p><strong>❤️ Like/Comment:</strong> Viewer pays cost coins → Owner receives reward coins. Net = cost - reward.</p>
                <p><strong>💳 UPI Payments:</strong> User pays via UPI → Admin verifies manually → Coins added automatically.</p>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>