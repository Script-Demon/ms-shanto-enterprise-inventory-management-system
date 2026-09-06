<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

// Matches product name, SKU, or category name — the invoice screen tells the
// user they can search by name or category, so category has to be searchable.
$stmt = $pdo->prepare("SELECT p.id, p.name, p.sku, p.unit, p.sell_price, p.cost_price, p.stock_qty, p.image
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?
    ORDER BY p.name
    LIMIT 15");
$like = "%$q%";
$stmt->execute([$like, $like, $like]);
$products = $stmt->fetchAll();

// Hand the invoice screen a ready-to-use URL instead of a bare filename, so it
// needs to know nothing about base_path or where uploads live. Null whenever
// the product has no picture or the file has gone missing from disk.
foreach ($products as &$p) {
    $file = (string)$p['image'];
    $p['image_url'] = ($file !== '' && is_file(product_image_dir() . '/' . $file))
        ? url('assets/uploads/products/' . $file)
        : null;
    unset($p['image']);
}
unset($p);

echo json_encode($products);
