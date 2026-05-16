<?php
// api/view-post.php – Record post view with monetization
// GET: post_id
// Returns: view count, reward info

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

$postId = $_GET['post_id'] ?? '';
if (empty($postId)) {
    echo json_encode(['success' => false, 'error' => 'post_id required']);
    exit;
}

$user = getCurrentUser();
$viewerId = $user ? $user['user_id'] : null;
$viewerIp = $_SERVER['REMOTE_ADDR'] ?? null;

$result = recordPostView($postId, $viewerId, $viewerIp);
echo json_encode($result);