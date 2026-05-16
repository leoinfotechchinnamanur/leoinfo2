<?php
// api/gift_convert.php — Convert owned gift to coins
// POST: gift_id
// Auth required, 10% fee goes to AkkuCollectionBox

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$giftId = intval($_POST['gift_id'] ?? 0);
if ($giftId <= 0) {
    echo json_encode(['success' => false, 'error' => 'gift_id required']);
    exit;
}

// Get gift details
$gift = $pdo->prepare("SELECT * FROM gifts WHERE gift_id = ?");
$gift->execute([$giftId]);
$giftData = $gift->fetch();

if (!$giftData) {
    echo json_encode(['success' => false, 'error' => 'Gift not found']);
    exit;
}

// Process conversion
$treasury = new AkkuCollectionBox($pdo, (int)$user['user_id']);
$result = $treasury->processGiftConversion(
    (int)$user['user_id'],
    $giftId,
    floatval($giftData['coin_price']),
    $giftData['name']
);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => "Converted to {$result['net_to_user']} coins (fee: {$result['commission']})",
        'gross_value' => $result['gross_value'],
        'commission' => $result['commission'],
        'net_to_user' => $result['net_to_user'],
        'collection_box_txn_id' => $result['collection_box_txn_id']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}