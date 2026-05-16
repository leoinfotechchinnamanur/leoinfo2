<?php
// admin/coins.php – Admin coin management
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Coin Management';
include __DIR__ . '/../includes/header.php';

// Handle admin actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request (CSRF).";
    } else {
        $action = $_POST['action'] ?? '';
        
        // Admin Add/Deduct Coins
        if (in_array($action, ['admin_add', 'admin_deduct'])) {
            $targetUserId = $_POST['user_id'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            
            if ($amount <= 0) {
                $error = "Amount must be greater than 0.";
            } elseif (empty($targetUserId)) {
                $error = "Please select a user.";
            } else {
                // Verify target user exists
                $check = $pdo->prepare("SELECT user_id, name, coin_balance FROM users WHERE user_id = ?");
                $check->execute([$targetUserId]);
                $targetUser = $check->fetch();
                
                if (!$targetUser) {
                    $error = "User not found.";
                } elseif ($action === 'admin_deduct' && $targetUser['coin_balance'] < $amount) {
                    $error = "User doesn't have enough coins. Balance: " . $targetUser['coin_balance'];
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $newBalance = $action === 'admin_add' 
                            ? $targetUser['coin_balance'] + $amount 
                            : $targetUser['coin_balance'] - $amount;
                        
                        // Update user balance
                        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
                            ->execute([$newBalance, $targetUserId]);
                        
                        // Record transaction
                        $pdo->prepare(
                            "INSERT INTO coin_transactions 
                             (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW())"
                        )->execute([
                            generateUUID(),
                            $targetUserId,
                            $action,
                            $action === 'admin_add' ? $amount : -$amount,
                            $newBalance,
                            "Admin (" . $user['name'] . "): " . $reason
                        ]);
                        
                        $pdo->commit();
                        $message = "Successfully " . ($action === 'admin_add' ? 'added' : 'deducted') . 
                                   " " . number_format($amount, 2) . " coins to " . $targetUser['name'];
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Transaction failed: " . $e->getMessage();
                    }
                }
            }
        }
        
        // Update Reward Settings
        if ($action === 'update_rewards') {
            $rewards = [
                'new_user'      => floatval($_POST['reward_new_user'] ?? 100),
                'post_create'   => floatval($_POST['reward_post'] ?? 2),
                'like_received' => floatval($_POST['reward_like'] ?? 1),
                'game_win'      => floatval($_POST['reward_game'] ?? 10),
            ];
            
            // Store in config table
            try {
                foreach ($rewards as $key => $value) {
                    $pdo->prepare(
                        "INSERT INTO config (setting_key, setting_value, updated_at) 
                         VALUES (?, ?, NOW())
                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()"
                    )->execute(["reward_$key", $value, $value]);
                }
                $message = "Reward settings updated successfully.";
            } catch (Exception $e) {
                $error = "Failed to save settings: " . $e->getMessage();
            }
        }
    }
}

// Get current reward settings from config
$rewardSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM config WHERE setting_key LIKE 'reward_%'");
    while ($row = $stmt->fetch()) {
        $key = str_replace('reward_', '', $row['setting_key']);
        $rewardSettings[$key] = floatval($row['setting_value']);
    }
} catch (Exception $e) {
    // Config table might not have rewards yet, use defaults
}

// Defaults if not set
$defaults = ['new_user' => 100, 'post_create' => 2, 'like_received' => 1, 'game_win' => 10];
foreach ($defaults as $key => $val) {
    if (!isset($rewardSettings[$key])) $rewardSettings[$key] = $val;
}

// Recent transactions
$transactions = [];
try {
    $stmt = $pdo->query(
        "SELECT t.*, u.name as user_name 
         FROM coin_transactions t
         JOIN users u ON t.user_id = u.user_id
         ORDER BY t.created_at DESC LIMIT 50"
    );
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Could not load transactions: " . $e->getMessage();
}

// User list for dropdown
$users = [];
try {
    $stmt = $pdo->query("SELECT user_id, name, email, coin_balance FROM users ORDER BY name");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

$csrf_token = generateCSRFToken();
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
        --admin-red: #ef4444;
    }
    
    .admin-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
    .admin-header { margin-bottom: 24px; }
    .admin-header h1 { font-size: 28px; color: var(--admin-bright); font-weight: 800; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .grid-1 { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 24px; }
    
    .card {
        background: var(--admin-card);
        border: 1px solid var(--admin-border);
        border-radius: 14px;
        padding: 24px;
    }
    .card h2 {
        font-size: 20px;
        color: var(--admin-bright);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
    }
    .card h2 .emoji { font-size: 24px; }
    
    .form-group { margin-bottom: 16px; }
    .form-group label {
        display: block;
        font-size: 13px;
        color: var(--admin-text);
        margin-bottom: 6px;
        font-weight: 500;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid var(--admin-border);
        background: #1a1a22;
        color: var(--admin-bright);
        font-size: 14px;
        font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none;
        border-color: var(--admin-accent);
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        color: white;
        transition: opacity 0.2s;
    }
    .btn:hover { opacity: 0.9; }
    .btn-accent { background: var(--admin-accent); }
    .btn-green { background: var(--admin-green); }
    .btn-red { background: var(--admin-red); }
    
    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--admin-green); color: var(--admin-green); }
    .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--admin-red); color: var(--admin-red); }
    
    .txn-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .txn-table th {
        text-align: left;
        padding: 10px;
        color: var(--admin-text);
        border-bottom: 1px solid var(--admin-border);
        font-weight: 600;
    }
    .txn-table td {
        padding: 10px;
        border-bottom: 1px solid var(--admin-border);
        color: var(--admin-bright);
    }
    .txn-table tr:hover td { background: #15151d; }
    .txn-amount { font-weight: 700; }
    .txn-positive { color: var(--admin-green); }
    .txn-negative { color: var(--admin-red); }
    
    @media (max-width: 768px) {
        .grid-2 { grid-template-columns: 1fr; }
        .txn-table { font-size: 11px; }
        .txn-table th, .txn-table td { padding: 6px; }
    }
</style>

<div class="admin-wrap">
    <div class="admin-header">
        <h1><span class="emoji">🪙</span> Coin Management</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="grid-2">
        <!-- Add Coins -->
        <div class="card">
            <h2><span class="emoji">➕</span> Add Coins</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="admin_add">
                
                <div class="form-group">
                    <label>Select User</label>
                    <select name="user_id" required>
                        <option value="">Choose user...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>) — 🪙<?= number_format($u['coin_balance'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" name="reason" required placeholder="Bonus, compensation, etc.">
                </div>
                
                <button type="submit" class="btn btn-green">🪙 Add Coins</button>
            </form>
        </div>
        
        <!-- Deduct Coins -->
        <div class="card">
            <h2><span class="emoji">➖</span> Deduct Coins</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="admin_deduct">
                
                <div class="form-group">
                    <label>Select User</label>
                    <select name="user_id" required>
                        <option value="">Choose user...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>) — 🪙<?= number_format($u['coin_balance'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" name="reason" required placeholder="Penalty, correction, etc.">
                </div>
                
                <button type="submit" class="btn btn-red">🪙 Deduct Coins</button>
            </form>
        </div>
    </div>
    
    <!-- Reward Settings -->
    <div class="card" style="margin-bottom: 24px;">
        <h2><span class="emoji">⚙️</span> Auto-Reward Settings</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="update_rewards">
            
            <div class="grid-2">
                <div class="form-group">
                    <label>New User Bonus 🎁</label>
                    <input type="number" name="reward_new_user" step="0.01" value="<?= $rewardSettings['new_user'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Post Creation 📝</label>
                    <input type="number" name="reward_post" step="0.01" value="<?= $rewardSettings['post_create'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Like Received ❤️</label>
                    <input type="number" name="reward_like" step="0.01" value="<?= $rewardSettings['like_received'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Game Win 🎮</label>
                    <input type="number" name="reward_game" step="0.01" value="<?= $rewardSettings['game_win'] ?>" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-accent">💾 Save Reward Settings</button>
        </form>
    </div>
    
    <!-- Transaction History -->
    <div class="card">
        <h2><span class="emoji">📊</span> Recent Transactions</h2>
        <div style="overflow-x: auto;">
            <table class="txn-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($t['created_at'])) ?></td>
                            <td><?= htmlspecialchars($t['user_name']) ?></td>
                            <td><?= str_replace('_', ' ', $t['reference_type']) ?></td>
                            <td class="txn-amount <?= $t['amount'] >= 0 ? 'txn-positive' : 'txn-negative' ?>">
                                <?= $t['amount'] >= 0 ? '+' : '' ?><?= number_format($t['amount'], 2) ?>
                            </td>
                            <td><?= number_format($t['balance_after'], 2) ?></td>
                            <td><?= htmlspecialchars($t['description'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align:center; color: var(--admin-text);">No transactions yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>