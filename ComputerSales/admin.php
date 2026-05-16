<?php
// ComputerSales/admin.php - ADMIN DASHBOARD
// Features: Vendor, Customer, Product, Stock, Sales, Pre-Booking, Product Extensions
// Access: Admin & Moderator only

define('AKKUAPPS_LOADED', true);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Security.php';
require_once __DIR__ . '/Models/Product.php';

use ComputerSales\Core\Database;
use ComputerSales\Core\Security;
use ComputerSales\Models\Product;

// Strict admin check
requireLogin('/ComputerSales/admin.php');
$user = getCurrentUser();
if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'moderator')) {
    header('Location: /ComputerSales/?error=unauthorized');
    exit;
}

$db = new Database();
$productModel = new Product();

// Handle AJAX actions
$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'dashboard';

// Stats
$stats = [
    'total_products' => $db->query("SELECT COUNT(*) FROM cs_products WHERE is_active=1")->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM cs_products WHERE stock_quantity <= low_stock_threshold AND is_active=1")->fetchColumn(),
    'out_of_stock' => $db->query("SELECT COUNT(*) FROM cs_products WHERE stock_quantity = 0 AND is_active=1")->fetchColumn(),
    'total_invoices' => $db->query("SELECT COUNT(*) FROM cs_invoices")->fetchColumn(),
    'pending_payments' => $db->query("SELECT COUNT(*) FROM cs_invoices WHERE payment_status='pending'")->fetchColumn(),
    'today_sales' => $db->query("SELECT COALESCE(SUM(grand_total),0) FROM cs_invoices WHERE invoice_date = CURDATE()")->fetchColumn(),
    'total_customers' => $db->query("SELECT COUNT(*) FROM cs_customers WHERE is_active=1")->fetchColumn(),
    'open_tickets' => $db->query("SELECT COUNT(*) FROM cs_service_tickets WHERE status IN ('received','diagnosed','in_progress','waiting_parts')")->fetchColumn(),
];

// Fetch data based on tab
$products = [];
$customers = [];
$invoices = [];
$lowStockItems = [];
$tickets = [];

if ($tab === 'products') {
    $products = $productModel->getAll([], 1, 50);
}
if ($tab === 'stock') {
    $lowStockItems = $db->fetchAll("SELECT p.*, b.name as brand_name, c.name as category_name 
        FROM cs_products p 
        LEFT JOIN cs_brands b ON p.brand_id = b.brand_id 
        LEFT JOIN cs_categories c ON p.category_id = c.category_id 
        WHERE p.stock_quantity <= p.low_stock_threshold AND p.is_active = 1 
        ORDER BY p.stock_quantity ASC");
}
if ($tab === 'customers') {
    $customers = $db->fetchAll("SELECT * FROM cs_customers WHERE is_active = 1 ORDER BY created_at DESC LIMIT 50");
}
if ($tab === 'sales') {
    $invoices = $db->fetchAll("SELECT i.*, c.name as customer_name, c.phone as customer_phone 
        FROM cs_invoices i 
        JOIN cs_customers c ON i.customer_id = c.customer_id 
        ORDER BY i.invoice_date DESC, i.invoice_id DESC LIMIT 50");
}
if ($tab === 'tickets') {
    $tickets = $db->fetchAll("SELECT t.*, c.name as customer_name, c.phone as customer_phone, b.name as brand_name 
        FROM cs_service_tickets t 
        JOIN cs_customers c ON t.customer_id = c.customer_id 
        LEFT JOIN cs_brands b ON t.brand_id = b.brand_id 
        ORDER BY t.received_at DESC LIMIT 50");
}

$pageTitle = 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Computer Sales</title>
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
            --red: #ef4444;
            --yellow: #f59e0b;
            --orange: #f97316;
            --purple: #a855f7;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }

        /* Header */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-logo { font-size: 18px; font-weight: 800; color: var(--bright); }
        .admin-logo span { color: var(--accent); }
        .admin-user { display: flex; align-items: center; gap: 10px; }
        .admin-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--accent); display: flex; align-items: center;
            justify-content: center; color: white; font-weight: 700; font-size: 12px;
        }
        .admin-role {
            background: var(--purple); color: white;
            padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
        }

        /* Sidebar */
        .admin-layout { display: flex; min-height: calc(100vh - 55px); }
        .admin-sidebar {
            width: 220px;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 16px 0;
            flex-shrink: 0;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .admin-nav-item:hover, .admin-nav-item.active {
            background: rgba(99,102,241,0.1);
            color: var(--bright);
            border-left-color: var(--accent);
        }
        .admin-nav-icon { font-size: 16px; width: 24px; text-align: center; }
        .admin-nav-badge {
            margin-left: auto;
            background: var(--red);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
        }
        .admin-nav-badge.green { background: var(--green); }
        .admin-nav-badge.yellow { background: var(--yellow); color: #000; }

        /* Main Content */
        .admin-main { flex: 1; padding: 24px; overflow-x: auto; }
        .admin-page-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--bright);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.2s;
        }
        .stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .stat-icon { font-size: 28px; margin-bottom: 8px; }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--bright);
            margin-bottom: 4px;
        }
        .stat-value.green { color: var(--green); }
        .stat-value.red { color: var(--red); }
        .stat-value.yellow { color: var(--yellow); }
        .stat-value.purple { color: var(--purple); }
        .stat-label {
            font-size: 12px;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Section Cards */
        .section-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--bright);
        }
        .section-actions { display: flex; gap: 8px; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn:hover { border-color: var(--accent); color: var(--bright); }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-success {
            background: var(--green);
            border-color: var(--green);
            color: white;
        }
        .btn-danger {
            background: var(--red);
            border-color: var(--red);
            color: white;
        }
        .btn-sm { padding: 5px 12px; font-size: 12px; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            background: rgba(0,0,0,0.2);
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .data-table tr:hover td { background: rgba(99,102,241,0.05); }
        .data-table tr:last-child td { border-bottom: none; }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-green { background: rgba(16,185,129,0.15); color: var(--green); }
        .badge-red { background: rgba(239,68,68,0.15); color: var(--red); }
        .badge-yellow { background: rgba(245,158,11,0.15); color: var(--yellow); }
        .badge-blue { background: rgba(99,102,241,0.15); color: var(--accent); }
        .badge-purple { background: rgba(168,85,247,0.15); color: var(--purple); }

        /* Stock indicator */
        .stock-bar {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 6px;
        }
        .stock-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s;
        }
        .stock-fill.high { background: var(--green); }
        .stock-fill.medium { background: var(--yellow); }
        .stock-fill.low { background: var(--red); }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding: 20px;
        }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input, .form-select, .form-textarea {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #15151d;
            color: var(--bright);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--accent);
        }
        .form-textarea { min-height: 100px; resize: vertical; }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            padding: 0 20px;
            border-bottom: 1px solid var(--border);
        }
        .tab {
            padding: 10px 16px;
            color: var(--text);
            font-size: 13px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab:hover { color: var(--bright); }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .alert-warning {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            color: var(--yellow);
        }

        /* Mobile */
        @media (max-width: 768px) {
            .admin-sidebar { display: none; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px; }
        }
    </style>
</head>
<body>

<header class="admin-header">
    <div class="admin-logo">akkuapps<span>.in</span> Admin</div>
    <div class="admin-user">
        <div class="admin-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <span style="color:var(--bright);font-weight:600;"><?= Security::e($user['name']) ?></span>
        <span class="admin-role"><?= $user['role'] ?></span>
        <a href="/ComputerSales/" class="btn btn-sm">🏠 Store</a>
        <a href="/auth/logout.php" class="btn btn-sm">Logout</a>
    </div>
</header>

<div class="admin-layout">

    <!-- Sidebar -->
    <nav class="admin-sidebar">
        <a href="?tab=dashboard" class="admin-nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📊</span> Dashboard
        </a>
        <a href="?tab=products" class="admin-nav-item <?= $tab === 'products' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📦</span> Products
        </a>
        <a href="?tab=stock" class="admin-nav-item <?= $tab === 'stock' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📉</span> Stock Alert
            <?php if ($stats['low_stock'] > 0): ?>
                <span class="admin-nav-badge"><?= $stats['low_stock'] ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=customers" class="admin-nav-item <?= $tab === 'customers' ? 'active' : '' ?>">
            <span class="admin-nav-icon">👥</span> Customers
        </a>
        <a href="?tab=sales" class="admin-nav-item <?= $tab === 'sales' ? 'active' : '' ?>">
            <span class="admin-nav-icon">💰</span> Sales & Invoices
        </a>
        <a href="?tab=prebooking" class="admin-nav-item <?= $tab === 'prebooking' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📋</span> Pre-Booking
        </a>
        <a href="?tab=tickets" class="admin-nav-item <?= $tab === 'tickets' ? 'active' : '' ?>">
            <span class="admin-nav-icon">🔧</span> Service Tickets
            <?php if ($stats['open_tickets'] > 0): ?>
                <span class="admin-nav-badge yellow"><?= $stats['open_tickets'] ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=vendors" class="admin-nav-item <?= $tab === 'vendors' ? 'active' : '' ?>">
            <span class="admin-nav-icon">🏭</span> Vendors
        </a>
        <a href="?tab=reports" class="admin-nav-item <?= $tab === 'reports' ? 'active' : '' ?>">
            <span class="admin-nav-icon">📈</span> Reports
        </a>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">

        <?php if ($tab === 'dashboard'): ?>
        <!-- DASHBOARD -->
        <h1 class="admin-page-title">📊 Dashboard Overview</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📉</div>
                <div class="stat-value yellow"><?= number_format($stats['low_stock']) ?></div>
                <div class="stat-label">Low Stock Alert</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-value red"><?= number_format($stats['out_of_stock']) ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🧾</div>
                <div class="stat-value"><?= number_format($stats['total_invoices']) ?></div>
                <div class="stat-label">Total Invoices</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-value yellow"><?= number_format($stats['pending_payments']) ?></div>
                <div class="stat-label">Pending Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value green">₹<?= number_format($stats['today_sales'], 2) ?></div>
                <div class="stat-label">Today's Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value purple"><?= number_format($stats['total_customers']) ?></div>
                <div class="stat-label">Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔧</div>
                <div class="stat-value"><?= number_format($stats['open_tickets']) ?></div>
                <div class="stat-label">Open Tickets</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">⚡ Quick Actions</span>
            </div>
            <div style="padding:20px;display:flex;gap:12px;flex-wrap:wrap;">
                <a href="?tab=products&action=add" class="btn btn-primary">➕ Add Product</a>
                <a href="?tab=customers&action=add" class="btn btn-primary">➕ Add Customer</a>
                <a href="/ComputerSales/invoice-create.php" class="btn btn-success">🧾 Create Invoice</a>
                <a href="?tab=tickets&action=add" class="btn btn-primary">🔧 New Ticket</a>
                <a href="?tab=stock" class="btn">📉 View Stock Alerts</a>
            </div>
        </div>

        <?php elseif ($tab === 'products'): ?>
        <!-- PRODUCTS -->
        <h1 class="admin-page-title">📦 Product Management</h1>

        <?php if ($action === 'add'): ?>
        <!-- Add Product Form -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">➕ Add New Product</span>
                <a href="?tab=products" class="btn btn-sm">← Back</a>
            </div>
            <form action="api/product-save.php" method="POST" class="form-grid">
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-input" placeholder="e.g. HP-15S-001" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g. HP 15s Intel Core i3" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand</label>
                    <select name="brand_id" class="form-select">
                        <option value="">Select Brand</option>
                        <?php $brands = $db->fetchAll("SELECT * FROM cs_brands WHERE is_active=1 ORDER BY name");
                        foreach ($brands as $b): ?>
                            <option value="<?= $b['brand_id'] ?>"><?= Security::e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php $cats = $db->fetchAll("SELECT * FROM cs_categories WHERE is_active=1 ORDER BY name");
                        foreach ($cats as $c): ?>
                            <option value="<?= $c['category_id'] ?>"><?= Security::e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">MRP (₹)</label>
                    <input type="number" name="mrp" class="form-input" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Selling Price (₹)</label>
                    <input type="number" name="current_price" class="form-input" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" name="cost_price" class="form-input" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock Quantity</label>
                    <input type="number" name="stock_quantity" class="form-input" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Low Stock Alert Threshold</label>
                    <input type="number" name="low_stock_threshold" class="form-input" value="5" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Warranty (Months)</label>
                    <input type="number" name="warranty_months" class="form-input" value="12" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Condition</label>
                    <select name="condition_type" class="form-select">
                        <option value="new">New</option>
                        <option value="refurbished">Refurbished</option>
                        <option value="open_box">Open Box</option>
                        <option value="used">Used</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label class="form-label">Short Description</label>
                    <input type="text" name="short_description" class="form-input" placeholder="Brief product summary">
                </div>
                <div class="form-group full">
                    <label class="form-label">Full Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Detailed product description..."></textarea>
                </div>
                <div class="form-group full">
                    <label class="form-label">Specifications (JSON format)</label>
                    <textarea name="specifications" class="form-textarea" placeholder='{"processor":"Intel i3","ram":"8GB","storage":"512GB SSD"}'></textarea>
                </div>
                <div class="form-group full" style="display:flex;gap:12px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_featured" value="1"> Featured Product
                    </label>
                    <button type="submit" class="btn btn-primary">💾 Save Product</button>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- Product List -->
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">All Products</span>
                <div class="section-actions">
                    <a href="?tab=products&action=add" class="btn btn-primary">➕ Add Product</a>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>MRP</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products['data'] ?? [] as $p): ?>
                    <tr>
                        <td>#<?= $p['product_id'] ?></td>
                        <td>
                            <strong style="color:var(--bright);"><?= Security::e($p['name']) ?></strong><br>
                            <small style="opacity:0.6;"><?= Security::e($p['sku']) ?></small>
                        </td>
                        <td><?= Security::e($p['brand_name'] ?? '-') ?></td>
                        <td><?= Security::e($p['category_name'] ?? '-') ?></td>
                        <td>₹<?= number_format($p['mrp'], 2) ?></td>
                        <td style="color:var(--green);font-weight:700;">₹<?= number_format($p['current_price'], 2) ?></td>
                        <td>
                            <?= $p['stock_quantity'] ?>
                            <div class="stock-bar">
                                <?php 
                                $pct = min(100, ($p['stock_quantity'] / max($p['low_stock_threshold'] * 4, 1)) * 100);
                                $cls = $pct > 50 ? 'high' : ($pct > 20 ? 'medium' : 'low');
                                ?>
                                <div class="stock-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="badge badge-green">Active</span>
                            <?php else: ?>
                                <span class="badge badge-red">Inactive</span>
                            <?php endif; ?>
                            <?php if ($p['is_featured']): ?>
                                <span class="badge badge-purple">Featured</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?tab=products&action=edit&id=<?= $p['product_id'] ?>" class="btn btn-sm">✏️</a>
                            <a href="api/product-toggle.php?id=<?= $p['product_id'] ?>" class="btn btn-sm">🔄</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php elseif ($tab === 'stock'): ?>
        <!-- STOCK ALERT -->
        <h1 class="admin-page-title">📉 Stock Management</h1>

        <?php if ($stats['low_stock'] > 0): ?>
        <div class="alert alert-warning">
            ⚠️ <?= $stats['low_stock'] ?> products are at or below their low stock threshold. Consider restocking soon.
        </div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Low Stock & Out of Stock Items</span>
                <a href="?tab=products&action=add" class="btn btn-primary">➕ Add Stock</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockItems as $item): 
                        $isOut = $item['stock_quantity'] <= 0;
                        $isLow = !$isOut && $item['stock_quantity'] <= $item['low_stock_threshold'];
                    ?>
                    <tr>
                        <td>
                            <strong style="color:var(--bright);"><?= Security::e($item['name']) ?></strong><br>
                            <small><?= Security::e($item['sku']) ?></small>
                        </td>
                        <td><?= Security::e($item['brand_name'] ?? '-') ?></td>
                        <td><?= Security::e($item['category_name'] ?? '-') ?></td>
                        <td style="font-size:18px;font-weight:800;color:<?= $isOut ? 'var(--red)' : 'var(--yellow)' ?>;">
                            <?= $item['stock_quantity'] ?>
                        </td>
                        <td><?= $item['low_stock_threshold'] ?></td>
                        <td>
                            <?php if ($isOut): ?>
                                <span class="badge badge-red">OUT OF STOCK</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">LOW STOCK</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="api/stock-update.php" method="POST" style="display:flex;gap:6px;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="number" name="quantity" class="form-input" style="width:80px;padding:6px 10px;" placeholder="Qty" min="1">
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'customers'): ?>
        <!-- CUSTOMERS -->
        <h1 class="admin-page-title">👥 Customer Management</h1>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">All Customers</span>
                <a href="?tab=customers&action=add" class="btn btn-primary">➕ Add Customer</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>GST</th>
                        <th>Credit Limit</th>
                        <th>Outstanding</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>#<?= $c['customer_id'] ?></td>
                        <td><strong style="color:var(--bright);"><?= Security::e($c['name']) ?></strong></td>
                        <td><?= Security::e($c['phone']) ?></td>
                        <td><?= Security::e($c['email'] ?? '-') ?></td>
                        <td><span class="badge badge-blue"><?= ucfirst($c['customer_type']) ?></span></td>
                        <td><?= Security::e($c['gst_number'] ?? '-') ?></td>
                        <td>₹<?= number_format($c['credit_limit'], 2) ?></td>
                        <td style="color:<?= $c['outstanding_balance'] > 0 ? 'var(--red)' : 'var(--green)' ?>;font-weight:700;">
                            ₹<?= number_format($c['outstanding_balance'], 2) ?>
                        </td>
                        <td>
                            <a href="?tab=customers&action=edit&id=<?= $c['customer_id'] ?>" class="btn btn-sm">✏️</a>
                            <a href="/ComputerSales/invoice-create.php?customer=<?= $c['customer_id'] ?>" class="btn btn-sm btn-primary">🧾</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'sales'): ?>
        <!-- SALES -->
        <h1 class="admin-page-title">💰 Sales & Invoices</h1>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Recent Invoices</span>
                <a href="/ComputerSales/invoice-create.php" class="btn btn-success">🧾 New Invoice</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Grand Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong style="color:var(--accent);"><?= Security::e($inv['invoice_number']) ?></strong></td>
                        <td><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
                        <td>
                            <?= Security::e($inv['customer_name']) ?><br>
                            <small><?= Security::e($inv['customer_phone']) ?></small>
                        </td>
                        <td><span class="badge badge-blue"><?= ucfirst($inv['service_type']) ?></span></td>
                        <td style="font-weight:700;">₹<?= number_format($inv['grand_total'], 2) ?></td>
                        <td style="color:var(--green);">₹<?= number_format($inv['amount_paid'], 2) ?></td>
                        <td style="color:var(--red);font-weight:700;">₹<?= number_format($inv['amount_due'], 2) ?></td>
                        <td>
                            <?php if ($inv['payment_status'] === 'paid'): ?>
                                <span class="badge badge-green">Paid</span>
                            <?php elseif ($inv['payment_status'] === 'partial'): ?>
                                <span class="badge badge-yellow">Partial</span>
                            <?php elseif ($inv['payment_status'] === 'pending'): ?>
                                <span class="badge badge-red">Pending</span>
                            <?php else: ?>
                                <span class="badge"><?= ucfirst($inv['payment_status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="invoice-print.php?id=<?= $inv['invoice_id'] ?>" class="btn btn-sm">🖨️</a>
                            <a href="api/payment-add.php?invoice=<?= $inv['invoice_id'] ?>" class="btn btn-sm btn-success">💰</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'prebooking'): ?>
        <!-- PRE-BOOKING -->
        <h1 class="admin-page-title">📋 Pre-Booking (Out of Stock)</h1>

        <div class="alert alert-warning">
            📋 Pre-booking allows customers to reserve out-of-stock items. When stock arrives, invoice them automatically.
        </div>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Out of Stock Products Available for Pre-Booking</span>
            </div>
            <?php 
            $outOfStock = $db->fetchAll("SELECT p.*, b.name as brand_name, c.name as category_name 
                FROM cs_products p 
                LEFT JOIN cs_brands b ON p.brand_id = b.brand_id 
                LEFT JOIN cs_categories c ON p.category_id = c.category_id 
                WHERE p.stock_quantity = 0 AND p.is_active = 1");
            ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Expected Restock</th>
                        <th>Pre-Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outOfStock as $item): ?>
                    <tr>
                        <td>
                            <strong style="color:var(--bright);"><?= Security::e($item['name']) ?></strong><br>
                            <small><?= Security::e($item['sku']) ?></small>
                        </td>
                        <td><?= Security::e($item['brand_name'] ?? '-') ?></td>
                        <td><?= Security::e($item['category_name'] ?? '-') ?></td>
                        <td style="color:var(--green);font-weight:700;">₹<?= number_format($item['current_price'], 2) ?></td>
                        <td><input type="date" class="form-input" style="width:140px;padding:6px 10px;"></td>
                        <td><span class="badge badge-yellow">0 booked</span></td>
                        <td>
                            <a href="api/prebook-create.php?product=<?= $item['product_id'] ?>" class="btn btn-primary btn-sm">📋 Pre-Book</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($outOfStock)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;">No out of stock products. All items are available!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'tickets'): ?>
        <!-- SERVICE TICKETS -->
        <h1 class="admin-page-title">🔧 Service Tickets</h1>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">All Service Tickets</span>
                <a href="?tab=tickets&action=add" class="btn btn-primary">🔧 New Ticket</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Brand/Model</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><strong style="color:var(--accent);"><?= Security::e($t['ticket_number']) ?></strong></td>
                        <td>
                            <?= Security::e($t['customer_name']) ?><br>
                            <small><?= Security::e($t['customer_phone']) ?></small>
                        </td>
                        <td><?= ucfirst($t['device_type']) ?></td>
                        <td><?= Security::e($t['brand_name'] ?? '-') ?> / <?= Security::e($t['model'] ?? '-') ?></td>
                        <td><?= Security::e(substr($t['issue_description'], 0, 50)) ?>...</td>
                        <td>
                            <?php 
                            $statusColors = [
                                'received' => 'badge-blue',
                                'diagnosed' => 'badge-purple',
                                'in_progress' => 'badge-yellow',
                                'waiting_parts' => 'badge-yellow',
                                'ready' => 'badge-green',
                                'delivered' => 'badge-green',
                                'cancelled' => 'badge-red'
                            ];
                            ?>
                            <span class="badge <?= $statusColors[$t['status']] ?? 'badge-blue' ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                        </td>
                        <td>
                            <?php if ($t['priority'] === 'urgent'): ?>
                                <span class="badge badge-red">URGENT</span>
                            <?php elseif ($t['priority'] === 'high'): ?>
                                <span class="badge badge-yellow">High</span>
                            <?php else: ?>
                                <span class="badge"><?= ucfirst($t['priority']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M', strtotime($t['received_at'])) ?></td>
                        <td>
                            <a href="?tab=tickets&action=edit&id=<?= $t['ticket_id'] ?>" class="btn btn-sm">✏️</a>
                            <a href="api/ticket-update.php?id=<?= $t['ticket_id'] ?>&status=ready" class="btn btn-sm btn-success">✓</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:40px;">No service tickets yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'vendors'): ?>
        <!-- VENDORS -->
        <h1 class="admin-page-title">🏭 Vendor Management</h1>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Vendors & Suppliers</span>
                <a href="?tab=vendors&action=add" class="btn btn-primary">➕ Add Vendor</a>
            </div>
            <div style="padding:40px;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">🏭</div>
                <h3 style="color:var(--bright);margin-bottom:8px;">Vendor Module</h3>
                <p style="margin-bottom:20px;">Track suppliers, purchase orders, and incoming stock.</p>
                <p style="opacity:0.6;font-size:13px;">To implement: Create <code>cs_vendors</code> and <code>cs_purchase_orders</code> tables</p>
            </div>
        </div>

        <?php elseif ($tab === 'reports'): ?>
        <!-- REPORTS -->
        <h1 class="admin-page-title">📈 Reports & Analytics</h1>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Monthly Sales Report</span>
            </div>
            <?php $monthly = $db->fetchAll("SELECT * FROM cs_vw_monthly_sales LIMIT 12"); ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Invoices</th>
                        <th>Revenue</th>
                        <th>Tax</th>
                        <th>Collected</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly as $m): ?>
                    <tr>
                        <td><strong style="color:var(--bright);"><?= $m['month'] ?></strong></td>
                        <td><?= number_format($m['total_invoices']) ?></td>
                        <td style="color:var(--green);font-weight:700;">₹<?= number_format($m['total_revenue'], 2) ?></td>
                        <td>₹<?= number_format($m['total_tax'], 2) ?></td>
                        <td style="color:var(--green);">₹<?= number_format($m['total_collected'], 2) ?></td>
                        <td style="color:var(--red);">₹<?= number_format($m['total_pending'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($monthly)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:40px;">No sales data yet. Create invoices to see reports.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Low Stock Report</span>
            </div>
            <?php $lowstock = $db->fetchAll("SELECT * FROM cs_vw_low_stock_alert"); ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Threshold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowstock as $ls): ?>
                    <tr>
                        <td><strong style="color:var(--bright);"><?= Security::e($ls['name']) ?></strong></td>
                        <td><?= Security::e($ls['brand_name'] ?? '-') ?></td>
                        <td><?= Security::e($ls['category_name'] ?? '-') ?></td>
                        <td style="color:var(--red);font-weight:700;"><?= $ls['stock_quantity'] ?></td>
                        <td><?= $ls['low_stock_threshold'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lowstock)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:40px;">All stock levels are healthy!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>