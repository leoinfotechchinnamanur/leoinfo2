<?php
// admin/users.php – Admin User Management
// View all users, ban/unban, view coin history

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'User Management';

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $targetId = $_POST['target_user_id'] ?? '';

    if ($action === 'ban_user' && !empty($targetId)) {
        try {
            $pdo->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ?")
                ->execute([$targetId]);
            $message = "🚫 User banned";
        } catch (Exception $e) {
            $error = "Ban failed: " . $e->getMessage();
        }
    } elseif ($action === 'unban_user' && !empty($targetId)) {
        try {
            $pdo->prepare("UPDATE users SET is_banned = 0 WHERE user_id = ?")
                ->execute([$targetId]);
            $message = "✅ User unbanned";
        } catch (Exception $e) {
            $error = "Unban failed: " . $e->getMessage();
        }
    } elseif ($action === 'add_coins' && !empty($targetId)) {
        $amount = floatval($_POST['coin_amount'] ?? 0);
        if ($amount > 0) {
            try {
                $pdo->beginTransaction();

                $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
                $balStmt->execute([$targetId]);
                $current = (float)$balStmt->fetchColumn();
                $newBalance = $current + $amount;

                $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
                    ->execute([$newBalance, $targetId]);

                $pdo->prepare("
                    INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                    VALUES (?, ?, 'admin_add', ?, ?, ?, NOW())
                ")->execute([
                    generateUUID(), $targetId, $amount, $newBalance,
                    "Admin bonus from " . $user['name']
                ]);

                $pdo->commit();
                $message = "✅ Added {$amount} coins to user";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Add coins failed: " . $e->getMessage();
            }
        }
    } elseif ($action === 'deduct_coins' && !empty($targetId)) {
        $amount = floatval($_POST['coin_amount'] ?? 0);
        if ($amount > 0) {
            try {
                $pdo->beginTransaction();

                $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
                $balStmt->execute([$targetId]);
                $current = (float)$balStmt->fetchColumn();

                if ($current < $amount) {
                    $error = "User only has {$current} coins";
                } else {
                    $newBalance = $current - $amount;
                    $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
                        ->execute([$newBalance, $targetId]);

                    $pdo->prepare("
                        INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                        VALUES (?, ?, 'admin_deduct', ?, ?, ?, NOW())
                    ")->execute([
                        generateUUID(), $targetId, -$amount, $newBalance,
                        "Admin deduction by " . $user['name']
                    ]);

                    $pdo->commit();
                    $message = "✅ Deducted {$amount} coins from user";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Deduct coins failed: " . $e->getMessage();
            }
        }
    }
}

// Load users
$search = $_GET['search'] ?? '';
$users = [];
try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   (SELECT COUNT(*) FROM user_posts WHERE user_id = u.user_id) as post_count,
                   (SELECT COUNT(*) FROM coin_transactions WHERE user_id = u.user_id) as txn_count
            FROM users u
            WHERE u.name LIKE ? OR u.email LIKE ?
            ORDER BY u.created_at DESC
            LIMIT 100
        ");
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->query("
            SELECT u.*,
                   (SELECT COUNT(*) FROM user_posts WHERE user_id = u.user_id) as post_count,
                   (SELECT COUNT(*) FROM coin_transactions WHERE user_id = u.user_id) as txn_count
            FROM users u
            ORDER BY u.created_at DESC
            LIMIT 100
        ");
    }
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Could not load users: " . $e->getMessage();
}

// Stats
$stats = [];
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) as banned,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(coin_balance) as total_coins
        FROM users
    ")->fetch();
} catch (Exception $e) {}

$csrf_token = generateCSRFToken();
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
    }
    .mod-wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .mod-header { margin-bottom: 20px; }
    .mod-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-box {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
    }
    .stat-box .emoji { font-size: 28px; display: block; margin-bottom: 8px; }
    .stat-box .value { font-size: 28px; font-weight: 800; color: var(--accent); }
    .stat-box .label { font-size: 11px; color: var(--text); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

    .search-bar {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
    }
    .search-bar input {
        flex: 1;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: #1a1a22;
        color: var(--bright);
        font-size: 14px;
    }
    .search-bar button {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        background: var(--accent);
        color: white;
        font-weight: 700;
        cursor: pointer;
    }

    .user-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .user-table th {
        text-align: left;
        padding: 12px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .user-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
        color: var(--bright);
        vertical-align: middle;
    }
    .user-table tr:hover td { background: #15151d; }

    .user-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: var(--accent);
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: bold; font-size: 14px;
    }

    .role-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .role-admin { background: rgba(99,102,241,0.15); color: var(--accent); }
    .role-user { background: rgba(161,161,170,0.15); color: var(--text); }

    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
    }
    .status-active { background: rgba(16,185,129,0.15); color: var(--green); }
    .status-banned { background: rgba(239,68,68,0.15); color: var(--red); }

    .action-btns {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    .action-btns button, .action-btns a {
        padding: 6px 10px;
        border-radius: 6px;
        border: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        color: white;
        text-decoration: none;
        display: inline-block;
    }
    .btn-ban { background: var(--red); }
    .btn-unban { background: var(--green); }
    .btn-coins { background: var(--yellow); color: #000; }
    .btn-view { background: var(--accent); }

    .coin-form {
        display: none;
        margin-top: 8px;
        padding: 12px;
        background: #1a1a22;
        border-radius: 8px;
    }
    .coin-form.open { display: flex; gap: 8px; }
    .coin-form input {
        flex: 1;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--bright);
    }
    .coin-form button {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        cursor: pointer;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid var(--green); color: var(--green); }
    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid var(--red); color: var(--red); }

    @media (max-width: 768px) {
        .user-table { font-size: 11px; }
        .user-table th, .user-table td { padding: 8px; }
        .action-btns { flex-direction: column; }
    }
</style>

<div class="mod-wrap">
    <div class="mod-header">
        <h1><span class="emoji">👥</span> User Management</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <span class="emoji">👥</span>
            <div class="value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="label">Total Users</div>
        </div>
        <div class="stat-box">
            <span class="emoji">🛡️</span>
            <div class="value"><?= number_format($stats['admins'] ?? 0) ?></div>
            <div class="label">Admins</div>
        </div>
        <div class="stat-box">
            <span class="emoji">🚫</span>
            <div class="value"><?= number_format($stats['banned'] ?? 0) ?></div>
            <div class="label">Banned</div>
        </div>
        <div class="stat-box">
            <span class="emoji">🪙</span>
            <div class="value"><?= number_format($stats['total_coins'] ?? 0, 0) ?></div>
            <div class="label">Total Coins</div>
        </div>
    </div>

    <!-- Search -->
    <form class="search-bar" method="GET">
        <input type="text" name="search" placeholder="Search users by name or email..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">🔍 Search</button>
        <?php if ($search): ?>
            <a href="?" style="padding: 10px 16px; background: var(--border); color: var(--text); border-radius: 10px; text-decoration: none; font-weight: 600;">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Users Table -->
    <div style="overflow-x: auto;">
        <table class="user-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Coins</th>
                    <th>Activity</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="user-avatar"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                            <div>
                                <div style="font-weight: 700;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size: 12px; color: var(--text);"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge role-<?= $u['role'] ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?= $u['is_banned'] ? 'status-banned' : 'status-active' ?>">
                            <?= $u['is_banned'] ? 'Banned' : 'Active' ?>
                        </span>
                    </td>
                    <td>
                        <span style="color: var(--green); font-weight: 700;">🪙 <?= number_format($u['coin_balance'] ?? 0, 0) ?></span>
                    </td>
                    <td>
                        <?= number_format($u['post_count'] ?? 0) ?> posts<br>
                        <?= number_format($u['txn_count'] ?? 0) ?> txns
                    </td>
                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="/user/profile.php?id=<?= $u['user_id'] ?>" class="btn-view" target="_blank">👁️</a>

                            <button type="button" class="btn-coins" onclick="toggleCoinForm('<?= $u['user_id'] ?>')">🪙</button>

                            <?php if ($u['is_banned']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="unban_user">
                                    <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn-unban">✅</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Ban this user?')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="ban_user">
                                    <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn-ban">🚫</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Coin Form -->
                        <div class="coin-form" id="coin-form-<?= $u['user_id'] ?>">
                            <form method="POST" style="display: flex; gap: 8px; width: 100%;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                <input type="number" name="coin_amount" step="0.01" min="0.01" placeholder="Amount" required>
                                <button type="submit" name="action" value="add_coins" style="background: var(--green); color: white;">➕</button>
                                <button type="submit" name="action" value="deduct_coins" style="background: var(--red); color: white;">➖</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text); padding: 40px;">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleCoinForm(userId) {
    const form = document.getElementById('coin-form-' + userId);
    document.querySelectorAll('.coin-form').forEach(f => {
        if (f !== form) f.classList.remove('open');
    });
    form.classList.toggle('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>