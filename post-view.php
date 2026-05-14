<?php
require_once 'includes/functions.php';
require_once 'includes/post-functions.php';

$hashId = $_GET['hash'] ?? $_GET['id'] ?? '';
if (empty($hashId)) {
    header("Location: /user/feed.php");
    exit();
}

$db = getDB();

$stmt = $db->prepare("
    SELECT p.*, u.name as author_name, u.id as author_id, u.avatar,
           (SELECT COUNT(*) FROM user_interactions WHERE post_id = p.id AND type = 'like') as likes_count,
           (SELECT COUNT(*) FROM user_post_comments WHERE post_id = p.id AND status = 'active') as comments_count,
           (SELECT 1 FROM user_interactions WHERE post_id = p.id AND type = 'like' AND user_id = ?) as user_liked,
           (SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = p.user_id) as is_following
    FROM user_posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.hash_id = ? AND p.status = 'active'
");
$stmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['user_id'] ?? 0, $hashId]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: /user/feed.php?error=notfound");
    exit();
}

// Update views
$db->prepare("UPDATE user_posts SET views_count = views_count + 1 WHERE id = ?")->execute([$post['id']]);

// Parse media
$media = getPostMedia($post['media_urls'] ?? null);

$pageTitle = 'Post by ' . $post['author_name'];
include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-6">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="/user/feed.php" class="neu-button px-4 py-2 rounded-lg text-sm inline-flex items-center gap-2">← Back to Feed</a>
    </div>

    <!-- Post Card -->
    <article class="neu-card p-6 mb-6 shadow-lg">
        <!-- Author -->
        <div class="flex items-center justify-between mb-4">
            <a href="/user/@<?php echo urlencode($post['author_name']); ?>" class="flex items-center gap-3 hover:opacity-80">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                    <?php echo strtoupper(substr($post['author_name'], 0, 1)); ?>
                </div>
                <div>
                    <div class="font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($post['author_name']); ?></div>
                    <div class="text-xs text-gray-500"><?php echo timeAgo($post['created_at']); ?></div>
                </div>
            </a>
        </div>

        <!-- Content -->
        <div class="text-gray-800 dark:text-gray-200 mb-6 text-lg leading-relaxed whitespace-pre-wrap">
            <?php echo formatPostContent($post['content']); ?>
        </div>

        <!-- Media Gallery with Auto-play -->
        <?php if (!empty($media)): ?>
        <div class="mb-6 space-y-4">
            <?php foreach ($media as $index => $item): 
                $type = $item['type'] ?? 'image';
            ?>
                <?php if ($type === 'video'): ?>
                <!-- Video Player with Auto-play -->
                <div class="relative rounded-lg overflow-hidden bg-black">
                    <video 
                        controls 
                        autoplay 
                        muted 
                        playsinline
                        class="w-full max-h-[600px] object-contain"
                        poster="<?php echo htmlspecialchars($item['thumbnail'] ?? ''); ?>"
                        onloadeddata="this.muted = false;"
                    >
                        <source src="<?php echo htmlspecialchars($item['original']); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <?php if (!empty($item['duration'])): ?>
                    <div class="absolute bottom-4 right-4 bg-black/70 text-white px-3 py-1 rounded-full text-sm">
                        <?php echo htmlspecialchars($item['duration']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php elseif ($type === 'document'): ?>
                <!-- Document Download -->
                <div class="neu-card p-4 flex items-center gap-4 bg-gray-50 dark:bg-gray-800">
                    <div class="text-4xl"><?php echo $item['icon'] ?? '📄'; ?></div>
                    <div class="flex-grow">
                        <div class="font-bold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($item['name'] ?? 'Document'); ?></div>
                        <div class="text-sm text-gray-500 uppercase"><?php echo htmlspecialchars($item['ext'] ?? 'FILE'); ?> • <?php echo isset($item['size']) ? round($item['size']/1024, 1) . ' KB' : ''; ?></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($item['original']); ?>" download class="neu-button px-4 py-2 rounded-lg text-primary-600 hover:bg-primary-50">
                        ⬇️ Download
                    </a>
                </div>
                
                <?php else: ?>
                <!-- Image -->
                <div class="relative rounded-lg overflow-hidden">
                    <img src="<?php echo htmlspecialchars($item['original']); ?>" 
                         class="w-full max-h-[600px] object-contain cursor-pointer hover:opacity-95 transition"
                         onclick="openLightbox('<?php echo htmlspecialchars($item['original']); ?>')">
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex gap-6">
                <button onclick="toggleLike(<?php echo $post['id']; ?>)" 
                        class="flex items-center gap-2 transition hover:scale-105 <?php echo $post['user_liked'] ? 'text-red-500' : 'text-gray-500 hover:text-red-500'; ?>"
                        id="likeBtn">
                    <span class="text-2xl"><?php echo $post['user_liked'] ? '❤️' : '🤍'; ?></span>
                    <span class="font-bold text-lg" id="likeCount"><?php echo $post['likes_count'] ?? 0; ?></span>
                </button>
                
                <a href="#comments" class="flex items-center gap-2 text-gray-500 hover:text-blue-500 transition">
                    <span class="text-2xl">💬</span>
                    <span class="font-bold text-lg"><?php echo $post['comments_count'] ?? 0; ?></span>
                </a>
            </div>
            <div class="text-sm text-gray-400"><?php echo $post['views_count'] ?? 0; ?> views</div>
        </div>
    </article>

    <!-- Comments Section -->
    <div class="neu-card p-6" id="comments">
        <h3 class="font-bold text-xl mb-6">Comments (<?php echo $post['comments_count'] ?? 0; ?>)</h3>
        
        <?php if (isLoggedIn()): ?>
        <form id="commentForm" class="mb-6" onsubmit="submitComment(event)">
            <div class="flex gap-3">
                <textarea name="content" id="commentInput" rows="2" 
                          class="flex-grow neu-card px-4 py-3 bg-transparent resize-none rounded-lg"
                          placeholder="Add a comment..." required></textarea>
                <button type="submit" class="neu-button px-6 py-2 rounded-lg bg-primary-100 text-primary-700 font-bold self-end">Post</button>
            </div>
        </form>
        <?php endif; ?>

        <div id="commentsList" class="space-y-4">
            <div class="text-center text-gray-500 py-4">Loading comments...</div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="hidden fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4" onclick="closeLightbox()">
    <img id="lightboxImg" src="" class="max-w-full max-h-[90vh] object-contain">
    <button class="absolute top-4 right-4 text-white text-4xl">&times;</button>
</div>

<script>
// Auto-play video handling
document.addEventListener('DOMContentLoaded', function() {
    const videos = document.querySelectorAll('video');
    videos.forEach(video => {
        // Ensure video plays
        video.play().catch(e => {
            console.log('Autoplay prevented:', e);
            // Show play button if autoplay blocked
            video.controls = true;
        });
    });
    
    loadComments();
});

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.remove('hidden');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
}

async function toggleLike(postId) {
    <?php if (!isLoggedIn()): ?>
    window.location.href = '/auth/login.php';
    return;
    <?php endif; ?>
    
    try {
        const response = await fetch('/api/post-actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=like&post_id=${postId}&csrf_token=<?php echo $_SESSION['csrf_token'] ?? ''; ?>`
        });
        const data = await response.json();
        
        if (data.success) {
            const btn = document.getElementById('likeBtn');
            const count = document.getElementById('likeCount');
            const icon = btn.querySelector('span:first-child');
            
            if (data.action === 'liked') {
                btn.classList.remove('text-gray-500');
                btn.classList.add('text-red-500');
                icon.textContent = '❤️';
                count.textContent = data.new_count;
            } else {
                btn.classList.add('text-gray-500');
                btn.classList.remove('text-red-500');
                icon.textContent = '🤍';
                count.textContent = data.new_count;
            }
        }
    } catch (err) {
        console.error('Like error:', err);
    }
}

async function submitComment(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('post_id', <?php echo $post['id']; ?>);
    formData.append('action', 'comment');
    
    try {
        const response = await fetch('/api/post-actions.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('commentInput').value = '';
            loadComments();
        }
    } catch (err) {
        console.error('Comment error:', err);
    }
}

async function loadComments() {
    try {
        const response = await fetch(`/api/post-actions.php?action=get_comments&post_id=<?php echo $post['id']; ?>`);
        const data = await response.json();
        const container = document.getElementById('commentsList');
        
        if (data.success && data.comments.length > 0) {
            container.innerHTML = data.comments.map(c => `
                <div class="flex gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-purple-600 flex items-center justify-center text-white font-bold">
                        ${c.author_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="font-bold text-sm">${c.author_name}</div>
                        <div class="text-gray-700 dark:text-gray-300 mt-1">${c.content}</div>
                        <div class="text-xs text-gray-500 mt-1">${new Date(c.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="text-center text-gray-500 py-4">No comments yet. Be the first!</div>';
        }
    } catch (err) {
        console.error('Load comments error:', err);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
