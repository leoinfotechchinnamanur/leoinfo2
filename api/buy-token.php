<?php
// api/buy-token.php – Buy Akku tokens

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
$tokenId = $_POST['token_id'] ?? '';
$quantity = max(1, intval($_POST['quantity'] ?? 1));

if (empty($tokenId)) {
    echo json_encode(['success' => false, 'error' => 'Token ID required']);
    exit;
}

$result = buyToken($user['user_id'], $tokenId, $quantity);
echo json_encode($result);