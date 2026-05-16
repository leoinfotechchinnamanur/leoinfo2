<?php
// api/get-notifications.php – Get user notifications
// GET: limit (optional, default 20)

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$user['user_id'], $limit]);
    $notifications = $stmt->fetchAll();

    // Mark as read
    markNotificationsRead($user['user_id']);

    echo json_encode([
        'success' => true,
        'notifications' => array_map(function($n) {
            return [
                'id' => $n['notification_id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'reference_id' => $n['reference_id'],
                'reference_type' => $n['reference_type'],
                'is_read' => (bool)$n['is_read'],
                'created_at' => $n['created_at']
            ];
        }, $notifications),
        'unread_count' => 0
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}