<?php
// api/buy-badge.php – Buy a badge

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
$badgeId = $_POST['badge_id'] ?? '';

if (empty($badgeId)) {
    echo json_encode(['success' => false, 'error' => 'Badge ID required']);
    exit;
}

$result = buyBadge($user['user_id'], $badgeId);
echo json_encode($result);