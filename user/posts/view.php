<?php
// user/posts/view.php — Single post view with shareable URL
// Anonymous users CAN view public posts

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/economy.php';

$postId = $_GET['id'] ?? '';
if (empty($postId)) {
    header('Location: /user/feed.php');
    exit;
}

// Get post
$post = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name, u.avatar, u.user_id as author_id,
               (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.post_id) as likes_count,
               (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.post_id) as comments_count,
               (SELECT COUNT(*) FROM post_reposts r WHERE r.post_id = p.post_id) as reposts_count
        FROM user_posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.post_id = ? AND p.status = 'active' AND p.visibility = 'public'
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
} catch (Exception $e) {}

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post Not Found';
    include __DIR__ . '/../../includes/header.php';
    echo '<div style="max-width:700px;margin:60px auto;text-align:center;color:#a1a1aa;">
        <div style="font-size:64px;margin-bottom:16px;">📭</div>
        <h2 style="color:#fff;margin-bottom:8px;">Post not found</h2>
        <p>This post may have been deleted or is private.</p>
        <a href="/user/feed.php" style="color:#6366f1;font-weight:700;">Back to Feed</a>
    </div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$pageTitle = 'Post by ' . $post['name'];

// Get comments
$comments = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name 
        FROM post_comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();
} catch (Exception $e) {}

// Record view (anonymous OK)
try {
    $viewerId = isLoggedIn() ? getCurrentUser()['user_id'] : null;
    $viewerIp = $_SERVER['REMOTE_ADDR'] ?? null;
    recordPostView($postId, $viewerId, $viewerIp);
} catch (Exception $e) {}

$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getCurrentUser() : null;
$userLikes = [];

if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $user['user_id']]);
        $userLikes = $stmt->fetch();
    } catch (Exception $e) {}
}

$shareUrl = 'https://akkuapps.in/user/posts/view.php?id=' . urlencode($postId);

include __DIR__ . '/../../includes/header.php';
?>

<style>
:root {
    --bg: #08080c; --card: #0f0f14; --border: #1a1a22;
    --text: #a1a1aa; --bright: #ffffff; --accent: #6366f1;
    --green: #10b981; --red: #ef4444;
}
.view-wrap { max-width: 700px; margin: 0 auto; padding: 16px; }

.post-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}
.post-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.post-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: bold; font-size: 18px;
}
.post-meta { flex: 1; }
.post-author { font-size: 16px; font-weight: 700; color: var(--bright); }
.post-time { font-size: 13px; color: var(--text); }
.post-content {
    font-size: 16px;
    color: var(--bright);
    line-height: 1.6;
    margin-bottom: 16px;
    white-space: pre-wrap;
    word-break: break-word;
}
.post-media {
    width: 100%;
    border-radius: 12px;
    margin-bottom: 16px;
    max-height: 500px;
    object-fit: cover;
}
.post-stats {
    display: flex;
    gap: 20px;
    padding: 14px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    margin-bottom: 14px;
    font-size: 14px;
    color: var(--text);
}
.post-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}
.post-action {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: 20px;
    border: none;
    background: transparent;
    color: var(--text);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}
.post-action:hover { background: #15151d; color: var(--bright); }
.post-action.liked { color: var(--red); }

.share-box {
    background: #15151d;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}
.share-box input {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--text);
    font-size: 13px;
}
.share-box button {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    background: var(--accent);
    color: white;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}

.comments-section { margin-top: 20px; }
.comments-section h3 {
    font-size: 16px;
    color: var(--bright);
    margin-bottom: 14px;
    font-weight: 700;
}
.comment-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.comment-item:last-child { border-bottom: none; }
.comment-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: bold; font-size: 14px;
    flex-shrink: 0;
}
.comment-body { flex: 1; }
.comment-author { font-size: 14px; font-weight: 700; color: var(--bright); margin-bottom: 2px; }
.comment-text { font-size: 15px; color: var(--text); line-height: 1.5; word-break: break-word; }
.comment-time { font-size: 12px; color: var(--text); opacity: 0.7; margin-top: 4px; }

.login-prompt {
    text-align: center;
    padding: 30px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 20px;
}
.login-prompt a {
    color: var(--accent);
    font-weight: 700;
    text-decoration: none;
}

@media (max-width: 640px) {
    .view-wrap { padding: 10px; }
    .post-content { font-size: 15px; }
    .post-action { padding: 8px 14px; font-size: 13px; }
}
</style>

<div class="view-wrap">
    <div style="margin-bottom: 16px;">
        <a href="/user/feed.php" style="color: var(--text); text-decoration: none; font-size: 14px;">← Back to Feed</a>
    </div>

    <div class="post-card">
        <div class="post-header">
            <div class="post-avatar"><?= strtoupper(substr($post['name'], 0, 1)) ?></div>
            <div class="post-meta">
                <div class="post-author"><?= htmlspecialchars($post['name']) ?></div>
                <div class="post-time"><?= date('M d, Y • H:i', strtotime($post['created_at'])) ?></div>
            </div>
        </div>

        <div class="post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>

        <?php if (!empty($post['media_urls'])): 
            $media = json_decode($post['media_urls'], true);
            if ($media && is_array($media)):
                foreach ($media as $url):
        ?>
        <img src="<?= htmlspecialchars($url) ?>" class="post-media" alt="Post media" loading="lazy" onerror="this.style.display='none'">
        <?php endforeach; endif; endif; ?>

        <div class="post-stats">
            <span>❤️ <?= number_format($post['likes_count']) ?> likes</span>
            <span>💬 <?= number_format($post['comments_count']) ?> comments</span>
            <span>🔄 <?= number_format($post['reposts_count']) ?> reposts</span>
            <span>👁️ <?= number_format($post['view_count'] ?? 0) ?> views</span>
        </div>

        <div class="post-actions">
            <?php if ($isLoggedIn): ?>
                <button class="post-action <?= $userLikes ? 'liked' : '' ?>" onclick="toggleLike('<?= $postId ?>', this)">
                    <?= $userLikes ? '❤️ Liked' : '🤍 Like' ?>
                </button>
                <button class="post-action" onclick="location.href='/user/feed.php'">
                    💬 Comment
                </button>
            <?php else: ?>
                <button class="post-action" onclick="location.href='/auth/login.php'">
                    🔑 Login to Like
                </button>
            <?php endif; ?>
            <button class="post-action" onclick="copyLink()">
                🔗 Copy Link
            </button>
        </div>

        <div class="share-box">
            <input type="text" value="<?= htmlspecialchars($shareUrl) ?>" readonly id="shareUrl">
            <button onclick="copyLink()">Copy</button>
        </div>
    </div>

    <!-- Comments -->
    <div class="comments-section">
        <h3>💬 Comments (<?= count($comments) ?>)</h3>
        
        <?php if (!$isLoggedIn): ?>
            <div class="login-prompt">
                <a href="/auth/login.php">🔑 Login</a> or <a href="/auth/register.php">✨ Register</a> to comment
            </div>
        <?php endif; ?>

        <?php foreach ($comments as $c): ?>
        <div class="comment-item">
            <div class="comment-avatar"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
            <div class="comment-body">
                <div class="comment-author"><?= htmlspecialchars($c['name']) ?></div>
                <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                <div class="comment-time"><?= date('M d, H:i', strtotime($c['created_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($comments)): ?>
            <p style="color: var(--text); text-align: center; padding: 20px;">No comments yet. Be the first!</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleLike(postId, btn) {
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'unlike' : 'like';

    fetch('/api/like-post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId) + '&action=' + action + '&csrf_token=' + encodeURIComponent('<?= $isLoggedIn ? generateCSRFToken() : '' ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('liked');
            btn.innerHTML = isLiked ? '🤍 Like' : '❤️ Liked';
        } else {
            alert(data.error || 'Failed');
        }
    });
}

function copyLink() {
    const input = document.getElementById('shareUrl');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>