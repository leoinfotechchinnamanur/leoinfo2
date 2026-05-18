<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';

// Core stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
$totalPosts = $pdo->query("SELECT COUNT(*) FROM user_posts")->fetchColumn() ?? 0;
$totalGames = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE status = 'completed'")->fetchColumn() ?? 0;
$todayLogins = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()")->fetchColumn() ?? 0;
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM upi_payments WHERE status = 'pending'")->fetchColumn() ?? 0;
$totalCoins = $pdo->query("SELECT SUM(coin_balance) FROM users")->fetchColumn() ?? 0;

// Marketplace stats (if tables exist)
$marketplaceStats = [];
try {
    $marketplaceStats['products'] = $pdo->query("SELECT COUNT(*) FROM cs_products WHERE status = 'active'")->fetchColumn() ?? 0;
    $marketplaceStats['brands'] = $pdo->query("SELECT COUNT(*) FROM cs_brands")->fetchColumn() ?? 0;
    $marketplaceStats['categories'] = $pdo->query("SELECT COUNT(*) FROM cs_categories")->fetchColumn() ?? 0;
    $marketplaceStats['orders'] = $pdo->query("SELECT COUNT(*) FROM cs_invoices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $marketplaceStats['lowStock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= reorder_level AND current_stock > 0")->fetchColumn() ?? 0;
    $marketplaceStats['outOfStock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= 0")->fetchColumn() ?? 0;
    $marketplaceStats['revenue'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM cs_invoices WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
} catch (Exception $e) {
    $marketplaceStats = ['products' => 0, 'brands' => 0, 'categories' => 0, 'orders' => 0, 'lowStock' => 0, 'outOfStock' => 0, 'revenue' => 0];
}

$stats = getEconomyStats();
$collectionBox = new AkkuCollectionBox($pdo);
$boxBalance = $collectionBox->getBalance();
?>

<!-- Link new theme CSS -->
<link rel="stylesheet" href="/assets/css/themes.css?v=2">

<style>
  .admin-layout { display: flex; min-height: calc(100vh - 41px); }
  .admin-sidebar {
    width: 210px;
    background: var(--bg-card);
    border-right: 1px solid var(--border-color);
    padding: var(--space-sm);
    position: fixed;
    left: 0;
    top: 41px;
    bottom: 0;
    overflow-y: auto;
    z-index: 90;
  }
  .admin-main {
    margin-left: 210px;
    flex: 1;
    padding: var(--space-md);
    min-height: calc(100vh - 41px);
  }
  .sidebar-brand {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    margin-bottom: var(--space-sm);
    font-size: var(--font-md);
    font-weight: 700;
    color: var(--text-primary);
  }
  .sidebar-brand .logo-icon {
    width: 24px; height: 24px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--border-radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: var(--font-xs);
  }
  .sidebar-section { margin-bottom: var(--space-sm); }
  .sidebar-title {
    font-size: var(--font-xs);
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: var(--space-sm) var(--space-md);
  }
  .sidebar-link {
    display: flex; align-items: center; gap: var(--space-sm);
    padding: 5px 10px;
    font-size: var(--font-sm);
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: var(--border-radius-sm);
    transition: all var(--transition-fast);
    margin-bottom: 1px;
  }
  .sidebar-link:hover, .sidebar-link.active {
    background: var(--bg-hover);
    color: var(--text-primary);
  }
  .sidebar-link.active {
    background: rgba(99,102,241,0.1);
    color: var(--primary-light);
    border-left: 2px solid var(--primary);
  }
  .sidebar-link .icon { font-size: var(--font-md); width: 18px; text-align: center; }
  .sidebar-link .badge {
    margin-left: auto;
    background: var(--danger);
    color: white;
    font-size: 9px;
    padding: 0 4px;
    border-radius: 6px;
    min-width: 14px;
    text-align: center;
  }

  /* Quick actions bar */
  .quick-bar {
    display: flex;
    gap: var(--space-sm);
    margin-bottom: var(--space-md);
    flex-wrap: wrap;
  }
  .quick-bar .btn { font-size: var(--font-xs); }

  /* Section cards */
  .section-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-md);
  }
  .section-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--space-md);
    transition: all var(--transition);
  }
  .section-card:hover {
    border-color: var(--border-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
  }
  .section-card h3 {
    font-size: var(--font-md);
    font-weight: 600;
    margin-bottom: var(--space-sm);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    color: var(--text-primary);
  }
  .section-card .links {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .section-card .links a {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: 5px 8px;
    font-size: var(--font-sm);
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: var(--border-radius-sm);
    transition: all var(--transition-fast);
  }
  .section-card .links a:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
  }
  .section-card .links a .badge {
    margin-left: auto;
    background: var(--primary);
    color: white;
    font-size: 9px;
    padding: 1px 5px;
    border-radius: 8px;
  }

  /* Stock alerts */
  .stock-alert {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--border-radius);
    margin-bottom: var(--space-sm);
    font-size: var(--font-sm);
  }
  .stock-alert.warning {
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.2);
    color: var(--warning);
  }
  .stock-alert.danger {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    color: var(--danger);
  }

  /* Mobile */
  @media (max-width: 768px) {
    .admin-sidebar {
      transform: translateX(-100%);
      transition: transform var(--transition);
      width: 240px;
      z-index: 99;
    }
    .admin-sidebar.open { transform: translateX(0); }
    .admin-main { margin-left: 0; }
    .section-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
      <div class="logo-icon">A</div>
      <span>AkkuApps</span>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Overview</div>
      <a href="/admin/dashboard.php" class="sidebar-link active">
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="/admin/analytics.php" class="sidebar-link">
        <span class="icon">📈</span> Analytics
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Marketplace</div>
      <a href="/admin/marketplace.php" class="sidebar-link">
        <span class="icon">🏪</span> Control Center
      </a>
      <a href="/admin/marketplace.php?tab=products" class="sidebar-link">
        <span class="icon">📦</span> Products
        <?php if ($marketplaceStats['products'] > 0): ?><span class="badge"><?= $marketplaceStats['products'] ?></span><?php endif; ?>
      </a>
      <a href="/admin/marketplace.php?tab=orders" class="sidebar-link">
        <span class="icon">🛒</span> Orders
        <?php if ($marketplaceStats['orders'] > 0): ?><span class="badge"><?= $marketplaceStats['orders'] ?></span><?php endif; ?>
      </a>
      <a href="/admin/marketplace.php?tab=stock" class="sidebar-link">
        <span class="icon">📊</span> Stock & Inventory
        <?php if ($marketplaceStats['lowStock'] > 0 || $marketplaceStats['outOfStock'] > 0): ?>
          <span class="badge"><?= $marketplaceStats['lowStock'] + $marketplaceStats['outOfStock'] ?></span>
        <?php endif; ?>
      </a>
      <a href="/admin/marketplace.php?tab=customers" class="sidebar-link">
        <span class="icon">👥</span> Customers
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Content</div>
      <a href="/admin/users.php" class="sidebar-link">
        <span class="icon">👤</span> Users
        <span class="badge"><?= $totalUsers ?></span>
      </a>
      <a href="/admin/posts.php" class="sidebar-link">
        <span class="icon">📝</span> Posts
        <span class="badge"><?= $totalPosts ?></span>
      </a>
      <a href="/admin/comments.php" class="sidebar-link">
        <span class="icon">💬</span> Comments
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">Economy</div>
      <a href="/admin/coins.php" class="sidebar-link">
        <span class="icon">🪙</span> Coins
      </a>
      <a href="/admin/economy.php" class="sidebar-link">
        <span class="icon">💰</span> Economy
      </a>
      <a href="/admin/collectionbox.php" class="sidebar-link">
        <span class="icon">🏦</span> CollectionBox
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-title">System</div>
      <a href="/" class="sidebar-link" style="color: var(--primary);">
        <span class="icon">🏠</span> Back to Site
      </a>
      <a href="/auth/logout.php" class="sidebar-link" style="color: var(--danger);">
        <span class="icon">🚪</span> Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="admin-main">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <span class="icon">🎛️</span> Admin Dashboard
        </h1>
        <p class="page-subtitle">Welcome back, <?= htmlspecialchars($user['name']) ?> — Here's what's happening today</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge badge-success" style="padding:4px 10px;font-size:11px;">
          🟢 Online
        </span>
        <button class="btn btn-primary btn-sm" onclick="location.href='/admin/marketplace.php'">
          🏪 Marketplace
        </button>
      </div>
    </div>

    <!-- Stock Alerts -->
    <?php if ($marketplaceStats['outOfStock'] > 0): ?>
    <div class="stock-alert danger" data-reveal="up" data-reveal-delay="0">
      <span>⚠️</span>
      <strong><?= $marketplaceStats['outOfStock'] ?> products</strong> are out of stock.
      <a href="/admin/marketplace.php?tab=stock&filter=out" style="margin-left:auto;color:inherit;text-decoration:underline;">View →</a>
    </div>
    <?php endif; ?>
    <?php if ($marketplaceStats['lowStock'] > 0): ?>
    <div class="stock-alert warning" data-reveal="up" data-reveal-delay="0.1">
      <span>⚡</span>
      <strong><?= $marketplaceStats['lowStock'] ?> products</strong> running low on stock.
      <a href="/admin/marketplace.php?tab=stock&filter=low" style="margin-left:auto;color:inherit;text-decoration:underline;">View →</a>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-4" style="margin-bottom: var(--space-md);">
      <div class="stat-card primary" data-reveal="up" data-reveal-delay="0">
        <div class="stat-icon">👤</div>
        <div class="stat-value" data-count="<?= $totalUsers ?>" data-duration="1200">0</div>
        <div class="stat-label">Total Users</div>
      </div>
      <div class="stat-card success" data-reveal="up" data-reveal-delay="0.05">
        <div class="stat-icon">📝</div>
        <div class="stat-value" data-count="<?= $totalPosts ?>" data-duration="1200">0</div>
        <div class="stat-label">Total Posts</div>
      </div>
      <div class="stat-card warning" data-reveal="up" data-reveal-delay="0.1">
        <div class="stat-icon">🪙</div>
        <div class="stat-value" data-count="<?= $totalCoins ?>" data-duration="1500">0</div>
        <div class="stat-label">Total Coins</div>
      </div>
      <div class="stat-card purple" data-reveal="up" data-reveal-delay="0.15">
        <div class="stat-icon">🏦</div>
        <div class="stat-value" data-count="<?= $boxBalance ?>" data-duration="1200">0</div>
        <div class="stat-label">CollectionBox</div>
      </div>
    </div>

    <!-- Marketplace Stats -->
    <div class="grid grid-4" style="margin-bottom: var(--space-md);">
      <div class="stat-card" data-reveal="up" data-reveal-delay="0.2">
        <div class="stat-icon">📦</div>
        <div class="stat-value" data-count="<?= $marketplaceStats['products'] ?>" data-duration="1000">0</div>
        <div class="stat-label">Active Products</div>
      </div>
      <div class="stat-card success" data-reveal="up" data-reveal-delay="0.25">
        <div class="stat-icon">💰</div>
        <div class="stat-value" data-count="<?= $marketplaceStats['revenue'] ?>" data-prefix="₹" data-duration="1500">0</div>
        <div class="stat-label">30-Day Revenue</div>
      </div>
      <div class="stat-card warning" data-reveal="up" data-reveal-delay="0.3">
        <div class="stat-icon">🛒</div>
        <div class="stat-value" data-count="<?= $marketplaceStats['orders'] ?>" data-duration="1000">0</div>
        <div class="stat-label">Recent Orders</div>
      </div>
      <div class="stat-card danger" data-reveal="up" data-reveal-delay="0.35">
        <div class="stat-icon">📊</div>
        <div class="stat-value" data-count="<?= $marketplaceStats['lowStock'] + $marketplaceStats['outOfStock'] ?>" data-duration="1000">0</div>
        <div class="stat-label">Stock Alerts</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-bar" data-reveal="up" data-reveal-delay="0.4">
      <a href="/admin/marketplace.php?action=add-product" class="btn btn-primary btn-sm">➕ Add Product</a>
      <a href="/admin/marketplace.php?action=import" class="btn btn-secondary btn-sm">📥 Import Excel</a>
      <a href="/admin/marketplace.php?tab=orders" class="btn btn-outline btn-sm">📋 View Orders</a>
      <a href="/admin/marketplace.php?tab=stock" class="btn btn-outline btn-sm">📊 Stock Report</a>
      <a href="/admin/users.php" class="btn btn-ghost btn-sm">👤 Manage Users</a>
    </div>

    <!-- Section Grid -->
    <div class="section-grid">
      <div class="section-card" data-reveal="up" data-reveal-delay="0.45">
        <h3><span>🏪</span> Marketplace</h3>
        <div class="links">
          <a href="/admin/marketplace.php">🏪 Control Center <span class="badge">NEW</span></a>
          <a href="/admin/marketplace.php?tab=products">📦 Products (<?= $marketplaceStats['products'] ?>)</a>
          <a href="/admin/marketplace.php?tab=brands">🏷️ Brands (<?= $marketplaceStats['brands'] ?>)</a>
          <a href="/admin/marketplace.php?tab=categories">📂 Categories (<?= $marketplaceStats['categories'] ?>)</a>
          <a href="/admin/marketplace.php?tab=orders">🛒 Orders (<?= $marketplaceStats['orders'] ?>)</a>
          <a href="/admin/marketplace.php?tab=stock">📊 Stock & Inventory</a>
          <a href="/admin/marketplace.php?tab=customers">👥 Customers</a>
          <a href="/admin/marketplace.php?action=import">📥 Bulk Import (Excel)</a>
        </div>
      </div>

      <div class="section-card" data-reveal="up" data-reveal-delay="0.5">
        <h3><span>💰</span> Economy</h3>
        <div class="links">
          <a href="/admin/coins.php">🪙 Coin Management</a>
          <a href="/admin/economy.php">📊 Economy Manager <span class="badge">NEW</span></a>
          <a href="/admin/collectionbox.php">🏦 CollectionBox</a>
          <a href="/admin/payments.php">💳 UPI Payments
            <?php if ($pendingPayments > 0): ?><span class="badge"><?= $pendingPayments ?> pending</span><?php endif; ?>
          </a>
        </div>
      </div>

      <div class="section-card" data-reveal="up" data-reveal-delay="0.55">
        <h3><span>👤</span> Management</h3>
        <div class="links">
          <a href="/admin/users.php">👤 Users <span class="badge"><?= $totalUsers ?></span></a>
          <a href="/admin/posts.php">📝 Posts <span class="badge"><?= $totalPosts ?></span></a>
          <a href="/admin/comments.php">💬 Comments</a>
          <a href="/admin/source-categories.php">📥 Download Categories</a>
          <a href="/admin/source-items.php">📥 Download Items</a>
        </div>
      </div>

      <div class="section-card" data-reveal="up" data-reveal-delay="0.6">
        <h3><span>⚙️</span> System</h3>
        <div class="links">
          <a href="/admin/analytics.php">📈 Analytics</a>
          <a href="/admin/settings.php">🔧 Site Settings</a>
          <a href="/api/diagnose.php">🔍 System Diagnose</a>
          <a href="/" style="color: var(--primary);">🏠 Back to Site</a>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Mobile sidebar toggle overlay -->
<script>
(function() {
  const sidebar = document.getElementById('adminSidebar');
  // Add mobile toggle button to nav if needed
  const navToggle = document.querySelector('.nav-toggle');
  if (navToggle && sidebar) {
    navToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
})();
</script>

<script src="/assets/js/animations.js?v=2"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>