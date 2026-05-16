<?php
// user/posts/create.php – Create user post with visibility & coin gates
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/economy.php';
requireLogin();

$pageTitle = 'Create Post';
include __DIR__ . '/../../includes/header.php';

$user = getCurrentUser();
$error = '';
$message = '';

// Post creation cost
define('POST_CREATION_COST', 2.00);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $content = trim($_POST['content'] ?? '');
        $visibility = $_POST['visibility'] ?? 'public';
        $tokenId = !empty($_POST['token_id']) ? $_POST['token_id'] : null;
        $coinPrice = floatval($_POST['coin_price'] ?? 0);

        // Handle media uploads
        $mediaUrls = [];
        if (!empty($_FILES['media']['tmp_name'][0])) {
            $uploadDir = __DIR__ . '/../../uploads/posts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['media']['tmp_name'] as $i => $tmpName) {
                if ($_FILES['media']['error'][$i] === 0 && is_uploaded_file($tmpName)) {
                    $ext = strtolower(pathinfo($_FILES['media']['name'][$i], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'];
                    
                    if (in_array($ext, $allowed)) {
                        $filename = generateUUID() . '.' . $ext;
                        $filepath = $uploadDir . $filename;
                        $webpath = '/uploads/posts/' . $filename;

                        if (move_uploaded_file($tmpName, $filepath)) {
                            $mediaUrls[] = $webpath;
                        }
                    }
                }
            }
        }

        if (empty($content) && empty($mediaUrls)) {
            $error = 'Post must have text or media';
        } else {
            // Check if user has enough balance FIRST
            $currentBalance = (float)($user['coin_balance'] ?? 0);
            
            if ($currentBalance < POST_CREATION_COST) {
                $error = "Need " . POST_CREATION_COST . " coins to create a post. You have {$currentBalance}. <a href='/user/wallet.php' style='color:#6366f1;'>Buy coins</a>";
            } else {
                // Use economy.php createPost function (now charges instead of rewards)
                $result = createPost($user['user_id'], $content, $mediaUrls, $visibility, $tokenId, $coinPrice);
                
                if ($result['success']) {
                    header('Location: /user/feed.php?success=posted&cost=' . POST_CREATION_COST);
                    exit;
                } else {
                    $error = $result['error'];
                }
            }
        }
    }
}

// Get user's tokens for token-gated posts
$userTokens = [];
try {
    $userTokens = getUserTokens($user['user_id']);
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
    }

    .create-wrap { max-width: 700px; margin: 0 auto; padding: 16px; }
    .create-header { margin-bottom: 20px; }
    .create-header h1 { font-size: 26px; color: var(--bright); font-weight: 800; }

    .create-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
    }

    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block;
        font-size: 14px;
        color: var(--text);
        margin-bottom: 8px;
        font-weight: 600;
    }
    .form-group textarea {
        width: 100%;
        min-height: 120px;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: #1a1a22;
        color: var(--bright);
        font-size: 16px;
        font-family: inherit;
        resize: vertical;
    }
    .form-group textarea:focus {
        outline: none;
        border-color: var(--accent);
    }
    .form-group input, .form-group select {
        width: 100%;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: #1a1a22;
        color: var(--bright);
        font-size: 14px;
    }

    .media-upload {
        border: 2px dashed var(--border);
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .media-upload:hover { border-color: var(--accent); }
    .media-upload .emoji { font-size: 32px; display: block; margin-bottom: 8px; }
    .media-upload p { color: var(--text); font-size: 14px; }
    .media-upload.has-files {
        border-color: var(--green);
        background: rgba(16,185,129,0.05);
    }

    .visibility-options {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .vis-option {
        padding: 14px;
        border-radius: 12px;
        border: 2px solid var(--border);
        background: #1a1a22;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }
    .vis-option:hover, .vis-option.selected {
        border-color: var(--accent);
        background: rgba(99,102,241,0.1);
    }
    .vis-option .emoji { font-size: 24px; display: block; margin-bottom: 6px; }
    .vis-option .name { font-size: 13px; color: var(--bright); font-weight: 600; }
    .vis-option .desc { font-size: 11px; color: var(--text); }

    .submit-btn {
        width: 100%;
        padding: 14px;
        border-radius: 12px;
        border: none;
        background: var(--accent);
        color: white;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        margin-top: 10px;
        transition: opacity 0.2s;
    }
    .submit-btn:hover { opacity: 0.9; }
    .submit-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }
    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid var(--red); color: var(--red); }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid var(--green); color: var(--green); }

    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 8px;
        margin-top: 12px;
    }
    .preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        background: #1a1a22;
    }
    .preview-item img, .preview-item video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .preview-item .remove {
        position: absolute;
        top: 4px;
        right: 4px;
        background: var(--red);
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        font-size: 12px;
    }

    @media (max-width: 640px) {
        .create-wrap { padding: 10px; }
        .create-header h1 { font-size: 20px; }
        .create-card { padding: 16px; }
        .visibility-options { grid-template-columns: 1fr; }
    }
</style>

<div class="create-wrap">
    <div class="create-header">
        <h1><span class="emoji">✍️</span> Create Post</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="create-card">
        <form method="POST" enctype="multipart/form-data" id="postForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="form-group">
                <label>What's on your mind?</label>
                <textarea name="content" id="postContent" placeholder="Share your thoughts, ideas, or updates..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Add Photos/Videos</label>
                <div class="media-upload" id="mediaUpload" onclick="document.getElementById('mediaInput').click()">
                    <span class="emoji">📷</span>
                    <p id="uploadText">Tap to upload images or videos (max 5)</p>
                    <input type="file" id="mediaInput" name="media[]" multiple accept="image/*,video/*" style="display:none;" onchange="handleFiles(this)">
                </div>
                <div class="preview-grid" id="previewGrid"></div>
            </div>

            <div class="form-group">
                <label>Visibility</label>
                <div class="visibility-options">
                    <div class="vis-option selected" onclick="selectVis(this, 'public')">
                        <span class="emoji">🌐</span>
                        <div class="name">Public</div>
                        <div class="desc">Everyone can see</div>
                    </div>
                    <div class="vis-option" onclick="selectVis(this, 'followers')">
                        <span class="emoji">👥</span>
                        <div class="name">Followers</div>
                        <div class="desc">Only followers</div>
                    </div>
                    <div class="vis-option" onclick="selectVis(this, 'subscribers')">
                        <span class="emoji">💎</span>
                        <div class="name">Subscribers</div>
                        <div class="desc">Paid subscribers only</div>
                    </div>
                    <div class="vis-option" onclick="selectVis(this, 'token_gated')">
                        <span class="emoji">🏆</span>
                        <div class="name">Token Gate</div>
                        <div class="desc">Token holders only</div>
                    </div>
                </div>
                <input type="hidden" name="visibility" id="visInput" value="public">
            </div>

            <div class="form-group" id="tokenSelect" style="display:none;">
                <label>Required Token</label>
                <select name="token_id">
                    <?php foreach ($userTokens as $t): ?>
                        <option value="<?= $t['token_id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['symbol'] ?>)</option>
                    <?php endforeach; ?>
                    <?php if (empty($userTokens)): ?>
                        <option value="">No tokens available - buy from wallet</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group" id="coinPrice" style="display:none;">
                <label>Coin Price to View (0 = free)</label>
                <input type="number" name="coin_price" value="0" min="0" step="0.01" placeholder="e.g. 10">
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">🚀 Publish Post</button>
        </form>
    </div>
</div>

<script>
let selectedFiles = [];

function selectVis(el, val) {
    document.querySelectorAll('.vis-option').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('visInput').value = val;

    document.getElementById('tokenSelect').style.display = val === 'token_gated' ? 'block' : 'none';
    document.getElementById('coinPrice').style.display = (val === 'subscribers' || val === 'token_gated') ? 'block' : 'none';
}

function handleFiles(input) {
    const files = Array.from(input.files);
    const grid = document.getElementById('previewGrid');
    const uploadDiv = document.getElementById('mediaUpload');
    const uploadText = document.getElementById('uploadText');
    
    if (files.length > 0) {
        uploadDiv.classList.add('has-files');
        uploadText.textContent = files.length + ' file(s) selected. Tap to change.';
    }
    
    grid.innerHTML = '';
    files.forEach((file, i) => {
        const div = document.createElement('div');
        div.className = 'preview-item';
        
        if (file.type.startsWith('video/')) {
            div.innerHTML = `<video src="${URL.createObjectURL(file)}" controls></video>`;
        } else {
            div.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="Preview">`;
        }
        
        grid.appendChild(div);
    });
}


// Prevent double submit
document.getElementById('postForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '🚀 Publishing...';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>