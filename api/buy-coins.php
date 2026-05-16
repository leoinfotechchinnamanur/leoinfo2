<?php
// api/buy-coins.php – Initiate UPI payment request

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
$packageId = intval($_POST['package_id'] ?? 0);

if ($packageId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Package required']);
    exit;
}

$result = createPaymentRequest($user['user_id'], $packageId);
echo json_encode($result);