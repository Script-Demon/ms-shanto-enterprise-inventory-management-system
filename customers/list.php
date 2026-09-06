<?php
require_once __DIR__ . '/../includes/auth.php';

$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, COALESCE((SELECT SUM(due_amount) FROM invoices i WHERE i.customer_id = c.id AND i.voided = 0), 0) AS total_due FROM customers c WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY c.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('cust_page_title')) ?></h4>
  <a href="<?= url('customers/add.php') ?>" class="btn btn-primary"><?= e(t('cust_add_button')) ?></a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="<?= e(t('cust_search_placeholder')) ?>" value="<?= e($search) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><?= e(t('cust_search_button')) ?></button></div>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr><th><?= e(t('cust_th_name')) ?></th><th><?= e(t('cust_th_phone')) ?></th><th><?= e(t('cust_th_address')) ?></th><th><?= e(t('cust_th_outstanding_due')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($customers as $c): ?>
    <tr>
      <td><?= e($c['name']) ?></td>
      <td data-label="<?= e(t('cust_th_phone')) ?>"><?= e($c['phone']) ?></td>
      <td data-label="<?= e(t('cust_th_address')) ?>"><?= e($c['address']) ?></td>
      <td data-label="<?= e(t('cust_th_outstanding_due')) ?>" class="<?= $c['total_due']>0 ? 'text-danger fw-bold' : '' ?>"><?= money($c['total_due']) ?></td>
      <td class="table-actions">
        <a href="<?= url('customers/ledger.php?id=' . $c['id']) ?>"><?= e(t('cust_ledger_link')) ?></a>
        <a href="<?= url('customers/edit.php?id=' . $c['id']) ?>"><?= e(t('common_edit')) ?></a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$customers): ?><tr><td colspan="5" class="text-muted text-center py-4"><?= e(t('cust_no_customers')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
