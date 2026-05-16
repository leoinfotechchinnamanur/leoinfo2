<?php
// ComputerSales/product.php - Single Product View
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';

use ComputerSales\Models\Product;
use ComputerSales\Core\{AuthGuard, Security};

// Authentication required
AuthGuard::requireLogin();
$currentUser = AuthGuard::getUser();

// Get product slug
$slug = Security::sanitize('slug', $_GET['slug'] ?? '');
if (empty($slug)) {
    header('Location: /ComputerSales/');
    exit;
}

// Load product
$productModel = new Product();
$product = $productModel->findBySlug($slug);

if (!$product) {
    header('Location: /ComputerSales/?error=product_not_found');
    exit;
}

// Load product images
$images = $productModel->getImages($product['product_id']);
$primaryImage = null;
$galleryImages = [];

foreach ($images as $img) {
    if ($img['is_primary']) {
        $primaryImage = $img;
    } else {
        $galleryImages[] = $img;
    }
}

// Load related products
$relatedProducts = $productModel->getRelated($product['product_id'], 4);

// Parse specifications
$specifications = [];
if (!empty($product['specifications'])) {
    $specs = json_decode($product['specifications'], true);
    if (is_array($specs)) {
        $specifications = $specs;
    }
}

// Calculate discount percentage
$discountPercent = 0;
if ($product['mrp'] > $product['current_price'] && $product['mrp'] > 0) {
    $discountPercent = round((($product['mrp'] - $product['current_price']) / $product['mrp']) * 100);
}

// Page title
$pageTitle = $product['name'] . ' - ' . ($product['brand_name'] ?? 'Computer Sales');
$metaDescription = $product['short_description'] ?? substr(strip_tags($product['description'] ?? ''), 0, 160);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Security::e($pageTitle) ?> - AkkuApps</title>
    <meta name="description" content="<?= Security::e($metaDescription) ?>">
    <link rel="stylesheet" href="/ComputerSales/Assets/css/computersales.css">
    <style>
        .cs-product-single {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }
        
        .cs-product-gallery {
            position: sticky;
            top: 80px;
            height: fit-content;
        }
        
        .cs-main-image {
            background: #15151d;
            border-radius: 16px;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            overflow: hidden;
        }
        
        .cs-main-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .cs-thumbnail-list {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        .cs-thumbnail {
            aspect-ratio: 1;
            background: #15151d;
            border: 2px solid var(--cs-border);
            border-radius: 12px;
            cursor: pointer;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        
        .cs-thumbnail:hover,
        .cs-thumbnail.active {
            border-color: var(--cs-accent);
        }
        
        .cs-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cs-product-details h1 {
            font-size: 32px;
            color: var(--cs-bright);
            margin-bottom: 8px;
        }
        
        .cs-brand-link {
            color: var(--cs-accent);
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .cs-price-box {
            background: var(--cs-card);
            border: 1px solid var(--cs-border);
            border-radius: 16px;
            padding: 24px;
            margin: 24px 0;
        }
        
        .cs-price-main {
            font-size: 36px;
            font-weight: 800;
            color: var(--cs-success);
            margin-bottom: 8px;
        }
        
        .cs-price-mrp {
            text-decoration: line-through;
            opacity: 0.6;
            margin-right: 16px;
        }
        
        .cs-discount-badge {
            background: var(--cs-success);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .cs-stock-status {
            margin-top: 16px;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .cs-in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--cs-success);
        }
        
        .cs-low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--cs-warning);
        }
        
        .cs-out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--cs-danger);
        }
        
        .cs-action-buttons {
            display: flex;
            gap: 12px;
            margin: 24px 0;
        }
        
        .cs-qty-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .cs-qty-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--cs-border);
            background: var(--cs-card);
            color: var(--cs-bright);
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cs-qty-btn:hover {
            border-color: var(--cs-accent);
        }
        
        .cs-qty-input {
            width: 60px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }
        
        .cs-specs-table {
            margin-top: 40px;
        }
        
        .cs-specs-table h2 {
            color: var(--cs-bright);
            margin-bottom: 16px;
        }
        
        .cs-spec-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 16px;
            border-bottom: 1px solid var(--cs-border);
        }
        
        .cs-spec-row:nth-child(even) {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .cs-spec-label {
            font-weight: 600;
            color: var(--cs-text);
        }
        
        .cs-spec-value {
            color: var(--cs-bright);
        }
        
        .cs-related-products {
            margin-top: 60px;
        }
        
        .cs-related-products h2 {
            color: var(--cs-bright);
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            .cs-product-single {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .cs-product-gallery {
                position: static;
            }
            
            .cs-spec-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body data-user-id="<?= Security::e($currentUser['user_id']) ?>">
    <?php require __DIR__ . '/includes/header.php'; ?>
    
    <div class="cs-container">
        <div class="cs-product-single">
            <!-- Image Gallery -->
            <div class="cs-product-gallery">
                <div class="cs-main-image" id="mainImage">
                    <img src="<?= Security::e($primaryImage['image_url'] ?? '/assets/no-image.jpg') ?>" 
                         alt="<?= Security::e($product['name']) ?>"
                         id="mainImageElement">
                </div>
                
                <?php if (count($images) > 1): ?>
                <div class="cs-thumbnail-list">
                    <?php foreach ($images as $img): ?>
                    <div class="cs-thumbnail <?= $img['is_primary'] ? 'active' : '' ?>" 
                         data-image="<?= Security::e($img['image_url']) ?>">
                        <img src="<?= Security::e($img['thumbnail_url'] ?? $img['image_url']) ?>" 
                             alt="<?= Security::e($img['alt_text'] ?? $product['name']) ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Details -->
            <div class="cs-product-details">
                <h1><?= Security::e($product['name']) ?></h1>
                
                <?php if (!empty($product['brand_name'])): ?>
                <a href="/ComputerSales/brand/<?= Security::e($product['brand_slug'] ?? '') ?>/" class="cs-brand-link">
                    <?= Security::e($product['brand_name']) ?>
                </a>
                <?php endif; ?>
                
                <?php if (!empty($product['short_description'])): ?>
                <p style="color: var(--cs-text); line-height: 1.6; margin: 16px 0;">
                    <?= Security::e($product['short_description']) ?>
                </p>
                <?php endif; ?>
                
                <!-- Price Box -->
                <div class="cs-price-box">
                    <div class="cs-price-main">
                        ₹<?= number_format($product['current_price'], 2) ?>
                    </div>
                    
                    <?php if ($product['mrp'] > $product['current_price']): ?>
                    <div>
                        <span class="cs-price-mrp">₹<?= number_format($product['mrp'], 2) ?></span>
                        <?php if ($discountPercent > 0): ?>
                        <span class="cs-discount-badge"><?= $discountPercent ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Stock Status -->
                    <?php
                    $stockClass = 'cs-out-of-stock';
                    $stockText = 'Out of Stock';
                    if ($product['stock_quantity'] > $product['low_stock_threshold']) {
                        $stockClass = 'cs-in-stock';
                        $stockText = 'In Stock (' . $product['stock_quantity'] . ' available)';
                    } elseif ($product['stock_quantity'] > 0) {
                        $stockClass = 'cs-low-stock';
                        $stockText = 'Low Stock (' . $product['stock_quantity'] . ' left)';
                    }
                    ?>
                    <div class="cs-stock-status <?= $stockClass ?>">
                        <?= $stockText ?>
                    </div>
                </div>
                
                <?php if ($product['stock_quantity'] > 0): ?>
                <!-- Quantity Selector -->
                <div class="cs-qty-selector">
                    <span style="color: var(--cs-text); font-weight: 600;">Quantity:</span>
                    <button class="cs-qty-btn" id="qtyMinus">−</button>
                    <input type="number" id="quantity" class="cs-invoice-input cs-qty-input" 
                           value="1" min="1" max="<?= $product['stock_quantity'] ?>">
                    <button class="cs-qty-btn" id="qtyPlus">+</button>
                </div>
                
                <!-- Action Buttons -->
                <div class="cs-action-buttons">
                    <button class="cs-btn cs-btn-primary" id="addToCart" 
                            data-product-id="<?= $product['product_id'] ?>" style="flex: 1;">
                        🛒 Add to Cart
                    </button>
                    <button class="cs-btn" id="quickInvoice" 
                            data-product-id="<?= $product['product_id'] ?>">
                        ⚡ Quick Invoice
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Product Info -->
                <?php if (!empty($product['condition_type'])): ?>
                <div style="margin: 16px 0; padding: 12px; background: rgba(99, 102, 241, 0.1); border-radius: 8px;">
                    <strong style="color: var(--cs-accent);">Condition:</strong> 
                    <span style="color: var(--cs-bright);"><?= ucfirst($product['condition_type']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($product['warranty_months'] > 0): ?>
                <div style="margin: 16px 0; color: var(--cs-text);">
                    ✅ <strong><?= $product['warranty_months'] ?> months warranty</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Full Description -->
        <?php if (!empty($product['description'])): ?>
        <div style="margin-top: 40px; padding: 24px; background: var(--cs-card); border: 1px solid var(--cs-border); border-radius: 16px;">
            <h2 style="color: var(--cs-bright); margin-bottom: 16px;">Description</h2>
            <div style="color: var(--cs-text); line-height: 1.8;">
                <?= $product['description'] ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Specifications -->
        <?php if (!empty($specifications)): ?>
        <div class="cs-specs-table">
            <h2>Specifications</h2>
            <div style="border: 1px solid var(--cs-border); border-radius: 16px; overflow: hidden;">
                <?php foreach ($specifications as $key => $value): ?>
                <div class="cs-spec-row">
                    <div class="cs-spec-label"><?= Security::e($key) ?></div>
                    <div class="cs-spec-value"><?= Security::e($value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="cs-related-products">
            <h2>Related Products</h2>
            <div class="cs-product-grid">
                <?php foreach ($relatedProducts as $related): ?>
                <article class="cs-product-card">
                    <a href="/ComputerSales/product.php?slug=<?= Security::e($related['slug']) ?>">
                        <div class="cs-product-image">
                            <img src="<?= Security::e($related['primary_image'] ?? '/assets/no-image.jpg') ?>" 
                                 alt="<?= Security::e($related['name']) ?>">
                        </div>
                        <div class="cs-product-info">
                            <h3><?= Security::e($related['name']) ?></h3>
                            <?php if (!empty($related['brand_name'])): ?>
                            <p class="cs-brand"><?= Security::e($related['brand_name']) ?></p>
                            <?php endif; ?>
                            <div class="cs-price-row">
                                <?php if ($related['mrp'] > $related['current_price']): ?>
                                <span class="cs-mrp">₹<?= number_format($related['mrp'], 2) ?></span>
                                <?php endif; ?>
                                <span class="cs-price">₹<?= number_format($related['current_price'], 2) ?></span>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions FAB -->
    <div class="cs-quick-actions">
        <a href="/ComputerSales/cart.php" class="cs-fab" title="View Cart">
            🛒
            <span class="cs-badge" id="cart-count">0</span>
        </a>
    </div>
    
    <script type="module">
        import { API } from '/ComputerSales/Assets/js/api.js';
        
        const api = new API('/ComputerSales/API/');
        const productId = <?= (int)$product['product_id'] ?>;
        const maxQty = <?= (int)$product['stock_quantity'] ?>;
        
        // Quantity controls
        const qtyInput = document.getElementById('quantity');
        const qtyMinus = document.getElementById('qtyMinus');
        const qtyPlus = document.getElementById('qtyPlus');
        
        if (qtyMinus) {
            qtyMinus.addEventListener('click', () => {
                const current = parseInt(qtyInput.value) || 1;
                if (current > 1) qtyInput.value = current - 1;
            });
        }
        
        if (qtyPlus) {
            qtyPlus.addEventListener('click', () => {
                const current = parseInt(qtyInput.value) || 1;
                if (current < maxQty) qtyInput.value = current + 1;
            });
        }
        
        // Image gallery
        document.querySelectorAll('.cs-thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                const imageUrl = this.dataset.image;
                document.getElementById('mainImageElement').src = imageUrl;
                
                document.querySelectorAll('.cs-thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Add to cart
        const addToCartBtn = document.getElementById('addToCart');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', async () => {
                const qty = parseInt(qtyInput.value) || 1;
                try {
                    const result = await api.post('cart.php', {
                        action: 'add',
                        product_id: productId,
                        quantity: qty
                    });
                    
                    if (result.success) {
                        document.getElementById('cart-count').textContent = result.cart_count;
                        alert('Added to cart!');
                    } else {
                        alert('Error: ' + (result.error || 'Failed to add to cart'));
                    }
                } catch (error) {
                    console.error('Cart error:', error);
                    alert('Failed to add to cart');
                }
            });
        }
        
        // Quick invoice
        const quickInvoiceBtn = document.getElementById('quickInvoice');
        if (quickInvoiceBtn) {
            quickInvoiceBtn.addEventListener('click', () => {
                const qty = parseInt(qtyInput.value) || 1;
                window.location.href = `/ComputerSales/invoice-create.php?product=${productId}&qty=${qty}`;
            });
        }
        
        // Load cart count
        async function loadCartCount() {
            try {
                const result = await api.get('cart.php?action=count');
                if (result.count !== undefined) {
                    document.getElementById('cart-count').textContent = result.count;
                }
            } catch (error) {
                console.error('Failed to load cart count:', error);
            }
        }
        loadCartCount();
    </script>
</body>
</html>