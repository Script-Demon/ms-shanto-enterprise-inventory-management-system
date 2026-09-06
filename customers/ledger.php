<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash_set(t('flash_customer_not_found'), 'danger');
    header('Location: ' . url('customers/list.php'));
    exit;
}

$invStmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? AND voided = 0 ORDER BY invoice_date DESC, id DESC");
$invStmt->execute([$id]);
$invoices = $invStmt->fetchAll();

$totalDue = array_sum(array_column($invoices, 'due_amount'));

include __DIR__ . '/../includes/header.php';
?>
<h4><?= e(t('cust_ledger_title', $customer['name'])) ?></h4>
<p class="text-muted"><?= e($customer['phone']) ?> <?= $customer['address'] ? '— '.e($customer['address']) : '' ?></p>
<div class="alert alert-<?= $totalDue>0?'danger':'success' ?>"><?= e(t('cust_ledger_total_due')) ?> <strong><?= money($totalDue) ?></strong></div>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr><th><?= e(t('common_date')) ?></th><th><?= e(t('inv_th_invoice')) ?></th><th><?= e(t('common_total')) ?></th><th><?= e(t('common_paid')) ?></th><th><?= e(t('common_due')) ?></th><th><?= e(t('common_status')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><?= e($inv['invoice_date']) ?></td>
      <td data-label="<?= e(t('inv_th_invoice')) ?>"><a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>"><?= e($inv['invoice_no']) ?></a></td>
      <td data-label="<?= e(t('common_total')) ?>"><?= money($inv['total']) ?></td>
      <td data-label="<?= e(t('common_paid')) ?>"><?= money($inv['paid_amount']) ?></td>
      <td data-label="<?= e(t('common_due')) ?>" class="<?= $inv['due_amount']>0?'text-danger':'' ?>"><?= money($inv['due_amount']) ?></td>
      <td data-label="<?= e(t('common_status')) ?>"><span class="badge bg-<?= $inv['status']==='paid'?'success':($inv['status']==='partial'?'warning':'danger') ?>"><?= e(t('status_' . $inv['status'])) ?></span></td>
      <td><?php if ($inv['due_amount']>0): ?><a href="<?= url('invoices/payment.php?id=' . $inv['id']) ?>"><?= e(t('cust_record_payment_link')) ?></a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$invoices): ?><tr><td colspan="7" class="text-muted text-center py-4"><?= e(t('cust_ledger_no_invoices')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
