<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash_set(t('flash_invoice_not_found'), 'danger');
    header('Location: ' . url('invoices/list.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($amount <= 0) {
        $error = t('inv_error_invalid_amount');
    } elseif ($amount > $invoice['due_amount'] + 0.01) {
        $error = t('inv_error_exceeds_due', money($invoice['due_amount']));
    } elseif (($noteErr = too_long('payments.note', $note, t('common_note'))) !== null) {
        $error = $noteErr;
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payments (invoice_id, customer_id, amount, payment_date, note) VALUES (?,?,?,?,?)")
            ->execute([$id, $invoice['customer_id'], $amount, date('Y-m-d'), $note ?: 'Due payment']);
        $newPaid = round($invoice['paid_amount'] + $amount, 2);
        $newDue = round($invoice['total'] - $newPaid, 2);
        $newStatus = $newDue <= 0.004 ? 'paid' : 'partial';
        $pdo->prepare("UPDATE invoices SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?")
            ->execute([$newPaid, max($newDue, 0), $newStatus, $id]);
        $pdo->commit();
        flash_set(t('flash_payment_recorded'));
        header('Location: ' . url('invoices/view.php?id=' . $id));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('inv_payment_title', $invoice['invoice_no'])) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="alert alert-info"><?= e(t('inv_current_due')) ?> <strong><?= money($invoice['due_amount']) ?></strong></div>
<form method="post" class="card p-4" style="max-width:400px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(t('inv_field_amount')) ?></label><input type="number" step="0.01" name="amount" class="form-control" max="<?= e($invoice['due_amount']) ?>" required></div>
  <div class="mb-3"><label class="form-label"><?= e(t('inv_field_note')) ?></label><input type="text" name="note" class="form-control" maxlength="<?= field_max('payments.note') ?>"></div>
  <div><button class="btn btn-primary"><?= e(t('inv_save_payment_button')) ?></button> <a href="<?= url('invoices/view.php?id=' . $id) ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
