<?php
// api/create-post.php – Create a new post
// POST: content, media (optional)
// Auth required, costs 2 coins

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

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

$content = trim($_POST['content'] ?? '');
if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Content required']);
    exit;
}

// Cost to create post: 2 coins
$postCost = 2.0;

// Check balance
$balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
$balStmt->execute([$user['user_id']]);
$balance = (float)$balStmt->fetchColumn();

if ($balance < $postCost) {
    echo json_encode(['success' => false, 'error' => "Need {$postCost} coins to post. You have {$balance}"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Deduct coins
    $newBalance = $balance - $postCost;
    $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
        ->execute([$newBalance, $user['user_id']]);

    // Record transaction
    $pdo->prepare("
        INSERT INTO coin_transactions 
        (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
        VALUES (?, ?, 'post_create', ?, ?, ?, NOW())
    ")->execute([
        generateUUID(), $user['user_id'], -$postCost, $newBalance,
        "Created a new post"
    ]);

    // Create post
    $postId = generateUUID();
    $mediaUrls = null;

    // Handle media uploads if present (supports multiple files like create.php)
    $mediaUrls = null;
    if (!empty($_FILES['media']['tmp_name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/posts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $uploadedUrls = [];
        foreach ($_FILES['media']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['media']['error'][$i] === 0 && is_uploaded_file($tmpName)) {
                $ext = strtolower(pathinfo($_FILES['media']['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'];
                if (in_array($ext, $allowed)) {
                    $fileName = uniqid() . '_' . basename($_FILES['media']['name'][$i]);
                    $uploadPath = $uploadDir . $fileName;
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $uploadedUrls[] = '/uploads/posts/' . $fileName;
                    }
                }
            }
        }
        if (!empty($uploadedUrls)) {
            $mediaUrls = json_encode($uploadedUrls);
        }
    }

    $visibility = in_array($_POST['visibility'] ?? '', ['public', 'followers', 'private']) 
        ? $_POST['visibility'] 
        : 'public';

    $pdo->prepare("
        INSERT INTO user_posts (post_id, user_id, content, media_urls, visibility, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ")->execute([$postId, $user['user_id'], $content, $mediaUrls, $visibility]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Post created!',
        'post_id' => $postId,
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('createPost error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to create post']);
}