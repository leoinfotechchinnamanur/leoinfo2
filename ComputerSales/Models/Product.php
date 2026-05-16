<?php
// ComputerSales/Models/Product.php
// Product CRUD with price history audit trail

namespace ComputerSales\Models;

use ComputerSales\Core\{Database, Security};

class Product {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Find product by ID with brand/category info
    public function findById(int $id): ?array {
        $sql = "SELECT p.*, b.name as brand_name, c.name as category_name 
                FROM cs_products p
                LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
                LEFT JOIN cs_categories c ON p.category_id = c.category_id
                WHERE p.product_id = ? AND p.is_active = 1";
        return $this->db->fetch($sql, [$id]);
    }

    // Find by slug (for SEO URLs)
    public function findBySlug(string $slug): ?array {
        $sql = "SELECT p.*, b.name as brand_name, c.name as category_name,
                       b.logo_url as brand_logo
                FROM cs_products p
                LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
                LEFT JOIN cs_categories c ON p.category_id = c.category_id
                WHERE p.slug = ? AND p.is_active = 1";
        return $this->db->fetch($sql, [$slug]);
    }

    // Get all products with filtering, pagination, sorting
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ["p.is_active = 1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "p.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['category_slug'])) {
            $where[] = "c.slug = ?";
            $params[] = Security::sanitize('slug', $filters['category_slug']);
        }

        if (!empty($filters['brand_id'])) {
            $where[] = "p.brand_id = ?";
            $params[] = (int)$filters['brand_id'];
        }

        if (!empty($filters['brand_slug'])) {
            $where[] = "b.slug = ?";
            $params[] = Security::sanitize('slug', $filters['brand_slug']);
        }

        if (!empty($filters['condition'])) {
            $where[] = "p.condition_type = ?";
            $params[] = $filters['condition'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . Security::sanitize('string', $filters['search']) . '%';
            $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = "p.current_price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = "p.current_price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['in_stock'])) {
            $where[] = "p.stock_quantity > 0";
        }

        if (!empty($filters['featured'])) {
            $where[] = "p.is_featured = 1";
        }

        $whereClause = implode(' AND ', $where);

        // Sorting
        $sortField = in_array($filters['sort'] ?? '', ['name', 'current_price', 'created_at', 'stock_quantity']) 
            ? $filters['sort'] : 'p.is_featured DESC, p.created_at';
        $sortDir = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        // Count total
        $countSql = "SELECT COUNT(*) FROM cs_products p 
                     LEFT JOIN cs_brands b ON p.brand_id = b.brand_id 
                     LEFT JOIN cs_categories c ON p.category_id = c.category_id 
                     WHERE {$whereClause}";
        $total = (int)$this->db->query($countSql, $params)->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, b.name as brand_name, b.slug as brand_slug,
                       c.name as category_name, c.slug as category_slug,
                       (SELECT image_url FROM cs_product_images 
                        WHERE product_id = p.product_id AND is_primary = 1 
                        LIMIT 1) as primary_image,
                       (SELECT COUNT(*) FROM cs_product_images 
                        WHERE product_id = p.product_id) as image_count
                FROM cs_products p
                LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
                LEFT JOIN cs_categories c ON p.category_id = c.category_id
                WHERE {$whereClause}
                ORDER BY {$sortField} {$sortDir}
                LIMIT {$perPage} OFFSET {$offset}";

        $products = $this->db->fetchAll($sql, $params);

        return [
            'data' => $products,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    // Create new product
    public function create(array $data): int {
        $slug = $this->generateSlug($data['name'] ?? 'product');

        $insertData = [
            'sku' => Security::sanitize('string', $data['sku'] ?? ''),
            'slug' => $slug,
            'name' => Security::sanitize('string', $data['name'] ?? ''),
            'brand_id' => !empty($data['brand_id']) ? (int)$data['brand_id'] : null,
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'description' => Security::cleanHtml($data['description'] ?? null),
            'short_description' => Security::sanitize('string', $data['short_description'] ?? null),
            'specifications' => !empty($data['specifications']) ? json_encode($data['specifications']) : null,
            'mrp' => (float)($data['mrp'] ?? 0),
            'current_price' => (float)($data['current_price'] ?? 0),
            'cost_price' => (float)($data['cost_price'] ?? 0),
            'stock_quantity' => (int)($data['stock_quantity'] ?? 0),
            'low_stock_threshold' => (int)($data['low_stock_threshold'] ?? 5),
            'warranty_months' => (int)($data['warranty_months'] ?? 12),
            'condition_type' => in_array($data['condition_type'] ?? '', ['new', 'refurbished', 'open_box', 'used']) 
                ? $data['condition_type'] : 'new',
            'is_featured' => !empty($data['is_featured']) ? 1 : 0,
            'meta_title' => Security::sanitize('string', $data['meta_title'] ?? null),
            'meta_description' => Security::sanitize('string', $data['meta_description'] ?? null),
            'created_by' => $_SESSION['user_id'] ?? null
        ];

        return $this->db->insert('cs_products', $insertData);
    }

    // Update product with price history tracking
    public function update(int $id, array $data): bool {
        $current = $this->findById($id);
        if (!$current) return false;

        $updateData = [];

        // Build update data safely
        $fields = ['name', 'sku', 'brand_id', 'category_id', 'description', 'short_description',
                  'stock_quantity', 'low_stock_threshold', 'warranty_months', 'condition_type',
                  'is_featured', 'is_active', 'meta_title', 'meta_description'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = is_string($data[$field]) ? Security::sanitize('string', $data[$field]) : $data[$field];
            }
        }

        // Handle specifications JSON
        if (isset($data['specifications'])) {
            $updateData['specifications'] = is_string($data['specifications']) ? $data['specifications'] : json_encode($data['specifications']);
        }

        // Track price changes
        $oldMrp = (float)$current['mrp'];
        $oldPrice = (float)$current['current_price'];
        $newMrp = isset($data['mrp']) ? (float)$data['mrp'] : $oldMrp;
        $newPrice = isset($data['current_price']) ? (float)$data['current_price'] : $oldPrice;
        $newCost = isset($data['cost_price']) ? (float)$data['cost_price'] : (float)$current['cost_price'];

        if (isset($data['mrp'])) $updateData['mrp'] = $newMrp;
        if (isset($data['current_price'])) $updateData['current_price'] = $newPrice;
        if (isset($data['cost_price'])) $updateData['cost_price'] = $newCost;

        // Log price history if changed
        if ($oldMrp != $newMrp || $oldPrice != $newPrice) {
            $this->db->insert('cs_price_history', [
                'product_id' => $id,
                'old_mrp' => $oldMrp,
                'new_mrp' => $newMrp,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'change_reason' => Security::sanitize('string', $data['price_reason'] ?? 'Manual update'),
                'changed_by' => $_SESSION['user_id'] ?? 'system'
            ]);
        }

        $updateData['updated_by'] = $_SESSION['user_id'] ?? null;
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->db->update('cs_products', $updateData, 'product_id = ?', [$id]) > 0;
    }

    // Soft delete
    public function delete(int $id): bool {
        return $this->db->update('cs_products', 
            ['is_active' => 0, 'updated_by' => $_SESSION['user_id'] ?? null], 
            'product_id = ?', [$id]
        ) > 0;
    }

    // Get price history
    public function getPriceHistory(int $productId): array {
        $sql = "SELECT h.*, u.name as changed_by_name
                FROM cs_price_history h
                LEFT JOIN users u ON h.changed_by = u.user_id
                WHERE h.product_id = ?
                ORDER BY h.created_at DESC";
        return $this->db->fetchAll($sql, [$productId]);
    }

    // Get product images
    public function getImages(int $productId): array {
        return $this->db->fetchAll(
            "SELECT * FROM cs_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, created_at",
            [$productId]
        );
    }

    // Add product image
    public function addImage(int $productId, string $imageUrl, ?string $thumbnailUrl = null, bool $isPrimary = false, string $altText = ''): int {
        if ($isPrimary) {
            $this->db->query(
                "UPDATE cs_product_images SET is_primary = 0 WHERE product_id = ?",
                [$productId]
            );
        }
        return $this->db->insert('cs_product_images', [
            'product_id' => $productId,
            'image_url' => Security::sanitize('url', $imageUrl),
            'thumbnail_url' => $thumbnailUrl ? Security::sanitize('url', $thumbnailUrl) : null,
            'alt_text' => Security::sanitize('string', $altText),
            'is_primary' => $isPrimary ? 1 : 0
        ]);
    }

    // Update stock
    public function updateStock(int $productId, int $quantity, string $reason = 'manual'): bool {
        try {
            $this->db->beginTransaction();

            $this->db->query(
                "UPDATE cs_products SET stock_quantity = stock_quantity + ? WHERE product_id = ?",
                [$quantity, $productId]
            );

            $this->db->insert('cs_inventory_movements', [
                'product_id' => $productId,
                'movement_type' => $quantity > 0 ? 'purchase' : 'adjustment',
                'reference_type' => 'manual',
                'quantity' => $quantity,
                'notes' => Security::sanitize('string', $reason),
                'created_by' => $_SESSION['user_id'] ?? 'system'
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Stock update failed: " . $e->getMessage());
            return false;
        }
    }

    // Get related products
    public function getRelated(int $productId, int $limit = 4): array {
        $product = $this->findById($productId);
        if (!$product) return [];

        $sql = "SELECT p.*, b.name as brand_name,
                       (SELECT image_url FROM cs_product_images 
                        WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image
                FROM cs_products p
                LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
                WHERE p.product_id != ? 
                  AND p.is_active = 1 
                  AND (p.category_id = ? OR p.brand_id = ?)
                  AND p.stock_quantity > 0
                ORDER BY p.is_featured DESC, RAND()
                LIMIT ?";
        return $this->db->fetchAll($sql, [$productId, $product['category_id'], $product['brand_id'], $limit]);
    }

    private function generateSlug(string $name): string {
        $base = Security::slugify($name);
        $slug = $base;
        $counter = 1;

        while ($this->db->fetch("SELECT 1 FROM cs_products WHERE slug = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }
}