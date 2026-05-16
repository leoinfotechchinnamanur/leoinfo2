<?php
// api/like-post.php – Handle like/unlike with coin economy

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$user = getCurrentUser();
$postId = $_POST['post_id'] ?? '';
$action = $_POST['action'] ?? 'like';

if (empty($postId)) {
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

if ($action === 'unlike') {
    $result = unlikePost($user['user_id'], $postId);
} else {
    $result = likePost($user['user_id'], $postId);
}

echo json_encode($result);