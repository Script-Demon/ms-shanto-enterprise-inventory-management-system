<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('transport/index.php'));
    exit;
}
verify_csrf();

$id = (int)($_POST['id'] ?? 0);

// Deleting a trip takes its payment history with it. The payments hold a
// foreign key to the entry, so they have to go first or the delete is refused;
// both run in one transaction so a trip can never be left half-deleted with
// orphaned payments still counting towards the totals.
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM transport_payments WHERE entry_id = ?")->execute([$id]);
    $del = $pdo->prepare("DELETE FROM transport_entries WHERE id = ?");
    $del->execute([$id]);
    $found = $del->rowCount() > 0;
    $pdo->commit();
} catch (Exception $ex) {
    $pdo->rollBack();
    throw $ex;
}

// Every figure in the module is summed from these two tables on each page load,
// so the period, monthly, yearly and outstanding totals correct themselves.
flash_set($found ? t('flash_transport_deleted') : t('trn_err_not_found'), $found ? 'success' : 'danger');

header('Location: ' . url('transport/index.php'));
exit;
