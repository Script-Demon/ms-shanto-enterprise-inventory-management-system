<?php
require_once __DIR__ . '/../includes/auth.php';

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone
        FROM invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.voided = 0";
$params = [];

if (in_array($status, ['paid', 'partial', 'due'], true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

// Search covers saved customers and walk-in details stored on the invoice itself.
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR i.walkin_name LIKE ? OR i.walkin_phone LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$sql .= " ORDER BY i.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('inv_list_title')) ?></h4>
  <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(t('inv_new_button')) ?></a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-5">
    <input type="text" name="q" class="form-control" placeholder="<?= e(t('inv_list_search_placeholder')) ?>" value="<?= e($search) ?>">
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select">
      <option value=""><?= e(t('inv_filter_all_statuses')) ?></option>
      <option value="paid" <?= $status==='paid'?'selected':'' ?>><?= e(t('status_paid')) ?></option>
      <option value="partial" <?= $status==='partial'?'selected':'' ?>><?= e(t('status_partial')) ?></option>
      <option value="due" <?= $status==='due'?'selected':'' ?>><?= e(t('status_due')) ?></option>
    </select>
  </div>
  <div class="col-md-2">
    <button class="btn btn-outline-secondary w-100"><?= e(t('cust_search_button')) ?></button>
  </div>
  <?php if ($search !== '' || $status !== ''): ?>
  <div class="col-md-2">
    <a href="<?= url('invoices/list.php') ?>" class="btn btn-link"><?= e(t('common_clear')) ?></a>
  </div>
  <?php endif; ?>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr><th><?= e(t('inv_th_invoice')) ?></th><th><?= e(t('inv_th_date')) ?></th><th><?= e(t('inv_th_customer')) ?></th><th><?= e(t('common_phone')) ?></th><th><?= e(t('inv_th_total')) ?></th><th><?= e(t('inv_th_paid')) ?></th><th><?= e(t('inv_th_due')) ?></th><th><?= e(t('inv_th_status')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($invoices as $inv): ?>
    <?php $phone = $inv['customer_phone'] ?? $inv['walkin_phone'] ?? ''; ?>
    <tr>
      <td><a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>"><?= e($inv['invoice_no']) ?></a></td>
      <td data-label="<?= e(t('inv_th_date')) ?>"><?= e($inv['invoice_date']) ?></td>
      <td data-label="<?= e(t('inv_th_customer')) ?>"><?= e($inv['customer_name'] ?? $inv['walkin_name'] ?? t('common_walkin')) ?></td>
      <td data-label="<?= e(t('common_phone')) ?>"><?php if ($phone !== ''): ?><a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a><?php endif; ?></td>
      <td data-label="<?= e(t('inv_th_total')) ?>"><?= money($inv['total']) ?></td>
      <td data-label="<?= e(t('inv_th_paid')) ?>"><?= money($inv['paid_amount']) ?></td>
      <td data-label="<?= e(t('inv_th_due')) ?>"><?= money($inv['due_amount']) ?></td>
      <td data-label="<?= e(t('inv_th_status')) ?>"><span class="badge bg-<?= $inv['status']==='paid'?'success':($inv['status']==='partial'?'warning':'danger') ?>"><?= e(t('status_' . $inv['status'])) ?></span></td>
      <td><a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>"><?= e(t('inv_view_link')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$invoices): ?><tr><td colspan="9" class="text-muted text-center py-4"><?= e($search !== '' ? t('inv_no_search_results') : t('inv_no_invoices')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
