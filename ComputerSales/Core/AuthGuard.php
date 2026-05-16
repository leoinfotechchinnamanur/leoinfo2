<?php
// ComputerSales/Core/AuthGuard.php
// Authentication middleware - blocks anonymous access

namespace ComputerSales\Core;

class AuthGuard {

    /**
     * Require login - redirects to login page if not authenticated
     */
    public static function requireLogin(): void {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            $_SESSION['cs_redirect_after_login'] = $_SERVER['REQUEST_URI'];

            if (self::isApiRequest()) {
                Security::jsonResponse([
                    'error' => 'Authentication required',
                    'message' => 'Please login to access Computer Sales',
                    'login_url' => '/auth/login.php',
                    'redirect' => $_SERVER['REQUEST_URI']
                ], 401);
            }

            header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '&msg=login_required');
            exit;
        }
    }

    /**
     * Require specific role(s)
     */
    public static function requireRole(array $roles): void {
        self::requireLogin();

        $user = getCurrentUser();
        if (!$user || !in_array($user['role'], $roles)) {
            if (self::isApiRequest()) {
                Security::jsonResponse([
                    'error' => 'Insufficient permissions',
                    'required' => $roles,
                    'current' => $user['role'] ?? 'none'
                ], 403);
            }

            header('Location: /ComputerSales/?error=unauthorized');
            exit;
        }
    }

    /**
     * Check if user is admin or moderator
     */
    public static function requireAdmin(): void {
        self::requireRole(['admin', 'moderator']);
    }

    /**
     * Get current user data
     */
    public static function getUser(): ?array {
        return getCurrentUser();
    }

    /**
     * Check if authenticated (no redirect)
     */
    public static function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get user ID safely
     */
    public static function getUserId(): ?string {
        return $_SESSION['user_id'] ?? null;
    }

    private static function isApiRequest(): bool {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || 
               str_contains($contentType, 'application/json') ||
               str_contains($_SERVER['REQUEST_URI'] ?? '', '/API/');
    }

    /**
     * Redirect after login completion
     */
    public static function redirectAfterLogin(): void {
        $redirect = $_SESSION['cs_redirect_after_login'] ?? '/ComputerSales/';
        unset($_SESSION['cs_redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    }
}