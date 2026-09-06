<?php
require_once __DIR__ . '/../includes/auth.php';

/* ---------------- Filters ----------------
   Month and year narrow the list to a period; an exact date is more specific
   than either, so when one is given it wins and the two selects are ignored.
   Everything filters on the trip date rather than the payment date, so the
   period figures answer "what was charged in this month", which is what the
   summary tiles claim. */
$year   = $_GET['year']  ?? date('Y');
$month  = $_GET['month'] ?? date('n');
$date   = trim($_GET['date'] ?? '');
$search = trim($_GET['q'] ?? '');

if ($year !== 'all' && !preg_match('/^\d{4}$/', (string)$year)) { $year = date('Y'); }
if ($month !== 'all' && !preg_match('/^([1-9]|1[0-2])$/', (string)$month)) { $month = date('n'); }
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = ''; }

$where = [];
$params = [];
if ($date !== '') {
    $where[] = 'te.entry_date = ?';
    $params[] = $date;
} else {
    if ($year !== 'all')  { $where[] = 'YEAR(te.entry_date) = ?';  $params[] = (int)$year; }
    if ($month !== 'all') { $where[] = 'MONTH(te.entry_date) = ?'; $params[] = (int)$month; }
}
if ($search !== '') {
    $where[] = '(te.car_number LIKE ? OR te.driver_name LIKE ? OR te.driver_phone LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------------- Entries in the filtered period ---------------- */
$listStmt = $pdo->prepare("SELECT te.*,
        COALESCE((SELECT SUM(tp.amount) FROM transport_payments tp WHERE tp.entry_id = te.id), 0) AS paid
    FROM transport_entries te
    $whereSql
    ORDER BY te.entry_date DESC, te.id DESC
    LIMIT 300");
$listStmt->execute($params);
$entries = $listStmt->fetchAll();

/* ---------------- Summary ---------------- */
// SUM over a per-row subquery: an entry with no payments contributes NULL,
// which SUM() skips, so the total stays right instead of collapsing to NULL.
$sumStmt = $pdo->prepare("SELECT
        COALESCE(SUM(te.charge_amount), 0) AS charged,
        COALESCE(SUM((SELECT SUM(tp.amount) FROM transport_payments tp WHERE tp.entry_id = te.id)), 0) AS paid
    FROM transport_entries te $whereSql");
$sumStmt->execute($params);
$period = $sumStmt->fetch();
$periodOutstanding = $period['charged'] - $period['paid'];

$monthTotal = $pdo->query("SELECT COALESCE(SUM(charge_amount),0) t FROM transport_entries
    WHERE MONTH(entry_date) = MONTH(CURDATE()) AND YEAR(entry_date) = YEAR(CURDATE())")->fetch()['t'];
$yearTotal = $pdo->query("SELECT COALESCE(SUM(charge_amount),0) t FROM transport_entries
    WHERE YEAR(entry_date) = YEAR(CURDATE())")->fetch()['t'];
$totalOutstanding = $pdo->query("SELECT COALESCE((SELECT SUM(charge_amount) FROM transport_entries),0)
    - COALESCE((SELECT SUM(amount) FROM transport_payments),0) AS o")->fetch()['o'];

/* ---------------- Filter option data ---------------- */
$years = $pdo->query("SELECT DISTINCT YEAR(entry_date) y FROM transport_entries ORDER BY y DESC")->fetchAll();
$yearOptions = array_column($years, 'y');
if (!in_array((int)date('Y'), array_map('intval', $yearOptions), true)) {
    array_unshift($yearOptions, date('Y'));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('trn_page_title')) ?></h4>
  <a href="<?= url('transport/add.php') ?>" class="btn btn-primary"><?= e(t('trn_add_button')) ?></a>
</div>

<div class="stats-grid">
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('truck') ?></span>
      <span>
        <span class="stat-label"><?= e(t('trn_stat_period')) ?></span>
        <div class="stat-value"><?= money($period['charged']) ?></div>
        <span class="stat-label"><?= e(t('trn_stat_period_paid')) ?> <?= money($period['paid']) ?></span>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('cash') ?></span>
      <span>
        <span class="stat-label"><?= e(t('trn_stat_month')) ?></span>
        <div class="stat-value"><?= money($monthTotal) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('chart') ?></span>
      <span>
        <span class="stat-label"><?= e(t('trn_stat_year')) ?></span>
        <div class="stat-value"><?= money($yearTotal) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-amber"><?= icon('alert') ?></span>
      <span>
        <span class="stat-label"><?= e(t('trn_stat_outstanding')) ?></span>
        <div class="stat-value"><?= money($totalOutstanding) ?></div>
      </span>
    </div>
  </div>
</div>

<form class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label"><?= e(t('trn_search_label')) ?></label>
    <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= e(t('trn_search_placeholder')) ?>">
  </div>
  <div class="col-md-2">
    <label class="form-label"><?= e(t('trn_filter_month')) ?></label>
    <select name="month" class="form-select">
      <option value="all" <?= $month === 'all' ? 'selected' : '' ?>><?= e(t('trn_filter_all_months')) ?></option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= (string)$month === (string)$m ? 'selected' : '' ?>><?= e(t('month_' . $m)) ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label"><?= e(t('trn_filter_year')) ?></label>
    <select name="year" class="form-select">
      <option value="all" <?= $year === 'all' ? 'selected' : '' ?>><?= e(t('trn_filter_all_years')) ?></option>
      <?php foreach ($yearOptions as $y): ?>
        <option value="<?= (int)$y ?>" <?= (string)$year === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label"><?= e(t('trn_filter_date')) ?></label>
    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
  </div>
  <div class="col-md-3 align-self-end">
    <button class="btn btn-outline-secondary"><?= e(t('report_filter_button')) ?></button>
    <a href="<?= url('transport/index.php') ?>" class="btn btn-link"><?= e(t('common_clear')) ?></a>
  </div>
  <div class="col-12"><div class="form-text"><?= e(t('trn_filter_date_hint')) ?></div></div>
</form>

<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr>
    <th><?= e(t('common_date')) ?></th>
    <th><?= e(t('trn_th_car')) ?></th>
    <th><?= e(t('trn_th_driver')) ?></th>
    <th><?= e(t('common_phone')) ?></th>
    <th><?= e(t('trn_th_charge')) ?></th>
    <th><?= e(t('trn_th_paid')) ?></th>
    <th><?= e(t('trn_th_outstanding')) ?></th>
    <th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($entries as $en): $out = $en['charge_amount'] - $en['paid']; ?>
    <tr>
      <td><?= e($en['entry_date']) ?></td>
      <td data-label="<?= e(t('trn_th_car')) ?>">
        <a href="<?= url('transport/view.php?id=' . $en['id']) ?>"><strong><?= e($en['car_number']) ?></strong></a>
        <?php if ($en['description']): ?><div class="text-muted" style="font-size:.8rem"><?= e($en['description']) ?></div><?php endif; ?>
      </td>
      <td data-label="<?= e(t('trn_th_driver')) ?>"><?= e($en['driver_name']) ?></td>
      <td data-label="<?= e(t('common_phone')) ?>"><?php if ($en['driver_phone']): ?><a href="tel:<?= e($en['driver_phone']) ?>"><?= e($en['driver_phone']) ?></a><?php endif; ?></td>
      <td data-label="<?= e(t('trn_th_charge')) ?>"><?= money($en['charge_amount']) ?></td>
      <td data-label="<?= e(t('trn_th_paid')) ?>"><?= money($en['paid']) ?></td>
      <td data-label="<?= e(t('trn_th_outstanding')) ?>">
        <?php if ($out > 0): ?><span class="text-danger"><?= money($out) ?></span>
        <?php else: ?><span class="badge bg-success"><?= e(t('trn_status_settled')) ?></span><?php endif; ?>
      </td>
      <td class="table-actions">
        <a href="<?= url('transport/view.php?id=' . $en['id']) ?>"><?= e(t('trn_record_payment')) ?></a>
        <a href="<?= url('transport/edit.php?id=' . $en['id']) ?>"><?= e(t('common_edit')) ?></a>
        <form method="post" action="<?= url('transport/delete.php') ?>" onsubmit="return confirm('<?= e(t('trn_confirm_delete_entry')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $en['id'] ?>">
          <button type="submit" class="btn btn-link text-danger p-0"><?= e(t('common_delete')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$entries): ?><tr><td colspan="8" class="text-muted text-center py-4"><?= e(t('trn_no_entries')) ?></td></tr><?php endif; ?>
  </tbody>
  <?php if ($entries): ?>
  <tfoot><tr>
    <th colspan="4"><?= e(t('trn_th_period_total')) ?></th>
    <th data-label="<?= e(t('trn_th_charge')) ?>"><?= money($period['charged']) ?></th>
    <th data-label="<?= e(t('trn_th_paid')) ?>"><?= money($period['paid']) ?></th>
    <th data-label="<?= e(t('trn_th_outstanding')) ?>"><?= money($periodOutstanding) ?></th>
    <th></th>
  </tr></tfoot>
  <?php endif; ?>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
