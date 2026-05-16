<?php
// user/feed.php — Public social feed with pagination
// COMPLETE REWRITE: Bulletproof version, handles all edge cases

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

requireLogin();

$user = getCurrentUser();
$pageTitle = 'Feed';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// ============================================================
// STEP 1: Check which tables exist (using INFORMATION_SCHEMA)
// ============================================================
$hasFollows = false;
$hasSubs = false;
$hasTokens = false;

try {
    $stmt = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_follows' LIMIT 1");
    $hasFollows = (bool)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'creator_subscriptions' LIMIT 1");
    $hasSubs = (bool)$stmt->fetchColumn();
} catch (Exception $e) {}

try {
    $stmt = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_tokens' LIMIT 1");
    $hasTokens = (bool)$stmt->fetchColumn();
} catch (Exception $e) {}

// ============================================================
// STEP 2: Build WHERE clause dynamically
// ============================================================
$whereParts = ["p.visibility = 'public'", "(p.visibility = 'private' AND p.user_id = :uid)"];
$params = [':uid' => $user['user_id']];
$paramIdx = 2;

if ($hasFollows) {
    $whereParts[] = "(p.visibility = 'followers' AND EXISTS (SELECT 1 FROM user_follows uf WHERE uf.follower_id = :uid{$paramIdx} AND uf.following_id = p.user_id))";
    $params[":uid{$paramIdx}"] = $user['user_id'];
    $paramIdx++;
}
if ($hasSubs) {
    $whereParts[] = "(p.visibility = 'subscribers' AND EXISTS (SELECT 1 FROM creator_subscriptions cs WHERE cs.subscriber_id = :uid{$paramIdx} AND cs.creator_id = p.user_id AND cs.status = 'active' AND cs.expires_at > NOW()))";
    $params[":uid{$paramIdx}"] = $user['user_id'];
    $paramIdx++;
}
if ($hasTokens) {
    $whereParts[] = "(p.visibility = 'token_gated' AND EXISTS (SELECT 1 FROM user_tokens ut WHERE ut.user_id = :uid{$paramIdx} AND ut.token_id = p.token_gate_id AND ut.quantity > 0))";
    $params[":uid{$paramIdx}"] = $user['user_id'];
    $paramIdx++;
}

$whereClause = implode(' OR ', $whereParts);

// ============================================================
// STEP 3: Get total count
// ============================================================
$totalPosts = 0;
try {
    $countSql = "SELECT COUNT(*) FROM user_posts p WHERE p.status = 'active' AND ({$whereClause})";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $totalPosts = (int)$countStmt->fetchColumn();
} catch (Exception $e) {
    error_log('Feed count error: ' . $e->getMessage());
    try {
        $totalPosts = (int)$pdo->query("SELECT COUNT(*) FROM user_posts WHERE status = 'active' AND visibility = 'public'")->fetchColumn();
    } catch (Exception $e2) {}
}

$totalPages = max(1, ceil($totalPosts / $perPage));
$page = min($page, $totalPages);

// ============================================================
// STEP 4: Fetch posts
// ============================================================
$posts = [];
try {
    $sql = "SELECT p.*, u.name, u.avatar, u.user_id as author_id,
               (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.post_id) as likes_count,
               (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.post_id) as comments_count,
               (SELECT COUNT(*) FROM post_reposts r WHERE r.post_id = p.post_id) as reposts_count
        FROM user_posts p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.status = 'active'
        AND ({$whereClause})
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Feed fetch error: ' . $e->getMessage());
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name, u.avatar, u.user_id as author_id,
                   (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.post_id) as likes_count,
                   (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.post_id) as comments_count,
                   (SELECT COUNT(*) FROM post_reposts r WHERE r.post_id = p.post_id) as reposts_count
            FROM user_posts p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.status = 'active' AND p.visibility = 'public'
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $posts = $stmt->fetchAll();
    } catch (Exception $e2) {
        error_log('Feed ultimate fallback error: ' . $e2->getMessage());
    }
}

// Check which posts user liked
$userLikes = [];
try {
    $stmt = $pdo->prepare("SELECT post_id FROM post_likes WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $userLikes = array_column($stmt->fetchAll(), 'post_id');
} catch (Exception $e) {}

// Get available gifts
$gifts = [];
try {
    $gifts = getGifts(true);
} catch (Exception $e) {}

// Get unread notifications
$notifications = [];
try {
    $notifications = getUnreadNotifications($user['user_id']);
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
:root {
    --bg: #08080c; --card: #0f0f14; --border: #1a1a22;
    --text: #a1a1aa; --bright: #ffffff; --accent: #6366f1;
    --green: #10b981; --red: #ef4444; --yellow: #f59e0b;
}

.feed-wrap { max-width: 700px; margin: 0 auto; padding: 16px; }

.feed-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.feed-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }
.feed-actions { display: flex; gap: 10px; align-items: center; }
.feed-actions a {
    padding: 8px 16px;
    border-radius: 20px;
    background: var(--accent);
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}
.feed-actions .coin-display {
    color: var(--green);
    font-weight: 700;
    font-size: 14px;
}

/* Post Card */
.post-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
    transition: all 0.2s;
}
.post-card:hover { border-color: #2a2a35; }
.post-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.post-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: bold; font-size: 16px;
    flex-shrink: 0;
}
.post-meta { flex: 1; min-width: 0; }
.post-author { font-size: 15px; font-weight: 700; color: var(--bright); }
.post-time { font-size: 12px; color: var(--text); }
.post-content {
    font-size: 15px;
    color: var(--bright);
    line-height: 1.5;
    margin-bottom: 12px;
    white-space: pre-wrap;
    word-break: break-word;
}
.post-media {
    width: 100%;
    border-radius: 12px;
    margin-bottom: 12px;
    max-height: 400px;
    object-fit: cover;
}
.post-stats {
    display: flex;
    gap: 16px;
    padding: 12px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
    font-size: 13px;
    color: var(--text);
}
.post-actions {
    display: flex;
    justify-content: space-around;
    gap: 8px;
}
.post-action {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    border: none;
    background: transparent;
    color: var(--text);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    flex: 1;
    justify-content: center;
}
.post-action:hover { background: #15151d; color: var(--bright); }
.post-action.liked { color: var(--red); }
.post-action.liked:hover { background: rgba(239,68,68,0.1); }

/* Comments */
.comments-section {
    display: none;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}
.comments-section.open { display: block; }
.comment-input {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.comment-input input {
    flex: 1;
    background: #1a1a22;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 10px 16px;
    color: var(--bright);
    font-size: 14px;
    font-family: inherit;
}
.comment-input input:focus { outline: none; border-color: var(--accent); }
.comment-input button {
    background: var(--accent);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 700;
    cursor: pointer;
}
.comment-item {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.comment-item:last-child { border-bottom: none; }
.comment-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: bold; font-size: 12px;
    flex-shrink: 0;
}
.comment-body { flex: 1; min-width: 0; }
.comment-author { font-size: 13px; font-weight: 700; color: var(--bright); margin-bottom: 2px; }
.comment-text { font-size: 14px; color: var(--text); line-height: 1.4; word-break: break-word; }
.comment-time { font-size: 11px; color: var(--text); opacity: 0.7; margin-top: 2px; }

/* Gift Modal */
.gift-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.gift-modal.open { display: flex; }
.gift-modal-content {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    max-width: 400px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
}
.gift-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 16px;
}
.gift-option {
    background: #1a1a22;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.gift-option:hover { border-color: var(--accent); }
.gift-option.selected { border-color: var(--yellow); background: rgba(245,158,11,0.1); }
.gift-option .emoji { font-size: 32px; display: block; margin-bottom: 6px; }
.gift-option .price { font-size: 12px; color: var(--green); font-weight: 700; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 24px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}
.pagination a {
    background: var(--card);
    border: 1px solid var(--border);
    color: var(--text);
}
.pagination a:hover { border-color: var(--accent); color: var(--bright); }
.pagination .current {
    background: var(--accent);
    color: white;
}
.pagination .disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text);
}
.empty-state .emoji { font-size: 48px; margin-bottom: 16px; display: block; }

/* Success Banner */
.success-banner {
    background: rgba(16,185,129,0.1);
    border: 1px solid var(--green);
    color: var(--green);
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 14px;
    font-weight: 500;
}

/* Notification Bell */
.notif-bell {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 100;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 44px; height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 20px;
}
.notif-bell .badge {
    position: absolute;
    top: -4px; right: -4px;
    background: var(--red); color: white;
    font-size: 11px; font-weight: 700;
    padding: 2px 6px; border-radius: 10px;
    min-width: 18px; text-align: center;
}

/* Mobile */
@media (max-width: 640px) {
    .feed-wrap { padding: 10px; }
    .feed-header h1 { font-size: 20px; }
    .post-actions { gap: 2px; }
    .post-action { padding: 6px 8px; font-size: 12px; }
    .gift-grid { grid-template-columns: repeat(2, 1fr); }
    .pagination a, .pagination span { padding: 6px 10px; font-size: 12px; }
    .post-content { font-size: 14px; }
}
</style>

<div class="feed-wrap">
    <div class="feed-header">
        <h1>📱 Feed</h1>
        <div class="feed-actions">
            <a href="/user/posts/create.php">✍️ New Post</a>
            <span class="coin-display">🪙 <?= number_format($user['coin_balance'] ?? 0, 0) ?></span>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'posted'): ?>
        <div class="success-banner">
            ✅ Post published successfully! 
            <?php if (isset($_GET['cost'])): ?>
                <span style="color:#ef4444;">-<?= (float)$_GET['cost'] ?> 🪙 charged</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Notification Bell -->
    <div class="notif-bell" onclick="toggleNotifications()">
        🔔
        <?php if (count($notifications) > 0): ?>
            <span class="badge"><?= count($notifications) ?></span>
        <?php endif; ?>
    </div>

    <!-- DEBUG INFO (remove after testing) -->
    <!-- 
    <div style="background:#1a1a22; border:1px solid var(--border); border-radius:8px; padding:10px; margin-bottom:16px; font-size:12px; color:var(--text);">
        Total Posts: <?= $totalPosts ?> | Page: <?= $page ?>/<?= $totalPages ?> | 
        HasFollows: <?= $hasFollows ? 'Yes' : 'No' ?> | 
        HasSubs: <?= $hasSubs ? 'Yes' : 'No' ?> | 
        HasTokens: <?= $hasTokens ? 'Yes' : 'No' ?> | 
        Posts Loaded: <?= count($posts) ?>
    </div>
    -->

    <!-- Posts -->
    <?php foreach ($posts as $post): 
        $isLiked = in_array($post['post_id'], $userLikes);
        $timeAgo = timeAgo($post['created_at']);
    ?>
    <div class="post-card" data-post-id="<?= $post['post_id'] ?>">
        <div class="post-header">
            <div class="post-avatar"><?= strtoupper(substr($post['name'], 0, 1)) ?></div>
            <div class="post-meta">
                <div class="post-author"><?= htmlspecialchars($post['name']) ?></div>
                <div class="post-time"><?= $timeAgo ?></div>
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
            <span>❤️ <?= number_format($post['likes_count']) ?></span>
            <span>💬 <?= number_format($post['comments_count']) ?></span>
            <span>🔄 <?= number_format($post['reposts_count']) ?></span>
            <span>👁️ <?= number_format($post['view_count'] ?? 0) ?></span>
        </div>

        <div class="post-actions">
            <button class="post-action <?= $isLiked ? 'liked' : '' ?>" onclick="toggleLike('<?= $post['post_id'] ?>', this)">
                <?= $isLiked ? '❤️' : '🤍' ?> Like
            </button>
            <button class="post-action" onclick="toggleComments('<?= $post['post_id'] ?>')">
                💬 Comment
            </button>
            <button class="post-action" onclick="openGiftModal('<?= $post['post_id'] ?>', '<?= $post['author_id'] ?>')">
                🎁 Gift
            </button>
            <button class="post-action" onclick="sharePost('<?= $post['post_id'] ?>')">
                🔗 Share
            </button>
        </div>

        <!-- Comments Section -->
        <div class="comments-section" id="comments-<?= $post['post_id'] ?>">
            <div class="comment-input">
                <input type="text" id="comment-<?= $post['post_id'] ?>" placeholder="Add a comment... (costs 1 🪙)">
                <button onclick="addComment('<?= $post['post_id'] ?>')">Post</button>
            </div>
            <div class="comments-list" id="comments-list-<?= $post['post_id'] ?>">
                <!-- Comments loaded via AJAX -->
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <span class="emoji">📭</span>
            <h3 style="color: var(--bright); margin-bottom: 8px;">No posts yet</h3>
            <p>Be the first to share something!</p>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">← Prev</a>
        <?php else: ?>
            <span class="disabled">← Prev</span>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): 
        ?>
            <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Next →</a>
        <?php else: ?>
            <span class="disabled">Next →</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Gift Modal -->
<div class="gift-modal" id="giftModal">
    <div class="gift-modal-content">
        <h3 style="color: var(--bright); margin-bottom: 8px;">Send a Gift 🎁</h3>
        <p style="color: var(--text); font-size: 14px; margin-bottom: 16px;">Select a gift to send. 10 🪙 fee applies.</p>
        <div class="gift-grid" id="giftGrid">
            <?php foreach ($gifts as $g): ?>
            <div class="gift-option" data-gift-id="<?= $g['gift_id'] ?>" data-price="<?= $g['coin_price'] ?>" onclick="selectGift(this)">
                <span class="emoji">🎁</span>
                <div style="font-size: 13px; color: var(--bright); font-weight: 600;"><?= htmlspecialchars($g['name']) ?></div>
                <div class="price">🪙 <?= number_format($g['coin_price'], 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 16px; display: flex; gap: 8px;">
            <button onclick="closeGiftModal()" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--text); font-weight: 600; cursor: pointer;">Cancel</button>
            <button onclick="sendGift()" style="flex: 1; padding: 12px; border-radius: 10px; border: none; background: var(--yellow); color: #000; font-weight: 700; cursor: pointer;">Send Gift</button>
        </div>
    </div>
</div>

<script>
let selectedGiftId = null;
let selectedReceiverId = null;
let selectedPostId = null;

function toggleLike(postId, btn) {
    const isLiked = btn.classList.contains('liked');
    const action = isLiked ? 'unlike' : 'like';

    fetch('/api/like-post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId) + '&action=' + action + '&csrf_token=' + encodeURIComponent('<?= generateCSRFToken() ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('liked');
            btn.innerHTML = isLiked ? '🤍 Like' : '❤️ Liked';
        } else {
            alert(data.error || 'Failed');
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function toggleComments(postId) {
    const section = document.getElementById('comments-' + postId);
    section.classList.toggle('open');
    if (section.classList.contains('open')) {
        fetch('/api/get-comments.php?post_id=' + encodeURIComponent(postId))
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('comments-list-' + postId);
            list.innerHTML = (data.comments || []).map(c => `
                <div class="comment-item">
                    <div class="comment-avatar">${c.name.charAt(0).toUpperCase()}</div>
                    <div class="comment-body">
                        <div class="comment-author">${escapeHtml(c.name)}</div>
                        <div class="comment-text">${escapeHtml(c.content)}</div>
                        <div class="comment-time">${escapeHtml(c.time)}</div>
                    </div>
                </div>
            `).join('');
        })
        .catch(e => console.error('Comments load failed', e));
    }
}

function addComment(postId) {
    const input = document.getElementById('comment-' + postId);
    const content = input.value.trim();
    if (!content) return;

    fetch('/api/comment-post.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'post_id=' + encodeURIComponent(postId) + '&content=' + encodeURIComponent(content) + '&csrf_token=' + encodeURIComponent('<?= generateCSRFToken() ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            toggleComments(postId);
            setTimeout(() => toggleComments(postId), 100);
        } else {
            alert(data.error || 'Failed to comment');
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function openGiftModal(postId, receiverId) {
    selectedPostId = postId;
    selectedReceiverId = receiverId;
    document.getElementById('giftModal').classList.add('open');
}

function closeGiftModal() {
    document.getElementById('giftModal').classList.remove('open');
    selectedGiftId = null;
    document.querySelectorAll('.gift-option').forEach(g => g.classList.remove('selected'));
}

function selectGift(el) {
    document.querySelectorAll('.gift-option').forEach(g => g.classList.remove('selected'));
    el.classList.add('selected');
    selectedGiftId = el.dataset.giftId;
}

function sendGift() {
    if (!selectedGiftId || !selectedReceiverId) return alert('Select a gift first');

    fetch('/api/gift_send.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'receiver_id=' + encodeURIComponent(selectedReceiverId) + '&gift_id=' + encodeURIComponent(selectedGiftId) + '&csrf_token=' + encodeURIComponent('<?= generateCSRFToken() ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeGiftModal();
        } else {
            alert(data.error || 'Failed to send gift');
        }
    })
    .catch(e => alert('Error: ' + e.message));
}

function sharePost(postId) {
    const url = window.location.origin + '/user/posts/view.php?id=' + encodeURIComponent(postId);
    if (navigator.share) {
        navigator.share({ title: 'Check out this post', url: url });
    } else {
        navigator.clipboard.writeText(url).then(() => alert('Link copied to clipboard!'));
    }
}

function toggleNotifications() {
    alert('<?= count($notifications) ?> unread notifications');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Record views with data-viewed attribute to prevent double-counting
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.dataset.viewed) {
            const postId = entry.target.dataset.postId;
            fetch('/api/view-post.php?post_id=' + encodeURIComponent(postId));
            entry.target.dataset.viewed = 'true';
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.post-card').forEach(post => observer.observe(post));

function timeAgo(datetime) {
    const time = new Date(datetime).getTime();
    const now = Date.now();
    const diff = Math.floor((now - time) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(time).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>