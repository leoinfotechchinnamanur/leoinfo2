<?php
// api/subscribe.php – Subscribe to creator

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
$creatorId = $_POST['creator_id'] ?? '';

if (empty($creatorId)) {
    echo json_encode(['success' => false, 'error' => 'Creator ID required']);
    exit;
}

// FIX Medium 6: Look up subscription price from DB instead of trusting client input
$priceStmt = $pdo->prepare("SELECT subscription_price FROM users WHERE user_id = ? AND role IN ('user', 'admin')");
$priceStmt->execute([$creatorId]);
$price = (float)$priceStmt->fetchColumn();

if ($price <= 0) {
    // Fallback to default if not set
    $price = 50.00;
}

// Validate the client-provided price matches server-side price (optional extra check)
$clientPrice = floatval($_POST['price'] ?? 0);
if ($clientPrice > 0 && abs($clientPrice - $price) > 0.01) {
    echo json_encode(['success' => false, 'error' => 'Price mismatch. Subscription price is ' . $price]);
    exit;
}

$result = subscribeToCreator($user['user_id'], $creatorId, $price);
echo json_encode($result);