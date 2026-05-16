<?php
// ComputerSales/index.php - CLEAN PRODUCTION VERSION
// NO debug box, NO ob_start(), NO error reporting changes
// Works with existing auth system exactly as-is

define('AKKUAPPS_LOADED', true);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

// Auth check - redirects to login if not authenticated
requireLogin('/ComputerSales');

$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php');
    exit;
}

// Load ComputerSales classes
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Security.php';
require_once __DIR__ . '/Models/Product.php';

use ComputerSales\Core\Database;
use ComputerSales\Core\Security;
use ComputerSales\Models\Product;

// Initialize
$db = new Database();
$productModel = new Product();

// Fetch data
$featured = ['data' => [], 'total' => 0];
$newArrivals = ['data' => [], 'total' => 0];

try {
    $featured = $productModel->getAll(['featured' => true], 1, 6);
} catch (Exception $e) {
    error_log('Featured products error: ' . $e->getMessage());
}

try {
    $newArrivals = $productModel->getAll([], 1, 8);
} catch (Exception $e) {
    error_log('New arrivals error: ' . $e->getMessage());
}

$pageTitle = 'Computer Sales & Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= Security::e($pageTitle) ?> - AkkuApps.in</title>
    <style>
        :root {
            --bg: #08080c;
            --card: #0f0f14;
            --border: #1a1a22;
            --text: #a1a1aa;
            --bright: #ffffff;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --green: #10b981;
            --green-hover: #34d399;
            --red: #ef4444;
            --yellow: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
        }
        a { color: inherit; text-decoration: none; transition: all 0.2s; }

        /* Header */
        .cs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cs-logo {
            font-size: 20px;
            font-weight: 800;
            color: var(--bright);
            letter-spacing: -0.5px;
        }
        .cs-logo span { color: var(--accent); }
        .cs-logo small {
            display: block;
            font-size: 11px;
            color: var(--text);
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .cs-nav {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .cs-nav a {
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            padding: 4px 0;
            border-bottom: 2px solid transparent;
        }
        .cs-nav a:hover { color: var(--bright); border-bottom-color: var(--accent); }
        .cs-user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cs-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }
        .cs-user-name { color: var(--bright); font-weight: 600; font-size: 13px; }
        .cs-coins {
            background: rgba(99, 102, 241, 0.15);
            color: var(--accent);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }
        .cs-logout {
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cs-logout:hover { border-color: var(--red); color: var(--red); }

        .cs-container { max-width: 1200px; margin: 0 auto; padding: 24px; }

        /* Hero */
        .cs-hero {
            padding: 48px 0 32px;
            text-align: center;
            background: linear-gradient(180deg, rgba(99,102,241,0.08) 0%, transparent 100%);
            border-radius: 20px;
            margin-bottom: 32px;
        }
        .cs-hero h1 {
            font-size: 36px;
            font-weight: 800;
            color: var(--bright);
            margin-bottom: 12px;
            letter-spacing: -1px;
        }
        .cs-hero p {
            font-size: 16px;
            color: var(--text);
            margin-bottom: 28px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .cs-hero-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Buttons */
        .cs-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .cs-btn:hover {
            border-color: var(--accent);
            color: var(--bright);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }
        .cs-btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .cs-btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
            color: white;
        }
        .cs-btn-success {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        .cs-btn-success:hover {
            background: var(--green-hover);
            border-color: var(--green-hover);
        }

        /* Section */
        .cs-section { margin: 40px 0; }
        .cs-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .cs-section h2 {
            font-size: 22px;
            color: var(--bright);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cs-link {
            color: var(--accent);
            font-size: 14px;
            font-weight: 500;
        }
        .cs-link:hover { text-decoration: underline; }

        /* Search */
        .cs-search-section { margin: 24px 0; }
        .cs-search-form {
            display: flex;
            gap: 12px;
            max-width: 600px;
        }
        .cs-search-input {
            flex: 1;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--bright);
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        .cs-search-input:focus { border-color: var(--accent); }
        .cs-search-input::placeholder { color: #555; }

        /* Product Grid */
        .cs-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 20px;
        }
        .cs-product-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cs-product-card:hover {
            transform: translateY(-6px);
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }
        .cs-product-image {
            aspect-ratio: 4/3;
            background: linear-gradient(135deg, #15151d, #0a0a0f);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .cs-product-image img {
            max-width: 85%;
            max-height: 85%;
            object-fit: contain;
            transition: transform 0.3s;
        }
        .cs-product-card:hover .cs-product-image img { transform: scale(1.05); }
        .cs-no-image {
            font-size: 56px;
            opacity: 0.3;
        }
        .cs-condition-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--accent);
            color: white;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cs-product-info { padding: 20px; }
        .cs-product-info h3 {
            color: var(--bright);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .cs-brand {
            font-size: 12px;
            color: var(--accent);
            font-weight: 500;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cs-price-row {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 14px;
        }
        .cs-mrp {
            text-decoration: line-through;
            font-size: 13px;
            color: #666;
        }
        .cs-price {
            font-size: 22px;
            font-weight: 800;
            color: var(--green);
        }
        .cs-product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .cs-stock { font-weight: 600; }
        .cs-stock.in { color: var(--green); }
        .cs-stock.out { color: var(--red); }
        .cs-warranty { color: var(--yellow); }
        .cs-product-actions {
            display: flex;
            gap: 10px;
        }
        .cs-product-actions .cs-btn {
            flex: 1;
            justify-content: center;
            padding: 10px;
            font-size: 13px;
        }

        /* Category Cards */
        .cs-categories {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }
        .cs-category-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px 16px;
            text-align: center;
            transition: all 0.25s;
        }
        .cs-category-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.15);
        }
        .cs-cat-icon { font-size: 36px; margin-bottom: 12px; display: block; }
        .cs-category-card span:last-child {
            color: var(--bright);
            font-weight: 600;
            font-size: 14px;
        }
        .cs-cat-action { border-color: var(--accent); }
        .cs-cat-action span:last-child { color: var(--accent); }

        /* Service Banner */
        .cs-service-banner {
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(16,185,129,0.05));
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            margin: 40px 0;
            text-align: center;
        }
        .cs-service-banner h2 {
            font-size: 24px;
            color: var(--bright);
            margin-bottom: 12px;
        }
        .cs-service-banner p {
            color: var(--text);
            margin-bottom: 24px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* FAB */
        .cs-quick-actions {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 999;
        }
        .cs-fab {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--card);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            text-decoration: none;
            position: relative;
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            transition: all 0.2s;
            cursor: pointer;
        }
        .cs-fab:hover {
            transform: scale(1.1);
            border-color: var(--accent);
        }
        .cs-fab-primary { background: var(--accent); border-color: var(--accent); }
        .cs-fab-admin { background: var(--yellow); border-color: var(--yellow); }
        .cs-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--red);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        /* Footer */
        .cs-footer {
            text-align: center;
            padding: 32px 24px;
            border-top: 1px solid var(--border);
            margin-top: 60px;
        }
        .cs-footer p { margin: 4px 0; }
        .cs-footer p:first-child { color: var(--bright); font-weight: 600; }
        .cs-footer p:last-child { font-size: 12px; opacity: 0.6; }

        /* Empty State */
        .cs-empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--text);
        }
        .cs-empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .cs-empty h3 { color: var(--bright); margin-bottom: 8px; }
        .cs-empty p { margin-bottom: 20px; }

        /* Mobile */
        @media (max-width: 768px) {
            .cs-header { padding: 10px 14px; }
            .cs-logo { font-size: 16px; }
            .cs-nav { gap: 12px; }
            .cs-nav a { font-size: 12px; }
            .cs-hero h1 { font-size: 24px; }
            .cs-product-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .cs-product-info { padding: 14px; }
            .cs-price { font-size: 18px; }
            .cs-categories { grid-template-columns: repeat(2, 1fr); }
            .cs-container { padding: 16px; }
        }
        @media (max-width: 380px) {
            .cs-product-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="cs-header">
    <a href="/ComputerSales/" class="cs-logo">
        akkuapps<span>.in</span>
        <small>Computer Sales & Service</small>
    </a>
    <nav class="cs-nav">
        <a href="/ComputerSales/">🏠 Home</a>
        <a href="/ComputerSales/products.php">📦 Products</a>
        <a href="/ComputerSales/cart.php">🛒 Cart</a>
        <a href="/ComputerSales/invoices.php">🧾 Invoices</a>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'moderator'): ?>
            <a href="/ComputerSales/admin.php">⚙️ Admin</a>
        <?php endif; ?>
    </nav>
    <div class="cs-user-menu">
        <div class="cs-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <span class="cs-user-name"><?= Security::e($user['name']) ?></span>
        <span class="cs-coins">🪙<?= number_format($user['coin_balance'] ?? 0, 0) ?></span>
        <a href="/auth/logout.php" class="cs-logout">Logout</a>
    </div>
</header>

<div class="cs-container">

    <!-- Hero -->
    <div class="cs-hero">
        <h1>🖥️ LEO Infotech Computer Sales & Service Center</h1>
        <p>Laptops • Desktops • Components • Repairs • Upgrades</p>
        <div class="cs-hero-actions">
            <a href="/ComputerSales/products.php" class="cs-btn cs-btn-primary">📦 Browse Products</a>
            <a href="/ComputerSales/invoice-create.php" class="cs-btn">🧾 Create Invoice</a>
        </div>
    </div>

    <!-- Search -->
    <div class="cs-search-section">
        <form class="cs-search-form" action="/ComputerSales/products.php" method="GET">
            <input type="text" name="search" placeholder="🔍 Search laptops, desktops, components..." 
                   class="cs-search-input" autocomplete="off">
            <button type="submit" class="cs-btn cs-btn-primary">Search</button>
        </form>
    </div>

    <!-- Featured Products -->
    <div class="cs-section">
        <div class="cs-section-header">
            <h2>🔥 Featured Products</h2>
            <a href="/ComputerSales/products.php?featured=1" class="cs-link">View All →</a>
        </div>

        <?php if (empty($featured['data'])): ?>
            <div class="cs-empty">
                <div class="cs-empty-icon">📦</div>
                <h3>No Products Yet</h3>
                <p>Add products from the admin panel to get started.</p>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/ComputerSales/admin.php" class="cs-btn cs-btn-primary">Go to Admin</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="cs-product-grid">
                <?php foreach ($featured['data'] as $product): ?>
                <article class="cs-product-card">
                    <div class="cs-product-image">
                        <?php if ($product['primary_image']): ?>
                            <img src="<?= Security::e($product['primary_image']) ?>" 
                                 alt="<?= Security::e($product['name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="cs-no-image">💻</div>
                        <?php endif; ?>
                        <?php if ($product['condition_type'] !== 'new'): ?>
                            <span class="cs-condition-badge"><?= ucfirst($product['condition_type']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cs-product-info">
                        <h3><?= Security::e($product['name']) ?></h3>
                        <p class="cs-brand"><?= Security::e($product['brand_name'] ?? 'No Brand') ?></p>
                        <div class="cs-price-row">
                            <?php if ($product['mrp'] > $product['current_price']): ?>
                                <span class="cs-mrp">₹<?= number_format($product['mrp'], 2) ?></span>
                            <?php endif; ?>
                            <span class="cs-price">₹<?= number_format($product['current_price'], 2) ?></span>
                        </div>
                        <div class="cs-product-meta">
                            <span class="cs-stock <?= $product['stock_quantity'] > 0 ? 'in' : 'out' ?>">
                                <?= $product['stock_quantity'] > 0 ? '✅ In Stock (' . $product['stock_quantity'] . ')' : '❌ Out of Stock' ?>
                            </span>
                            <span class="cs-warranty">🛡️ <?= $product['warranty_months'] ?>mo</span>
                        </div>
                        <div class="cs-product-actions">
                            <a href="/ComputerSales/product.php?slug=<?= Security::e($product['slug']) ?>" class="cs-btn">View</a>
                            <button class="cs-btn cs-btn-primary" onclick="addToCart(<?= $product['product_id'] ?>)" 
                                    <?= $product['stock_quantity'] <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                                🛒 Add
                            </button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Arrivals -->
    <?php if (!empty($newArrivals['data'])): ?>
    <div class="cs-section">
        <div class="cs-section-header">
            <h2>🆕 New Arrivals</h2>
            <a href="/ComputerSales/products.php?sort=created_at" class="cs-link">View All →</a>
        </div>
        <div class="cs-product-grid">
            <?php foreach (array_slice($newArrivals['data'], 0, 4) as $product): ?>
            <article class="cs-product-card">
                <div class="cs-product-image">
                    <?php if ($product['primary_image']): ?>
                        <img src="<?= Security::e($product['primary_image']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="cs-no-image">💻</div>
                    <?php endif; ?>
                </div>
                <div class="cs-product-info">
                    <h3><?= Security::e($product['name']) ?></h3>
                    <p class="cs-brand"><?= Security::e($product['brand_name'] ?? '') ?></p>
                    <span class="cs-price">₹<?= number_format($product['current_price'], 2) ?></span>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Categories -->
    <div class="cs-section">
        <h2>📁 Browse by Category</h2>
        <div class="cs-categories">
            <a href="/ComputerSales/products.php?category_slug=laptops" class="cs-category-card">
                <span class="cs-cat-icon">💻</span>
                <span>Laptops</span>
            </a>
            <a href="/ComputerSales/products.php?category_slug=desktops" class="cs-category-card">
                <span class="cs-cat-icon">🖥️</span>
                <span>Desktops</span>
            </a>
            <a href="/ComputerSales/products.php?category_slug=components" class="cs-category-card">
                <span class="cs-cat-icon">🔧</span>
                <span>Components</span>
            </a>
            <a href="/ComputerSales/products.php?category_slug=monitors" class="cs-category-card">
                <span class="cs-cat-icon">🖥️</span>
                <span>Monitors</span>
            </a>
            <a href="/ComputerSales/products.php?category_slug=accessories" class="cs-category-card">
                <span class="cs-cat-icon">🖱️</span>
                <span>Accessories</span>
            </a>
            <a href="/ComputerSales/invoice-create.php" class="cs-category-card cs-cat-action">
                <span class="cs-cat-icon">🧾</span>
                <span>Create Invoice</span>
            </a>
        </div>
    </div>

    <!-- Service Banner -->
    <div class="cs-service-banner">
        <h2>🔧 Repair & Upgrade Services</h2>
        <p>Screen replacement • RAM upgrade • SSD installation • Virus removal • Data recovery • OS installation</p>
        <a href="/ComputerSales/service.php" class="cs-btn cs-btn-success">Book Service</a>
    </div>

</div>

<!-- Floating Action Buttons -->
<div class="cs-quick-actions">
    <a href="/ComputerSales/cart.php" class="cs-fab" title="Cart">
        🛒
        <span class="cs-badge" id="fab-cart-count">0</span>
    </a>
    <a href="/ComputerSales/invoice-create.php" class="cs-fab cs-fab-primary" title="New Invoice">
        🧾
    </a>
    <?php if ($user['role'] === 'admin'): ?>
    <a href="/ComputerSales/admin.php" class="cs-fab cs-fab-admin" title="Admin">
        ⚙️
    </a>
    <?php endif; ?>
</div>

<footer class="cs-footer">
    <p>© <?= date('Y') ?> AkkuApps.in Computer Sales & Service</p>
    <p>🪙 Coin Economy Active • GST Ready • Invoice Management • Inventory Tracking</p>
</footer>

<script>
function addToCart(productId) {
    alert('Added product ' + productId + ' to cart (API integration pending)');
}
</script>

</body>
</html>