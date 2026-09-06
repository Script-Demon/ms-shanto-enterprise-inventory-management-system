<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('suppliers/list.php'));
    exit;
}
verify_csrf();

// Nothing references a supplier, so this is a single unguarded delete — unlike
// customers, whose rows are held by their invoices.
$del = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
$del->execute([(int)($_POST['id'] ?? 0)]);
$found = $del->rowCount() > 0;

flash_set($found ? t('flash_supplier_deleted') : t('flash_supplier_not_found'), $found ? 'success' : 'danger');
header('Location: ' . url('suppliers/list.php'));
exit;
