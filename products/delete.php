<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('products/list.php'));
    exit;
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);

// Read the picture first — after the row is gone its filename is unrecoverable
// and the file would sit in assets/uploads/products forever.
$find = $pdo->prepare("SELECT image FROM products WHERE id = ?");
$find->execute([$id]);
$image = $find->fetchColumn();

$stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    delete_uploaded_image(product_image_dir(), $image);
}

flash_set(t('flash_product_deleted'));
header('Location: ' . url('products/list.php'));
exit;
