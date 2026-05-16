<?php
// ComputerSales/Models/Invoice.php
// Invoice generation, payment tracking, stock management

namespace ComputerSales\Models;

use ComputerSales\Core\{Database, Security};

class Invoice {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Generate unique invoice number
    public function generateNumber(): string {
        $prefix = 'CS-' . date('Ym') . '-';
        $sql = "SELECT COUNT(*) FROM cs_invoices WHERE invoice_number LIKE ?";
        $count = (int)$this->db->query($sql, [$prefix . '%'])->fetchColumn();
        return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // Create invoice with items
    public function create(array $invoiceData, array $items): array {
        try {
            $this->db->beginTransaction();

            $invoiceNumber = $this->generateNumber();
            $userId = $_SESSION['user_id'] ?? 'system';

            // Calculate all totals
            $subtotal = 0;
            $taxTotal = 0;
            $discountTotal = 0;
            $processedItems = [];

            foreach ($items as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $discountPct = min(100, max(0, (float)($item['discount_percent'] ?? 0)));
                $taxPct = (float)($item['tax_percent'] ?? 18.00);

                $lineTotal = $qty * $unitPrice;
                $lineDiscount = $lineTotal * ($discountPct / 100);
                $lineTaxable = $lineTotal - $lineDiscount;
                $lineTax = $lineTaxable * ($taxPct / 100);
                $lineFinal = $lineTaxable + $lineTax;

                $processedItems[] = [
                    'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    'description' => Security::sanitize('string', $item['description'] ?? 'Item'),
                    'specifications' => !empty($item['specifications']) ? Security::cleanHtml($item['specifications']) : null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPct,
                    'discount_amount' => $lineDiscount,
                    'tax_percent' => $taxPct,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineFinal,
                    'warranty_months' => (int)($item['warranty_months'] ?? 0),
                    'is_service' => !empty($item['is_service']) ? 1 : 0,
                    'sort_order' => (int)($item['sort_order'] ?? 0)
                ];

                $subtotal += $lineTotal;
                $discountTotal += $lineDiscount;
                $taxTotal += $lineTax;
            }

            $shipping = (float)($invoiceData['shipping_cost'] ?? 0);
            $grandTotal = $subtotal - $discountTotal + $taxTotal + $shipping;

            // Insert invoice
            $invoiceInsert = [
                'invoice_number' => $invoiceNumber,
                'customer_id' => (int)$invoiceData['customer_id'],
                'user_id' => $userId,
                'cart_id' => !empty($invoiceData['cart_id']) ? (int)$invoiceData['cart_id'] : null,
                'invoice_date' => $invoiceData['invoice_date'] ?? date('Y-m-d'),
                'due_date' => $invoiceData['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_rate' => (float)($invoiceData['tax_rate'] ?? 18.00),
                'tax_amount' => $taxTotal,
                'shipping_cost' => $shipping,
                'grand_total' => $grandTotal,
                'amount_due' => $grandTotal,
                'payment_status' => 'pending',
                'payment_method' => in_array($invoiceData['payment_method'] ?? '', ['cash', 'card', 'upi', 'bank_transfer', 'credit', 'mixed']) 
                    ? $invoiceData['payment_method'] : 'cash',
                'service_type' => in_array($invoiceData['service_type'] ?? '', ['sales', 'repair', 'upgrade', 'warranty']) 
                    ? $invoiceData['service_type'] : 'sales',
                'warranty_days' => (int)($invoiceData['warranty_days'] ?? 0),
                'notes' => Security::cleanHtml($invoiceData['notes'] ?? null),
                'terms_conditions' => Security::cleanHtml($invoiceData['terms_conditions'] ?? null),
                'created_by' => $userId
            ];

            $invoiceId = $this->db->insert('cs_invoices', $invoiceInsert);

            // Insert line items and update stock
            foreach ($processedItems as $item) {
                $item['invoice_id'] = $invoiceId;
                $this->db->insert('cs_invoice_items', $item);

                // Deduct stock for products
                if (!empty($item['product_id']) && empty($item['is_service'])) {
                    $this->db->query(
                        "UPDATE cs_products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE product_id = ?",
                        [$item['quantity'], $item['product_id']]
                    );

                    // Log inventory movement
                    $this->db->insert('cs_inventory_movements', [
                        'product_id' => $item['product_id'],
                        'movement_type' => 'sale',
                        'reference_type' => 'invoice',
                        'reference_id' => $invoiceId,
                        'quantity' => -$item['quantity'],
                        'unit_cost' => $item['unit_price'],
                        'notes' => 'Invoice: ' . $invoiceNumber,
                        'created_by' => $userId
                    ]);
                }
            }

            // Update cart status if applicable
            if (!empty($invoiceData['cart_id'])) {
                $this->db->query(
                    "UPDATE cs_carts SET status = 'converted' WHERE cart_id = ?",
                    [(int)$invoiceData['cart_id']]
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'grand_total' => $grandTotal,
                'amount_due' => $grandTotal
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Invoice creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Find invoice by ID with full details
    public function findById(int $id): ?array {
        $sql = "SELECT i.*, c.name as customer_name, c.phone as customer_phone,
                       c.gst_number, c.billing_address, c.shipping_address,
                       u.name as created_by_name
                FROM cs_invoices i
                JOIN cs_customers c ON i.customer_id = c.customer_id
                JOIN users u ON i.created_by = u.user_id
                WHERE i.invoice_id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    // Find by invoice number
    public function findByNumber(string $number): ?array {
        $sql = "SELECT i.*, c.name as customer_name, c.phone as customer_phone
                FROM cs_invoices i
                JOIN cs_customers c ON i.customer_id = c.customer_id
                WHERE i.invoice_number = ?";
        return $this->db->fetch($sql, [$number]);
    }

    // Get invoice items
    public function getItems(int $invoiceId): array {
        $sql = "SELECT ii.*, p.sku, p.name as product_name, p.stock_quantity as current_stock
                FROM cs_invoice_items ii
                LEFT JOIN cs_products p ON ii.product_id = p.product_id
                WHERE ii.invoice_id = ?
                ORDER BY ii.sort_order, ii.item_id";
        return $this->db->fetchAll($sql, [$invoiceId]);
    }

    // Record payment
    public function recordPayment(int $invoiceId, array $paymentData): array {
        try {
            $this->db->beginTransaction();

            $userId = $_SESSION['user_id'] ?? 'system';
            $amount = (float)$paymentData['amount'];

            if ($amount <= 0) {
                throw new \Exception("Payment amount must be greater than zero");
            }

            $this->db->insert('cs_payments', [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'payment_method' => in_array($paymentData['payment_method'] ?? '', ['cash', 'card', 'upi', 'bank_transfer', 'credit']) 
                    ? $paymentData['payment_method'] : 'cash',
                'transaction_reference' => Security::sanitize('string', $paymentData['transaction_reference'] ?? null),
                'upi_utr' => Security::sanitize('string', $paymentData['upi_utr'] ?? null),
                'notes' => Security::sanitize('string', $paymentData['notes'] ?? null),
                'received_by' => $userId
            ]);

            // Recalculate totals
            $sql = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM cs_payments WHERE invoice_id = ?";
            $totalPaid = (float)$this->db->query($sql, [$invoiceId])->fetchColumn();

            $invoice = $this->findById($invoiceId);
            if (!$invoice) {
                throw new \Exception("Invoice not found");
            }

            $grandTotal = (float)$invoice['grand_total'];
            $amountDue = max(0, $grandTotal - $totalPaid);

            $status = 'pending';
            if ($amountDue <= 0.01) {
                $status = 'paid';
            } elseif ($totalPaid > 0) {
                $status = 'partial';
            }

            $this->db->update('cs_invoices', [
                'amount_paid' => $totalPaid,
                'amount_due' => $amountDue,
                'payment_status' => $status,
                'updated_by' => $userId
            ], 'invoice_id = ?', [$invoiceId]);

            $this->db->commit();

            return [
                'success' => true,
                'amount_paid' => $totalPaid,
                'amount_due' => $amountDue,
                'status' => $status
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get all invoices with filtering
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where[] = "i.customer_id = ?";
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "i.payment_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['service_type'])) {
            $where[] = "i.service_type = ?";
            $params[] = $filters['service_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "i.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "i.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(i.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
            $search = '%' . Security::sanitize('string', $filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM cs_invoices i 
                     JOIN cs_customers c ON i.customer_id = c.customer_id 
                     WHERE {$whereClause}";
        $total = (int)$this->db->query($countSql, $params)->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT i.*, c.name as customer_name, c.phone as customer_phone,
                       u.name as created_by_name
                FROM cs_invoices i
                JOIN cs_customers c ON i.customer_id = c.customer_id
                JOIN users u ON i.created_by = u.user_id
                WHERE {$whereClause}
                ORDER BY i.invoice_date DESC, i.invoice_id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        return [
            'data' => $this->db->fetchAll($sql, $params),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    // Cancel invoice (reverse stock)
    public function cancel(int $invoiceId): bool {
        try {
            $this->db->beginTransaction();

            $invoice = $this->findById($invoiceId);
            if (!$invoice || $invoice['payment_status'] === 'cancelled') {
                return false;
            }

            // Return stock
            $items = $this->getItems($invoiceId);
            foreach ($items as $item) {
                if (!empty($item['product_id']) && empty($item['is_service'])) {
                    $this->db->query(
                        "UPDATE cs_products SET stock_quantity = stock_quantity + ? WHERE product_id = ?",
                        [$item['quantity'], $item['product_id']]
                    );

                    $this->db->insert('cs_inventory_movements', [
                        'product_id' => $item['product_id'],
                        'movement_type' => 'return',
                        'reference_type' => 'invoice',
                        'reference_id' => $invoiceId,
                        'quantity' => $item['quantity'],
                        'notes' => 'Invoice cancelled: ' . $invoice['invoice_number'],
                        'created_by' => $_SESSION['user_id'] ?? 'system'
                    ]);
                }
            }

            $this->db->update('cs_invoices', [
                'payment_status' => 'cancelled',
                'updated_by' => $_SESSION['user_id'] ?? null
            ], 'invoice_id = ?', [$invoiceId]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Invoice cancel failed: " . $e->getMessage());
            return false;
        }
    }
}