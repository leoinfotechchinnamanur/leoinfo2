// ComputerSales/Assets/js/app.js
import { Cart } from './cart.js';
import { InvoiceBuilder } from './invoice.js';
import { API } from './api.js';

class ComputerSalesApp {
    constructor() {
        this.api = new API('/ComputerSales/API/');
        this.cart = new Cart(this.api);
        this.invoice = new InvoiceBuilder(this.api);
        this.userId = document.body.dataset.userId;
        
        this.init();
    }
    
    async init() {
        // Verify auth status
        const auth = await this.api.get('auth/check.php');
        if (!auth.authenticated) {
            window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(location.pathname);
            return;
        }
        
        this.loadFeaturedProducts();
        this.loadCategories();
        this.setupEventListeners();
        this.updateCartBadge();
    }
    
    async loadFeaturedProducts() {
        try {
            const response = await this.api.get('products.php?featured=1&limit=6');
            const grid = document.getElementById('featured-products');
            
            grid.innerHTML = response.data.map(product => `
                <article class="cs-product-card" data-id="${this.escapeHtml(product.product_id)}">
                    <div class="cs-product-image">
                        <img src="${this.escapeHtml(product.primary_image || '/assets/no-image.jpg')}" 
                             alt="${this.escapeHtml(product.name)}" loading="lazy">
                    </div>
                    <div class="cs-product-info">
                        <h3>${this.escapeHtml(product.name)}</h3>
                        <p class="cs-brand">${this.escapeHtml(product.brand_name || '')}</p>
                        <div class="cs-price-row">
                            <span class="cs-mrp">₹${this.formatPrice(product.mrp)}</span>
                            <span class="cs-price">₹${this.formatPrice(product.current_price)}</span>
                        </div>
                        <button class="cs-btn cs-btn-add" data-action="add-to-cart" 
                                data-id="${product.product_id}">
                            🛒 Add to Cart
                        </button>
                    </div>
                </article>
            `).join('');
            
        } catch (error) {
            console.error('Failed to load products:', error);
        }
    }
    
    async loadCategories() {
        const response = await this.api.get('categories.php?parent=0');
        const container = document.getElementById('category-list');
        
        container.innerHTML = response.data.map(cat => `
            <a href="/ComputerSales/category/${this.escapeHtml(cat.slug)}/" class="cs-category-card">
                <span class="cs-category-icon">📦</span>
                <span>${this.escapeHtml(cat.name)}</span>
            </a>
        `).join('');
    }
    
    setupEventListeners() {
        // Delegate clicks for dynamic content
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            
            const action = btn.dataset.action;
            const id = btn.dataset.id;
            
            switch(action) {
                case 'add-to-cart':
                    this.cart.addItem(id, 1);
                    break;
                case 'remove-from-cart':
                    this.cart.removeItem(id);
                    break;
                case 'quick-invoice':
                    this.invoice.quickCreate(id);
                    break;
            }
        });
    }
    
    updateCartBadge() {
        const count = this.cart.getItemCount();
        document.getElementById('cart-count').textContent = count;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatPrice(price) {
        return new Intl.NumberFormat('en-IN').format(price);
    }
}

// Initialize when DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new ComputerSalesApp());
} else {
    new ComputerSalesApp();
}