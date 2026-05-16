<?php
// user/profile.php — Public Profile View
// Shows user's posts, badges, tokens, subscribe button, follow button, stats

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Get target user ID from URL
$targetUserId = $_GET['id'] ?? '';
if (empty($targetUserId)) {
    header('Location: /user/feed.php');
    exit;
}

$currentUser = getCurrentUser();
$isOwnProfile = $currentUser && $currentUser['user_id'] === $targetUserId;

// Fetch target user
$stmt = $pdo->prepare("
    SELECT user_id, name, email, avatar, bio, role, coin_balance, subscription_price, created_at, 
           (SELECT COUNT(*) FROM user_posts WHERE user_id = u.user_id AND status = 'active') as post_count,
           (SELECT COUNT(*) FROM user_follows WHERE following_id = u.user_id) as follower_count,
           (SELECT COUNT(*) FROM user_follows WHERE follower_id = u.user_id) as following_count
    FROM users u WHERE u.user_id = ? AND u.is_banned = 0
");
$stmt->execute([$targetUserId]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    header('HTTP/1.0 404 Not Found');
    $pageTitle = 'User Not Found';
    include __DIR__ . '/../includes/header.php';
    echo '<div class="max-w-3xl mx-auto px-4 py-12 text-center"><h1 class="text-3xl font-bold text-white mb-4">User Not Found</h1><p class="text-gray-400">This user does not exist or has been banned.</p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($profileUser['name']) . "'s Profile";

// Check follow status
$isFollowing = false;
$isSubscribed = false;
if ($currentUser && !$isOwnProfile) {
    $followStmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $followStmt->execute([$currentUser['user_id'], $targetUserId]);
    $isFollowing = (bool)$followStmt->fetch();

    $subStmt = $pdo->prepare("
        SELECT 1 FROM creator_subscriptions 
        WHERE subscriber_id = ? AND creator_id = ? AND status = 'active' AND expires_at > NOW()
    ");
    $subStmt->execute([$currentUser['user_id'], $targetUserId]);
    $isSubscribed = (bool)$subStmt->fetch();
}

// Fetch user's badges
$badges = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.badge_id, b.name, b.icon, b.color, ub.acquired_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.acquired_at DESC
    ");
    $stmt->execute([$targetUserId]);
    $badges = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch user's tokens
$tokens = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.token_id, t.name, t.symbol, t.icon, ut.quantity
        FROM user_tokens ut
        JOIN tokens t ON ut.token_id = t.token_id
        WHERE ut.user_id = ? AND ut.quantity > 0
        ORDER BY ut.quantity DESC
    ");
    $stmt->execute([$targetUserId]);
    $tokens = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch user's public posts
$posts = [];
try {
    $visibilityFilter = $isOwnProfile ? "p.visibility IN ('public', 'followers', 'subscribers', 'private')" : "p.visibility = 'public'";
    if (!$isOwnProfile && $isFollowing) {
        $visibilityFilter = "p.visibility IN ('public', 'followers')";
    }
    if (!$isOwnProfile && $isSubscribed) {
        $visibilityFilter = "p.visibility IN ('public', 'followers', 'subscribers')";
    }

    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.post_id) as likes_count,
               (SELECT COUNT(*) FROM post_comments c WHERE c.post_id = p.post_id) as comments_count,
               (SELECT COUNT(*) FROM post_reposts r WHERE r.post_id = p.post_id) as reposts_count
        FROM user_posts p
        WHERE p.user_id = ? AND p.status = 'active' AND ($visibilityFilter)
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$targetUserId]);
    $posts = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Profile posts error: ' . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-6">
    <!-- Profile Header -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <div class="flex flex-col md:flex-row gap-6 items-start">
            <div class="relative">
                <img src="<?= htmlspecialchars($profileUser['avatar'] ?: '/assets/default-avatar.png') ?>" 
                    class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-gray-700">
                <?php if ($profileUser['role'] === 'admin'): ?>
                <div class="absolute -bottom-1 -right-1 bg-red-600 text-white text-xs px-2 py-0.5 rounded-full">ADMIN</div>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl md:text-3xl font-bold text-white mb-1"><?= htmlspecialchars($profileUser['name']) ?></h1>
                <p class="text-gray-400 text-sm mb-3">Joined <?= date('F Y', strtotime($profileUser['created_at'])) ?></p>

                <?php if (!empty($profileUser['bio'])): ?>
                <p class="text-gray-300 mb-4 max-w-xl"><?= nl2br(htmlspecialchars($profileUser['bio'])) ?></p>
                <?php endif; ?>

                <div class="flex flex-wrap gap-4 text-sm mb-4">
                    <div class="text-center">
                        <div class="font-bold text-white text-lg"><?= number_format($profileUser['post_count']) ?></div>
                        <div class="text-gray-400">Posts</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-white text-lg"><?= number_format($profileUser['follower_count']) ?></div>
                        <div class="text-gray-400">Followers</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-white text-lg"><?= number_format($profileUser['following_count']) ?></div>
                        <div class="text-gray-400">Following</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-yellow-400 text-lg">🪙 <?= number_format($profileUser['coin_balance'], 0) ?></div>
                        <div class="text-gray-400">Coins</div>
                    </div>
                </div>

                <?php if ($currentUser && !$isOwnProfile): ?>
                <div class="flex gap-2">
                    <form method="POST" action="/api/follow-user.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($targetUserId) ?>">
                        <button type="submit" class="px-5 py-2 rounded-lg font-medium transition <?= $isFollowing ? 'bg-gray-700 text-white hover:bg-gray-600' : 'bg-blue-600 text-white hover:bg-blue-500' ?>">
                            <?= $isFollowing ? '✓ Following' : '+ Follow' ?>
                        </button>
                    </form>

                    <?php if ($profileUser['subscription_price'] > 0): ?>
                    <form method="POST" action="/api/subscribe.php" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="creator_id" value="<?= htmlspecialchars($targetUserId) ?>">
                        <input type="hidden" name="price" value="<?= $profileUser['subscription_price'] ?>">
                        <button type="submit" class="px-5 py-2 rounded-lg font-medium transition <?= $isSubscribed ? 'bg-green-700 text-white' : 'bg-purple-600 text-white hover:bg-purple-500' ?>">
                            <?= $isSubscribed ? '✓ Subscribed' : '🪙 Subscribe (' . number_format($profileUser['subscription_price'], 0) . '/mo)' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php elseif ($isOwnProfile): ?>
                <a href="/user/settings.php" class="inline-block px-5 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition">Edit Profile</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Badges -->
    <?php if (!empty($badges)): ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">🏅 Badges</h2>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($badges as $badge): ?>
            <div class="flex items-center gap-2 bg-gray-700/50 rounded-full px-3 py-1.5 border border-gray-600" title="Acquired <?= date('M d, Y', strtotime($badge['acquired_at'])) ?>">
                <span class="text-lg"><?= $badge['icon'] ?></span>
                <span class="text-sm text-gray-300"><?= htmlspecialchars($badge['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tokens -->
    <?php if (!empty($tokens)): ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">🪙 Tokens</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php foreach ($tokens as $token): ?>
            <div class="bg-gray-700/50 rounded-lg p-3 border border-gray-600 text-center">
                <div class="text-2xl mb-1"><?= $token['icon'] ?></div>
                <div class="text-sm font-semibold text-white"><?= htmlspecialchars($token['name']) ?></div>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($token['symbol']) ?></div>
                <div class="text-sm text-yellow-400 font-bold mt-1"><?= number_format($token['quantity']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Posts -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-white mb-4">📝 Posts</h2>
        <?php if (empty($posts)): ?>
        <p class="text-gray-500 text-center py-8">No public posts yet.</p>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($posts as $post): ?>
            <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-700 hover:border-gray-600 transition">
                <div class="flex items-center gap-3 mb-3">
                    <img src="<?= htmlspecialchars($profileUser['avatar'] ?: '/assets/default-avatar.png') ?>" class="w-8 h-8 rounded-full object-cover">
                    <div>
                        <span class="text-white font-medium text-sm"><?= htmlspecialchars($profileUser['name']) ?></span>
                        <span class="text-gray-500 text-xs ml-2"><?= timeAgo($post['created_at']) ?></span>
                    </div>
                    <?php if ($post['visibility'] !== 'public'): ?>
                    <span class="ml-auto text-xs px-2 py-0.5 rounded bg-gray-700 text-gray-400"><?= ucfirst($post['visibility']) ?></span>
                    <?php endif; ?>
                </div>
                <a href="/user/posts/view.php?id=<?= $post['post_id'] ?>" class="block">
                    <p class="text-gray-300 mb-3"><?= nl2br(htmlspecialchars(truncateText($post['content'], 200))) ?></p>
                    <?php if ($post['media_urls']): 
                        $media = json_decode($post['media_urls'], true);
                        if (!empty($media)): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-3">
                            <?php foreach (array_slice($media, 0, 3) as $m): ?>
                            <img src="<?= htmlspecialchars($m) ?>" class="w-full h-32 object-cover rounded-lg">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; 
                    endif; ?>
                </a>
                <div class="flex gap-4 text-sm text-gray-400">
                    <span>❤️ <?= $post['likes_count'] ?></span>
                    <span>💬 <?= $post['comments_count'] ?></span>
                    <span>🔄 <?= $post['reposts_count'] ?></span>
                    <span>👁️ <?= $post['view_count'] ?? 0 ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>