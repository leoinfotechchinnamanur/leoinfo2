<?php
// setup-admins.php - Run ONCE, then DELETE
define('AKKUAPPS_LOADED', true);
require_once 'includes/config.php';

// 🔴 Change these to your desired passwords
$admin1_pass = 'YourSecurePassword1!';
$admin2_pass = 'YourSecurePassword2!';

$admins = [
    'venkateshkumar.cmr@gmail.com' => $admin1_pass,
    'leoinfotech.chinnamanur@gmail.com' => $admin2_pass
];

foreach ($admins as $email => $plainPass) {
    $hash = hashPassword($plainPass);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ? AND role = 'admin'");
    $stmt->execute([$hash, $email]);
    echo "✅ Updated: $email<br>";
}
echo "<br>🔐 Done. <strong>DELETE this file now.</strong>";
?>