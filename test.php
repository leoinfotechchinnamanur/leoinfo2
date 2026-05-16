<?php
// test-db-simple.php - Delete after testing
define('AKKUAPPS_LOADED', true);

// Show errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Testing Database Connection...\n\n";

// Try direct connection
$host = 'localhost';
$db   = 'akkuapps_maui';
$user = 'akkuapps_maui';  // ← Change if different
$pass = 'akkuapps_maui';  // ← Change if different
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "✅ SUCCESS! Connected to $db\n";
    
    // Check users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        echo "✅ Table 'users' exists\n";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "✅ Users in table: $count\n";
    } else {
        echo "⚠️ Table 'users' NOT found\n";
    }
} catch (PDOException $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    echo "\n💡 Check:\n";
    echo "  • Database name: $db\n";
    echo "  • Username: $user\n";
    echo "  • Password: " . ($pass ? '****' : 'EMPTY') . "\n";
    echo "  • User has privileges on database\n";
}
echo "</pre>";
?>