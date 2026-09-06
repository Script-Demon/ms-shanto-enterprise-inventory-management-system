<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, c.name AS customer_name, c.phone AS customer_phone FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash_set(t('flash_invoice_not_found'), 'danger');
    header('Location: ' . url('invoices/list.php'));
    exit;
}

$itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date, id");
$paymentsStmt->execute([$id]);
$payments = $paymentsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void') {
    verify_csrf();
    if (!$invoice['voided']) {
        $pdo->beginTransaction();
        foreach ($items as $it) {
            if ($it['product_id']) {
                $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$it['quantity'], $it['product_id']]);
            }
        }
        $pdo->prepare("UPDATE invoices SET voided = 1 WHERE id = ?")->execute([$id]);
        $pdo->commit();
        flash_set(t('flash_invoice_voided'));
        header('Location: ' . url('invoices/view.php?id=' . $id));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><?= e(t('inv_view_title', $invoice['invoice_no'])) ?> <?php if ($invoice['voided']): ?><span class="badge bg-secondary"><?= e(t('inv_voided_badge')) ?></span><?php endif; ?></h4>
  <div>
    <a href="<?= url('invoices/print.php?id=' . $id . '&auto=1') ?>" class="btn btn-outline-secondary"><?= e(t('inv_print_button')) ?></a>
    <?php if (!$invoice['voided'] && $invoice['due_amount'] > 0): ?>
      <a href="<?= url('invoices/payment.php?id=' . $id) ?>" class="btn btn-success"><?= e(t('inv_record_payment_button')) ?></a>
    <?php endif; ?>
    <?php if (!$invoice['voided']): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('<?= e(t('inv_confirm_void')) ?>');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="void">
      <button class="btn btn-outline-danger"><?= e(t('inv_void_button')) ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
<div class="card p-3 mb-3">
  <div class="row">
    <div class="col-md-6">
      <strong><?= e(t('inv_label_customer_colon')) ?></strong> <?= e($invoice['customer_name'] ?? $invoice['walkin_name'] ?? t('common_walkin')) ?><br>
      <?php $phone = $invoice['customer_phone'] ?? $invoice['walkin_phone'] ?? ''; if ($phone): ?><strong><?= e(t('inv_label_phone_colon')) ?></strong> <?= e($phone) ?><br><?php endif; ?>
    </div>
    <div class="col-md-6 text-md-end">
      <strong><?= e(t('inv_label_date_colon')) ?></strong> <?= e($invoice['invoice_date']) ?><br>
      <strong><?= e(t('inv_label_status_colon')) ?></strong> <span class="badge bg-<?= $invoice['status']==='paid'?'success':($invoice['status']==='partial'?'warning':'danger') ?>"><?= e(t('status_' . $invoice['status'])) ?></span>
    </div>
  </div>
</div>
<div class="table-responsive">
<table class="table table-bordered bg-white">
  <thead><tr><th><?= e(t('common_product')) ?></th><th><?= e(t('common_qty')) ?></th><th><?= e(t('common_unit_price')) ?></th><th><?= e(t('common_line_total')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr><td><?= e($it['product_name']) ?></td><td data-label="<?= e(t('common_qty')) ?>"><?= qty($it['quantity']) ?> <?= e($it['unit']) ?></td><td data-label="<?= e(t('common_unit_price')) ?>"><?= money($it['unit_price']) ?></td><td data-label="<?= e(t('common_line_total')) ?>"><?= money($it['line_total']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<div class="row justify-content-end">
  <div class="col-md-4">
    <div class="d-flex justify-content-between"><span><?= e(t('common_subtotal')) ?></span><span><?= money($invoice['subtotal']) ?></span></div>
    <div class="d-flex justify-content-between"><span><?= e(t('common_discount')) ?></span><span><?= money($invoice['discount']) ?></span></div>
    <div class="d-flex justify-content-between fs-5"><strong><?= e(t('common_total')) ?></strong><strong><?= money($invoice['total']) ?></strong></div>
    <div class="d-flex justify-content-between"><span><?= e(t('common_paid')) ?></span><span><?= money($invoice['paid_amount']) ?></span></div>
    <div class="d-flex justify-content-between text-danger"><strong><?= e(t('common_due')) ?></strong><strong><?= money($invoice['due_amount']) ?></strong></div>
  </div>
</div>
<?php if ($payments): ?>
<h5 class="mt-4"><?= e(t('inv_payment_history_title')) ?></h5>
<table class="table table-sm bg-white">
  <thead><tr><th><?= e(t('common_date')) ?></th><th><?= e(t('common_amount')) ?></th><th><?= e(t('common_note')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($payments as $p): ?>
    <tr><td><?= e($p['payment_date']) ?></td><td data-label="<?= e(t('common_amount')) ?>"><?= money($p['amount']) ?></td><td data-label="<?= e(t('common_note')) ?>"><?= e($p['note']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
