<?php
// api/send-donation.php — Send a donation card with coins to another user
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$cardId = $_POST['card_id'] ?? '';
$receiverId = $_POST['receiver_id'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (empty($cardId) || empty($receiverId) || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Card, receiver, and amount required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get card details
    $cardStmt = $pdo->prepare("SELECT * FROM donation_cards WHERE card_id = ? AND is_active = 1");
    $cardStmt->execute([$cardId]);
    $card = $cardStmt->fetch();

    if (!$card) {
        throw new Exception('Donation card not found');
    }

    if ($amount < $card['min_amount']) {
        throw new Exception("Minimum amount is {$card['min_amount']} coins");
    }
    if ($card['max_amount'] > 0 && $amount > $card['max_amount']) {
        throw new Exception("Maximum amount is {$card['max_amount']} coins");
    }

    // Verify receiver exists
    $recvStmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ?");
    $recvStmt->execute([$receiverId]);
    $receiver = $recvStmt->fetch();
    if (!$receiver) {
        throw new Exception('Receiver not found');
    }

    // Check sender balance
    $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
    $balStmt->execute([$user['user_id']]);
    $balance = (float)$balStmt->fetchColumn();

    if ($balance < $amount) {
        throw new Exception("Need {$amount} coins. You have {$balance}");
    }

    // Deduct from sender
    $newBalance = $balance - $amount;
    $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
        ->execute([$newBalance, $user['user_id']]);

    // Record sender transaction
    $pdo->prepare("
        INSERT INTO coin_transactions 
        (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
        VALUES (?, ?, 'donation_sent', ?, ?, ?, ?, NOW())
    ")->execute([
        generateUUID(), $user['user_id'], $cardId, -$amount, $newBalance,
        "Sent donation card '{$card['name']}' to {$receiver['name']}"
    ]);

    // Credit receiver
    $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
        ->execute([$amount, $receiverId]);

    $recvBalStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
    $recvBalStmt->execute([$receiverId]);
    $recvNewBalance = (float)$recvBalStmt->fetchColumn();

    // Record receiver transaction
    $pdo->prepare("
        INSERT INTO coin_transactions 
        (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
        VALUES (?, ?, 'donation_received', ?, ?, ?, ?, NOW())
    ")->execute([
        generateUUID(), $receiverId, $cardId, $amount, $recvNewBalance,
        "Received donation card '{$card['name']}' from {$user['name']}"
    ]);

    // Store in gift_transactions for history
    $pdo->prepare("
        INSERT INTO gift_transactions 
        (txn_id, sender_id, receiver_id, gift_id, coin_amount, message, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        generateUUID(), $user['user_id'], $receiverId, $cardId, $amount, $message
    ]);

    // CollectionBox fee (5%)
    $fee = round($amount * 0.05, 4);
    if ($fee > 0) {
        $adminStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = (int)($adminStmt->fetchColumn() ?: 0);
        if ($adminId) {
            $treasury = new AkkuCollectionBox($pdo, $adminId);
            $treasury->collect(
                'donation_card',
                (int)$user['user_id'],
                $fee,
                'donation_card',
                (int)$cardId,
                (int)$receiverId,
                $amount,
                5,
                "Donation card fee: {$card['name']} ({$amount} coins)"
            );
        }
    }

    // Notify receiver
    createNotification($receiverId, 'donation', 'New Donation!', 
        "{$user['name']} sent you a {$card['name']} worth {$amount} coins", $cardId, 'donation_card');

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Sent {$card['name']} with {$amount} coins to {$receiver['name']}!",
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}