<?php
// auth/callback.php - Google OAuth Callback
define('AKKUAPPS_LOADED', true);
require_once '../includes/functions.php';

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
    
    // Find or create user
    $email = $userInfo['email'];
    $stmt = $pdo->prepare("SELECT id, status FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['status'] !== 'active') {
            header("Location: /auth/login.php?error=Account+suspended&redirect=" . urlencode($redirect));
            exit;
        }
        $userId = $user['id'];
    } else {
        // Create new user
        $userId = null; // Let DB auto-increment
        $stmt = $pdo->prepare("INSERT INTO users (google_id, email, name, avatar, role, status, coin_balance) 
                              VALUES (?, ?, ?, ?, 'user', 'active', ?)");
        $stmt->execute([
            $userInfo['id'] ?? null,
            $email,
            $userInfo['name'] ?? 'User',
            $userInfo['picture'] ?? null,
            100 // New user bonus
        ]);
        $userId = $pdo->lastInsertId();
        
        // Award welcome coins
        awardCoins($userId, 'new_user', null, 'Welcome bonus');
    }
    
    // Create secure session (persistent until manual logout)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_at'] = time();
    
    // Optional: Store session in DB for device tracking
    // (Add to user_sessions table if needed)
    
    // Redirect to intended page
    header("Location: $redirect");
    exit;
    
} else {
    // No code - redirect to login
    header("Location: /auth/login.php?error=No+authorization+code&redirect=" . urlencode($redirect));
    exit;
}
?>