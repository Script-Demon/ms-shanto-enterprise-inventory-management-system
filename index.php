<?php
require_once __DIR__ . '/includes/auth.php';

$todaySales = $pdo->query("SELECT COALESCE(SUM(total),0) t FROM invoices WHERE invoice_date = CURDATE() AND voided = 0")->fetch()['t'];
$monthSales = $pdo->query("SELECT COALESCE(SUM(total),0) t FROM invoices WHERE MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) AND voided = 0")->fetch()['t'];
$todayProfit = $pdo->query("SELECT COALESCE(SUM((ii.unit_price - ii.cost_price_snapshot) * ii.quantity),0) p FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.invoice_date = CURDATE() AND i.voided = 0")->fetch()['p'];
$monthProfit = $pdo->query("SELECT COALESCE(SUM((ii.unit_price - ii.cost_price_snapshot) * ii.quantity),0) p FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE MONTH(i.invoice_date) = MONTH(CURDATE()) AND YEAR(i.invoice_date) = YEAR(CURDATE()) AND i.voided = 0")->fetch()['p'];
$totalDue = $pdo->query("SELECT COALESCE(SUM(due_amount),0) t FROM invoices WHERE voided = 0")->fetch()['t'];
$lowStock = $pdo->query("SELECT * FROM products WHERE stock_qty <= reorder_level ORDER BY stock_qty ASC LIMIT 20")->fetchAll();

// Revenue for the last 14 days, zero-filled so quiet days still occupy a slot.
require_once __DIR__ . '/includes/revenue_chart.php';
$chartDays = 30;
$revStmt = $pdo->prepare("SELECT invoice_date, SUM(total) AS revenue FROM invoices
    WHERE voided = 0 AND invoice_date BETWEEN DATE_SUB(CURDATE(), INTERVAL ? DAY) AND CURDATE()
    GROUP BY invoice_date");
$revStmt->execute([$chartDays - 1]);
$revByDate = [];
foreach ($revStmt->fetchAll() as $r) {
    $revByDate[$r['invoice_date']] = (float)$r['revenue'];
}
$chartRows = [];
for ($i = $chartDays - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chartRows[] = ['date' => $d, 'revenue' => $revByDate[$d] ?? 0.0];
}
$recentInvoices = $pdo->query("SELECT i.*, c.name AS customer_name FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.voided = 0 ORDER BY i.id DESC LIMIT 10")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="stats-grid">
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('cash') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_today_sales')) ?></span>
        <div class="stat-value"><?= money($todaySales) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-green"><?= icon('trending') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_today_profit')) ?></span>
        <div class="stat-value text-success"><?= money($todayProfit) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('chart') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_month_sales')) ?></span>
        <div class="stat-value"><?= money($monthSales) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-green"><?= icon('trending') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_month_profit')) ?></span>
        <div class="stat-value text-success"><?= money($monthProfit) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-red"><?= icon('alert') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_total_due')) ?></span>
        <div class="stat-value text-danger"><?= money($totalDue) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-amber"><?= icon('warning') ?></span>
      <span>
        <span class="stat-label"><?= e(t('dash_low_stock_items')) ?></span>
        <div class="stat-value"><?= count($lowStock) ?></div>
      </span>
    </div>
  </div>
</div>

<div class="card p-4 mb-3 chart-card">
  <div class="page-head mb-3">
    <h5 class="mb-0"><?= e(t('dash_revenue_chart')) ?></h5>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('reports/sales.php') ?>"><?= e(t('report_daily_breakdown')) ?></a>
  </div>
  <?= render_revenue_chart($chartRows) ?>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h5><?= e(t('dash_low_stock_products')) ?></h5>
      <table class="table table-sm">
        <thead><tr><th><?= e(t('common_product')) ?></th><th><?= e(t('dash_th_stock')) ?></th><th><?= e(t('dash_th_reorder_level')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($lowStock as $p): ?>
          <tr class="low-stock">
            <td><?= e($p['name']) ?></td>
            <td data-label="<?= e(t('dash_th_stock')) ?>"><?= qty($p['stock_qty']) ?> <?= e($p['unit']) ?></td>
            <td data-label="<?= e(t('dash_th_reorder_level')) ?>"><?= qty($p['reorder_level']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lowStock): ?><tr><td class="text-muted"><?= e(t('dash_no_low_stock')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5><?= e(t('dash_recent_invoices')) ?></h5>
      <table class="table table-sm">
        <thead><tr><th><?= e(t('inv_th_invoice')) ?></th><th><?= e(t('common_customer')) ?></th><th><?= e(t('common_total')) ?></th><th><?= e(t('common_status')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($recentInvoices as $inv): ?>
          <tr>
            <td><a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>"><?= e($inv['invoice_no']) ?></a></td>
            <td data-label="<?= e(t('common_customer')) ?>"><?= e($inv['customer_name'] ?? $inv['walkin_name'] ?? t('common_walkin')) ?></td>
            <td data-label="<?= e(t('common_total')) ?>"><?= money($inv['total']) ?></td>
            <td data-label="<?= e(t('common_status')) ?>"><span class="badge bg-<?= $inv['status']==='paid'?'success':($inv['status']==='partial'?'warning':'danger') ?>"><?= e(t('status_' . $inv['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentInvoices): ?><tr><td class="text-muted"><?= e(t('dash_no_invoices')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
