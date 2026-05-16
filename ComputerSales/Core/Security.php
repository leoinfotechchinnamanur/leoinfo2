<?php
// ComputerSales/Core/Security.php
// Enhanced security layer for Computer Sales module

namespace ComputerSales\Core;

class Security {

    private static ?string $csrfToken = null;

    // XSS: Escape HTML entities for output
    public static function e(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // XSS: Clean HTML (allow only safe tags for descriptions)
    public static function cleanHtml(?string $html): ?string {
        if (!$html) return null;
        $allowed = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><span><div>';
        return strip_tags($html, $allowed);
    }

    // XSS: Sanitize for URL slugs
    public static function slugify(string $text): string {
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower(self::transliterate($text)));
        return trim($text, '-');
    }

    private static function transliterate(string $text): string {
        $map = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
            'ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i',
            'î'=>'i','ï'=>'i','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o',
            'ö'=>'o','ø'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y',
            'ÿ'=>'y','š'=>'s','ž'=>'z','ā'=>'a','ē'=>'e','ī'=>'i','ō'=>'o','ū'=>'u'
        ];
        return strtr($text, $map);
    }

    // CSRF Token generation
    public static function generateToken(): string {
        if (empty($_SESSION['cs_csrf_token'])) {
            $_SESSION['cs_csrf_token'] = bin2hex(random_bytes(32));
        }
        self::$csrfToken = $_SESSION['cs_csrf_token'];
        return self::$csrfToken;
    }

    // CSRF Token verification
    public static function verifyToken(?string $token): bool {
        if (empty($token) || empty($_SESSION['cs_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['cs_csrf_token'], $token);
    }

    // Get token for forms
    public static function getTokenField(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }

    // Input validation and sanitization
    public static function sanitize(string $type, $value) {
        return match($type) {
            'string' => trim(strip_tags((string)$value)),
            'email' => filter_var(trim($value), FILTER_SANITIZE_EMAIL),
            'int' => filter_var($value, FILTER_VALIDATE_INT) ?: 0,
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT) ?: 0.0,
            'bool' => (bool)$value,
            'url' => filter_var(trim($value), FILTER_VALIDATE_URL),
            'phone' => preg_replace('/[^0-9+\-]/', '', (string)$value),
            'decimal' => preg_replace('/[^0-9.]/', '', (string)$value),
            'slug' => self::slugify((string)$value),
            default => self::e((string)$value)
        };
    }

    // Validate GST number (India)
    public static function validateGST(string $gst): bool {
        return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gst) === 1;
    }

    // Validate Indian phone
    public static function validatePhone(string $phone): bool {
        return preg_match('/^[6-9][0-9]{9}$/', preg_replace('/[^0-9]/', '', $phone)) === 1;
    }

    // Generate secure random string
    public static function randomString(int $length = 16): string {
        return bin2hex(random_bytes($length / 2));
    }

    // Rate limiting
    public static function checkRateLimit(string $key, int $maxAttempts = 60, int $window = 60): bool {
        $now = time();
        $cacheKey = 'rate_' . $key;

        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = ['count' => 1, 'reset' => $now + $window];
            return true;
        }

        if ($now > $_SESSION[$cacheKey]['reset']) {
            $_SESSION[$cacheKey] = ['count' => 1, 'reset' => $now + $window];
            return true;
        }

        if ($_SESSION[$cacheKey]['count'] >= $maxAttempts) {
            return false;
        }

        $_SESSION[$cacheKey]['count']++;
        return true;
    }

    // JSON response helper for API
    public static function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Secure file upload validation
    public static function validateUpload(array $file, array $allowedTypes, int $maxSize = 5242880): array {
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed with error code: ' . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'File too large. Max ' . ($maxSize / 1048576) . 'MB allowed.';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
        }

        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dangerous = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat'];
        if (in_array($ext, $dangerous)) {
            $errors[] = 'Dangerous file extension not allowed';
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'mime' => $mime];
    }
}