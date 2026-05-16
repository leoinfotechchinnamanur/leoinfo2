<?php
// api/gift_send.php — Send gift to another user
// POST: receiver_id, gift_id
// Auth required, deducts 10 coin fee + removes gift from inventory

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

// Auth check
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

$receiverId = intval($_POST['receiver_id'] ?? 0);
$giftId = intval($_POST['gift_id'] ?? 0);

if ($receiverId <= 0 || $giftId <= 0) {
    echo json_encode(['success' => false, 'error' => 'receiver_id and gift_id required']);
    exit;
}

if ($receiverId == $user['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot send gift to yourself']);
    exit;
}

// Get gift details
$gift = $pdo->prepare("SELECT * FROM gifts WHERE gift_id = ? AND is_active = 1");
$gift->execute([$giftId]);
$giftData = $gift->fetch();

if (!$giftData) {
    echo json_encode(['success' => false, 'error' => 'Gift not found or inactive']);
    exit;
}

// FIX Priority 7: Query actual admin user_id from DB for treasury
$adminStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
$adminId = (int)($adminStmt->fetchColumn() ?: 0);

// Process via AkkuCollectionBox with correct admin ID
$treasury = new AkkuCollectionBox($pdo, $adminId);
$result = $treasury->processGiftSend(
    (int)$user['user_id'],
    $receiverId,
    $giftId,
    floatval($giftData['coin_price']),
    $giftData['name']
);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => "Gift sent! Fee: {$result['sender_cost']} coins",
        'sender_new_balance' => $result['sender_new_balance'],
        'collection_box_txn_id' => $result['collection_box_txn_id']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}