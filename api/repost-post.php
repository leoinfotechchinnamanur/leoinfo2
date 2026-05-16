<?php
// api/repost-post.php – Handle reposts

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
$comment = trim($_POST['comment'] ?? '');

if (empty($postId)) {
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

$result = repostPost($user['user_id'], $postId, $comment);
echo json_encode($result);