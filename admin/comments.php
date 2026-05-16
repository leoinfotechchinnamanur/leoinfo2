<?php
// admin/comments.php – Admin Comment Moderation

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Comment Moderation';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $commentId = $_POST['comment_id'] ?? '';

    if ($action === 'delete_comment' && !empty($commentId)) {
        try {
            $pdo->prepare("DELETE FROM post_comments WHERE comment_id = ?")
                ->execute([$commentId]);
            $message = "✅ Comment deleted";
        } catch (Exception $e) {
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}

// Load comments
$comments = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, u.name, u.email, p.content as post_preview
        FROM post_comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN user_posts p ON c.post_id = p.post_id
        ORDER BY c.created_at DESC
        LIMIT 200
    ");
    $comments = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Could not load comments: " . $e->getMessage();
}

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
        --red: #ef4444;
    }
    .mod-wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .mod-header { margin-bottom: 20px; }
    .mod-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }

    .comment-list { display: flex; flex-direction: column; gap: 12px; }
    .comment-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
    }
    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    .comment-author { font-weight: 700; color: var(--bright); }
    .comment-time { font-size: 12px; color: var(--text); }
    .comment-text { color: var(--text); line-height: 1.5; margin-bottom: 10px; }
    .comment-meta { font-size: 12px; color: var(--text); margin-bottom: 10px; }
    .comment-actions { display: flex; gap: 8px; }
    .comment-actions button {
        padding: 6px 12px;
        border-radius: 6px;
        border: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        color: white;
    }
    .btn-delete { background: var(--red); }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #10b981; }
    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid var(--red); color: var(--red); }
</style>

<div class="mod-wrap">
    <div class="mod-header">
        <h1><span class="emoji">💬</span> Comment Moderation</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="comment-list">
        <?php foreach ($comments as $c): ?>
        <div class="comment-card">
            <div class="comment-header">
                <div>
                    <span class="comment-author"><?= htmlspecialchars($c['name']) ?></span>
                    <span class="comment-time"> • <?= date('M d, H:i', strtotime($c['created_at'])) ?></span>
                </div>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this comment?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                    <button type="submit" class="btn-delete">🗑️ Delete</button>
                </form>
            </div>
            <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
            <div class="comment-meta">
                On post: "<?= htmlspecialchars(substr($c['post_preview'], 0, 60)) ?>..."<br>
                💰 Spent: <?= $c['coin_spent'] ?> 🪙 | Owner earned: <?= $c['coin_earned_by_owner'] ?> 🪙
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?>
            <p style="color: var(--text); text-align: center; padding: 40px;">No comments yet</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>