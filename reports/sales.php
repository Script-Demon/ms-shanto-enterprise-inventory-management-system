<?php
require_once __DIR__ . '/../includes/auth.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to = date('Y-m-d'); }

$stmt = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) total_sales, COALESCE(SUM(paid_amount),0) total_paid, COALESCE(SUM(due_amount),0) total_due FROM invoices WHERE invoice_date BETWEEN ? AND ? AND voided = 0");
$stmt->execute([$from, $to]);
$summary = $stmt->fetch();

$profitStmt = $pdo->prepare("SELECT COALESCE(SUM((ii.unit_price - ii.cost_price_snapshot) * ii.quantity),0) profit FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.invoice_date BETWEEN ? AND ? AND i.voided = 0");
$profitStmt->execute([$from, $to]);
$profit = $profitStmt->fetch()['profit'];

$stockValue = $pdo->query("SELECT COALESCE(SUM(stock_qty * cost_price),0) v FROM products")->fetch()['v'];

$dailyStmt = $pdo->prepare("SELECT invoice_date, SUM(total) day_total FROM invoices WHERE invoice_date BETWEEN ? AND ? AND voided = 0 GROUP BY invoice_date ORDER BY invoice_date DESC");
$dailyStmt->execute([$from, $to]);
$daily = $dailyStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('report_page_title')) ?></h4>
<form class="row g-2 mb-3">
  <div class="col-md-3"><label class="form-label"><?= e(t('report_field_from')) ?></label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label"><?= e(t('report_field_to')) ?></label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-md-2 align-self-end"><button class="btn btn-outline-secondary w-100"><?= e(t('report_filter_button')) ?></button></div>
</form>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3"><div class="text-muted"><?= e(t('report_stat_invoices')) ?></div><div class="fs-4"><?= (int)$summary['cnt'] ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted"><?= e(t('report_stat_total_sales')) ?></div><div class="fs-4"><?= money($summary['total_sales']) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted"><?= e(t('report_stat_profit')) ?></div><div class="fs-4 text-success"><?= money($profit) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><div class="text-muted"><?= e(t('report_stat_stock_value')) ?></div><div class="fs-4"><?= money($stockValue) ?></div></div></div>
</div>
<h5><?= e(t('report_daily_breakdown')) ?></h5>
<table class="table table-sm bg-white">
  <thead><tr><th><?= e(t('common_date')) ?></th><th><?= e(t('report_th_sales')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($daily as $d): ?>
    <tr><td><?= e($d['invoice_date']) ?></td><td data-label="<?= e(t('report_th_sales')) ?>"><?= money($d['day_total']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$daily): ?><tr><td colspan="2" class="text-muted text-center py-3"><?= e(t('report_no_sales')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../includes/footer.php'; ?>
