<?php
// includes/security.php
// FIX: Removed hashEmail() and generateUUID() — they are defined in config.php.
//      Including both files caused "Cannot redeclare function" fatal errors.
// FIX: getEncryptionKey() now uses the constant from config.php as fallback
//      instead of requiring a DB config table.
if (!defined('AKKUAPPS_LOADED')) { exit('Direct access not allowed'); }

define('ENCRYPTION_CIPHER', 'aes-256-cbc');
define('ENCRYPTION_IV_LEN',  openssl_cipher_iv_length('aes-256-cbc'));

function getEncryptionKey(): string {
    global $pdo;

    // Try to load from DB config table first
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare(
                "SELECT config_value FROM config WHERE config_key = 'encryption_salt' LIMIT 1"
            );
            $stmt->execute();
            $salt = $stmt->fetchColumn();
            if ($salt) {
                return hash('sha256', $salt, true); // 32 bytes for AES-256
            }
        }
    } catch (Exception $e) {
        // Table may not exist yet – fall through to constant
    }

    // Fallback: use the constant defined in config.php
    return hash('sha256', defined('ENCRYPTION_SALT') ? ENCRYPTION_SALT : 'akkuapps.in', true);
}

function encryptData(?string $plaintext): ?string {
    if (empty($plaintext)) return null;
    $key = getEncryptionKey();
    $iv  = openssl_random_pseudo_bytes(ENCRYPTION_IV_LEN);
    $ciphertext = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function decryptData(?string $ciphertextB64): ?string {
    if (empty($ciphertextB64)) return null;
    $key  = getEncryptionKey();
    $data = base64_decode($ciphertextB64);
    $iv         = substr($data, 0, ENCRYPTION_IV_LEN);
    $ciphertext = substr($data, ENCRYPTION_IV_LEN);
    $result = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    return $result === false ? null : $result;
}