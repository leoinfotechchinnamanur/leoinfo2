<?php
// ComputerSales/test.php - Server diagnostic
// Visit: https://akkuapps.in/ComputerSales/test.php

define('AKKUAPPS_LOADED', true);

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "=== AKKUAPPS COMPUTER SALES DIAGNOSTIC ===

";

// 1. PHP Version
echo "PHP Version: " . PHP_VERSION . "
";
echo "PHP SAPI: " . PHP_SAPI . "

";

// 2. Check includes
echo "=== INCLUDE PATHS ===
";
$includes = [
    __DIR__ . '/../includes/functions.php',
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../includes/security.php'
];
foreach ($includes as $file) {
    echo basename($file) . ": " . (file_exists($file) ? "EXISTS" : "MISSING") . "
";
}
echo "
";

// 3. Try loading
echo "=== LOADING TESTS ===
";
try {
    require_once __DIR__ . '/../includes/functions.php';
    echo "functions.php: LOADED OK
";
    echo "  isLoggedIn exists: " . (function_exists('isLoggedIn') ? 'YES' : 'NO') . "
";
    echo "  requireLogin exists: " . (function_exists('requireLogin') ? 'YES' : 'NO') . "
";
    echo "  getCurrentUser exists: " . (function_exists('getCurrentUser') ? 'YES' : 'NO') . "
";
} catch (Exception $e) {
    echo "functions.php: ERROR - " . $e->getMessage() . "
";
}

try {
    require_once __DIR__ . '/../includes/config.php';
    echo "config.php: LOADED OK
";
    echo "  DB_HOST defined: " . (defined('DB_HOST') ? 'YES (' . DB_HOST . ')' : 'NO') . "
";
    echo "  DB_NAME defined: " . (defined('DB_NAME') ? 'YES (' . DB_NAME . ')' : 'NO') . "
";
} catch (Exception $e) {
    echo "config.php: ERROR - " . $e->getMessage() . "
";
}

echo "
";

// 4. Check ComputerSales files
echo "=== COMPUTER SALES FILES ===
";
$csFiles = [
    'Core/Database.php',
    'Core/Security.php', 
    'Core/AuthGuard.php',
    'Models/Product.php',
    'Models/Invoice.php'
];
foreach ($csFiles as $file) {
    $path = __DIR__ . '/' . $file;
    echo $file . ": " . (file_exists($path) ? "EXISTS" : "MISSING");
    if (file_exists($path)) {
        echo " (" . filesize($path) . " bytes)";
    }
    echo "
";
}

echo "
";

// 5. Database test
echo "=== DATABASE TEST ===
";
if (defined('DB_HOST') && isset($pdo)) {
    try {
        $test = $pdo->query("SELECT 1");
        echo "DB Connection: OK
";

        // Check CS tables
        $tables = $pdo->query("SHOW TABLES LIKE 'cs_%'")->fetchAll(PDO::FETCH_COLUMN);
        echo "CS Tables found: " . count($tables) . "
";
        foreach ($tables as $t) {
            echo "  - $t
";
        }
    } catch (Exception $e) {
        echo "DB Connection: FAILED - " . $e->getMessage() . "
";
    }
} else {
    echo "DB Connection: NOT TESTED (config not loaded)
";
}

echo "
=== END DIAGNOSTIC ===
";