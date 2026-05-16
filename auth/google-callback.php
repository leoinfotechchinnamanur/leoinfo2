<?php
// auth/google-callback.php - Google OAuth Callback
// FIXED: Uses correct DB columns (user_id not id, is_banned not status)
// FIXED: Generates UUID for user_id, no duplicate coin awards

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Verify state/redirect
$redirect = $_GET['state'] ?? '/';
if (strpos($redirect, 'akkuapps.in') === false && strpos($redirect, '/') !== 0) {
    $redirect = '/';
}

// Handle Google OAuth code
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for tokens
    $tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'code' => $code,
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'grant_type' => 'authorization_code'
            ])
        ]
    ]));

    $tokens = json_decode($tokenResponse, true);
    if (!isset($tokens['access_token'])) {
        header("Location: /auth/login.php?error=Google+token+failed&redirect=" . urlencode($redirect));
        exit;
    }

    // Get user info from Google
    $userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokens['access_token']), true);

    if (!isset($userInfo['email'])) {
        header("Location: /auth/login.php?error=Invalid+Google+response&redirect=" . urlencode($redirect));
        exit;
    }

    global $pdo;

    // Find existing user by email
    $email = $userInfo['email'];
    $emailHash = hashEmail($email);
    $stmt = $pdo->prepare("SELECT user_id, is_banned FROM users WHERE email = ? OR email_hash = ? LIMIT 1");
    $stmt->execute([$email, $emailHash]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // FIX: Check is_banned instead of non-existent 'status' column
        if (!empty($existingUser['is_banned']) && $existingUser['is_banned'] == 1) {
            header("Location: /auth/login.php?error=Account+suspended&redirect=" . urlencode($redirect));
            exit;
        }
        $userId = $existingUser['user_id'];

        // Update last_login
        $pdo->prepare("UPDATE users SET last_login = NOW(), google_id = ? WHERE user_id = ?")
            ->execute([$userInfo['id'] ?? null, $userId]);
    } else {
        // Create new user
        // FIX: Generate UUID for user_id (varchar(36) primary key)
        $userId = generateUUID();

        // FIX: Removed 'status' column (doesn't exist), removed duplicate coin_balance
        // Only set coin_balance here, do NOT call awardCoins separately
        $newUserCoins = 100.00; // Welcome bonus set directly

        $stmt = $pdo->prepare("INSERT INTO users 
            (user_id, google_id, email, email_hash, name, avatar, role, is_banned, coin_balance, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'user', 0, ?, NOW())");
        $stmt->execute([
            $userId,
            $userInfo['id'] ?? null,
            $email,
            $emailHash,
            $userInfo['name'] ?? 'User',
            $userInfo['picture'] ?? null,
            $newUserCoins
        ]);

        // FIX: Do NOT call awardCoins here — coins already set in INSERT
        // Record the welcome bonus transaction for audit trail
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
            VALUES (?, ?, 'new_user', ?, ?, 'Welcome bonus for new Google OAuth user', NOW())
        ")->execute([generateUUID(), $userId, $newUserCoins, $newUserCoins]);
    }

    // Create secure session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_at'] = time();

    // Redirect to intended page
    header("Location: $redirect");
    exit;

} else {
    // No code - redirect to login
    header("Location: /auth/login.php?error=No+authorization+code&redirect=" . urlencode($redirect));
    exit;
}