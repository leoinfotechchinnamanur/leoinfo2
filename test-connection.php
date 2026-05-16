<?php
define('AKKUAPPS_LOADED', true);
require_once 'includes/config.php';

$pdo = getDBConnection();
if ($pdo) {
    echo "✅ Database connected successfully!";
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "<br>✅ Users table found!";
    } else {
        echo "<br>⚠️ Users table NOT found - run your SQL schema";
    }
} else {
    echo "❌ Database connection failed!<br>";
    echo "Check: DB credentials in includes/config.php";
}
?>