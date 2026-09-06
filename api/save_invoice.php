<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => t('api_error_invalid_form')]);
    exit;
}

$items = $input['items'] ?? [];
if (!$items || !is_array($items)) {
    http_response_code(422);
    echo json_encode(['error' => t('api_error_items_required')]);
    exit;
}

$customerId = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
$walkinName = trim($input['walkin_name'] ?? '');
$walkinPhone = trim($input['walkin_phone'] ?? '');
$discount = max((float)($input['discount'] ?? 0), 0);
$paidAmountInput = max((float)($input['paid_amount'] ?? 0), 0);
$invoiceDate = $input['invoice_date'] ?? date('Y-m-d');

// The walk-in fields come from a JSON body, so they bypass the form maxlength.
$walkinTooLong = first_error(
    too_long('invoices.walkin_name', $walkinName, t('inv_walkin_name_placeholder')),
    too_long('invoices.walkin_phone', $walkinPhone, t('inv_walkin_phone_placeholder'))
);
if ($walkinTooLong !== null) {
    http_response_code(422);
    echo json_encode(['error' => $walkinTooLong]);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
    $invoiceDate = date('Y-m-d');
}

try {
    $pdo->beginTransaction();

    $subtotal = 0;
    $preparedItems = [];
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qtyVal = (float)($item['quantity'] ?? 0);
        $unitPrice = max((float)($item['unit_price'] ?? 0), 0);

        if ($productId <= 0 || $qtyVal <= 0) {
            throw new Exception(t('api_error_invalid_line'));
        }

        // Lock the product row so concurrent sales cannot oversell the same stock.
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new Exception(t('api_error_product_missing'));
        }
        if ($qtyVal > (float)$product['stock_qty']) {
            throw new Exception(t('api_error_insufficient_stock', $product['name'], qty($product['stock_qty'])));
        }

        $lineTotal = round($qtyVal * $unitPrice, 2);
        $subtotal += $lineTotal;
        $preparedItems[] = [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'unit' => $product['unit'],
            'unit_price' => $unitPrice,
            'cost_price_snapshot' => $product['cost_price'],
            'quantity' => $qtyVal,
            'line_total' => $lineTotal,
        ];
    }

    $discount = min($discount, $subtotal);
    $total = round($subtotal - $discount, 2);
    $paidAmount = min($paidAmountInput, $total);
    $due = round($total - $paidAmount, 2);
    $status = $due <= 0.004 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');

    $stmt = $pdo->prepare("INSERT INTO invoices (customer_id, walkin_name, walkin_phone, invoice_date, subtotal, discount, total, paid_amount, due_amount, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$customerId, $walkinName ?: null, $walkinPhone ?: null, $invoiceDate, $subtotal, $discount, $total, $paidAmount, max($due, 0), $status]);
    $invoiceId = (int)$pdo->lastInsertId();

    $invoiceNo = generate_invoice_no($invoiceId);
    $pdo->prepare("UPDATE invoices SET invoice_no = ? WHERE id = ?")->execute([$invoiceNo, $invoiceId]);

    $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_id, product_name, unit, unit_price, cost_price_snapshot, quantity, line_total) VALUES (?,?,?,?,?,?,?,?)");
    $stockStmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
    foreach ($preparedItems as $it) {
        $itemStmt->execute([$invoiceId, $it['product_id'], $it['product_name'], $it['unit'], $it['unit_price'], $it['cost_price_snapshot'], $it['quantity'], $it['line_total']]);
        $stockStmt->execute([$it['quantity'], $it['product_id']]);
    }

    if ($paidAmount > 0) {
        $pdo->prepare("INSERT INTO payments (invoice_id, customer_id, amount, payment_date, note) VALUES (?,?,?,?,?)")
            ->execute([$invoiceId, $customerId, $paidAmount, $invoiceDate, 'Payment at sale']);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}
