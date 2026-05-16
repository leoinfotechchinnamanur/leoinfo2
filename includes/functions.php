<?php
// includes/functions.php – Core helper library
if (!defined('AKKUAPPS_LOADED')) { exit('Direct access not allowed'); }

global $pdo;
if (!$pdo) { require_once __DIR__ . '/config.php'; }

// ---------------------------------------------------
// Secure session configuration
// MUST be before session_start()
// -------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Set ALL ini settings BEFORE starting session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Set secure flag based on HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    } else {
        ini_set('session.cookie_secure', 0);
    }
    
    session_start();
}

// ---------------------------------------------------
// ---------- Authentication helpers ----------
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && (!empty($user['is_banned']) && $user['is_banned'])) {
        return null;
    }
    return $user ?: null;
}

function requireLogin(string $redirect = null): void {
    if (!isLoggedIn()) {
        $redirect = $redirect ?? $_SERVER['REQUEST_URI'];
        if (strpos($redirect, 'akkuapps.in') === false && strpos($redirect, '/') !== 0) {
            $redirect = '/';
        }
        header('Location: /auth/login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

// ---------------------------------------------------
// ---------- CSRF ----------
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
    }
    // FIX Medium 2: Rotate token every 15 minutes (900 seconds)
    if (time() - $_SESSION['csrf_time'] > 900) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
    }
    return $_SESSION['csrf_token'];
}
function verifyCSRFToken(string $token): bool {
    $valid = hash_equals($_SESSION['csrf_token'] ?? '', $token);
    // After successful verification, optionally rotate for next request
    // (uncomment below for per-use rotation - may break AJAX multi-requests)
    // if ($valid) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); $_SESSION['csrf_time'] = time(); }
    return $valid;
}

// ---------------------------------------------------
// ---------- Coin / reward system ----------
function awardCoins(string $userId, string $type, $referenceId = null, string $description = null): bool {
    global $pdo;

    $rewards = [
        'new_user'     => 100,
        'post_create'  =>   2,
        'like_received'=> 0.5,
        'game_win'     =>  10,
    ];
    $amount = $rewards[$type] ?? 0;
    if ($amount <= 0) return false;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $current = (float)$stmt->fetchColumn();

        $newBalance = $current + $amount;

        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newBalance, $userId]);

        $pdo->prepare(
            "INSERT INTO coin_transactions
             (txn_id, user_id, reference_type, reference_id, amount, balance_after, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            generateUUID(),
            $userId,
            $type,
            $referenceId,
            $amount,
            $newBalance,
            $description
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Coin award error: ' . $e->getMessage());
        return false;
    }
}