<?php
// api/comment-post.php – Handle comments with coin economy

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
$content = trim($_POST['content'] ?? '');

if (empty($postId) || empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Post ID and content required']);
    exit;
}

$result = commentOnPost($user['user_id'], $postId, $content);
echo json_encode($result);