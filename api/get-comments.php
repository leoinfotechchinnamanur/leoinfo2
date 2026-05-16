<?php
// api/get-comments.php – Load comments for a post

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$postId = $_GET['post_id'] ?? '';
if (empty($postId)) {
    echo json_encode(['comments' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT c.*, u.name 
         FROM post_comments c 
         JOIN users u ON c.user_id = u.user_id 
         WHERE c.post_id = ? 
         ORDER BY c.created_at DESC LIMIT 50"
    );
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();

    echo json_encode([
        'comments' => array_map(function($c) {
            return [
                'name' => $c['name'],
                'content' => $c['content'],
                'time' => date('M d, H:i', strtotime($c['created_at']))
            ];
        }, $comments)
    ]);
} catch (Exception $e) {
    echo json_encode(['comments' => []]);
}