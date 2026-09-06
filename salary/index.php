<?php
require_once __DIR__ . '/../includes/auth.php';

// Delete a payment record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_payment') {
    verify_csrf();
    $pid = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM salary_payments WHERE id = ?")->execute([$pid]);
    flash_set(t('flash_salary_deleted'));
    header('Location: ' . url('salary/index.php?' . http_build_query(array_diff_key($_GET, ['' => '']))));
    exit;
}

/* ---------------- Filters ---------------- */
$year  = $_GET['year']  ?? date('Y');
$month = $_GET['month'] ?? date('n');
$empId = $_GET['employee'] ?? '';

if ($year !== 'all' && !preg_match('/^\d{4}$/', (string)$year)) { $year = date('Y'); }
if ($month !== 'all' && !preg_match('/^([1-9]|1[0-2])$/', (string)$month)) { $month = date('n'); }

// Build the period filter used by every query on this page
$where = [];
$params = [];
if ($year !== 'all') {
    $where[] = 'YEAR(sp.payment_date) = ?';
    $params[] = (int)$year;
}
if ($month !== 'all') {
    $where[] = 'MONTH(sp.payment_date) = ?';
    $params[] = (int)$month;
}
if ($empId !== '') {
    $where[] = 'sp.employee_id = ?';
    $params[] = (int)$empId;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------------- Summary ---------------- */
$periodStmt = $pdo->prepare("SELECT COALESCE(SUM(sp.amount),0) total FROM salary_payments sp $whereSql");
$periodStmt->execute($params);
$periodTotal = $periodStmt->fetch()['total'];

$monthTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) t FROM salary_payments
    WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())")->fetch()['t'];
$yearTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) t FROM salary_payments
    WHERE YEAR(payment_date) = YEAR(CURDATE())")->fetch()['t'];
$commitment = $pdo->query("SELECT COALESCE(SUM(monthly_salary),0) t, COUNT(*) c FROM employees WHERE is_active = 1")->fetch();

/* ---------------- Per-employee totals ---------------- */
$empParams = [];
$empPeriod = [];
if ($year !== 'all')  { $empPeriod[] = 'YEAR(sp.payment_date) = ?';  $empParams[] = (int)$year; }
if ($month !== 'all') { $empPeriod[] = 'MONTH(sp.payment_date) = ?'; $empParams[] = (int)$month; }
$empPeriodSql = $empPeriod ? ('AND ' . implode(' AND ', $empPeriod)) : '';

$empSql = "SELECT e.*,
      COALESCE((SELECT SUM(sp.amount) FROM salary_payments sp
                WHERE sp.employee_id = e.id $empPeriodSql), 0) AS paid_period,
      COALESCE((SELECT SUM(sp2.amount) FROM salary_payments sp2 WHERE sp2.employee_id = e.id), 0) AS paid_total,
      (SELECT MAX(sp3.payment_date) FROM salary_payments sp3 WHERE sp3.employee_id = e.id) AS last_payment
    FROM employees e
    ORDER BY e.is_active DESC, e.name";
$empStmt = $pdo->prepare($empSql);
$empStmt->execute($empParams);
$employees = $empStmt->fetchAll();

/* ---------------- Payment list ---------------- */
$listStmt = $pdo->prepare("SELECT sp.*, e.name AS employee_name, e.designation
    FROM salary_payments sp JOIN employees e ON e.id = sp.employee_id
    $whereSql
    ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 200");
$listStmt->execute($params);
$payments = $listStmt->fetchAll();

/* ---------------- Filter option data ---------------- */
$years = $pdo->query("SELECT DISTINCT YEAR(payment_date) y FROM salary_payments ORDER BY y DESC")->fetchAll();
$yearOptions = array_column($years, 'y');
if (!in_array((int)date('Y'), array_map('intval', $yearOptions), true)) {
    array_unshift($yearOptions, date('Y'));
}
$allEmployees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('sal_page_title')) ?></h4>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('salary/employees.php') ?>" class="btn btn-outline-secondary"><?= e(t('sal_manage_employees')) ?></a>
    <a href="<?= url('salary/pay.php') ?>" class="btn btn-primary"><?= e(t('sal_record_payment')) ?></a>
  </div>
</div>

<div class="stats-grid">
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('wallet') ?></span>
      <span>
        <span class="stat-label"><?= e(t('sal_stat_period_total')) ?></span>
        <div class="stat-value"><?= money($periodTotal) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('cash') ?></span>
      <span>
        <span class="stat-label"><?= e(t('sal_stat_month_total')) ?></span>
        <div class="stat-value"><?= money($monthTotal) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon"><?= icon('chart') ?></span>
      <span>
        <span class="stat-label"><?= e(t('sal_stat_year_total')) ?></span>
        <div class="stat-value"><?= money($yearTotal) ?></div>
      </span>
    </div>
  </div>
  <div class="card p-3">
    <div class="stat">
      <span class="stat-icon is-amber"><?= icon('users') ?></span>
      <span>
        <span class="stat-label"><?= e(t('sal_stat_monthly_commitment')) ?></span>
        <div class="stat-value"><?= money($commitment['t']) ?></div>
        <span class="stat-label"><?= (int)$commitment['c'] ?> · <?= e(t('sal_stat_employees')) ?></span>
      </span>
    </div>
  </div>
</div>

<form class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label"><?= e(t('sal_filter_month')) ?></label>
    <select name="month" class="form-select">
      <option value="all" <?= $month === 'all' ? 'selected' : '' ?>><?= e(t('sal_filter_all_months')) ?></option>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?= $m ?>" <?= (string)$month === (string)$m ? 'selected' : '' ?>><?= e(t('month_' . $m)) ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= e(t('sal_filter_year')) ?></label>
    <select name="year" class="form-select">
      <option value="all" <?= $year === 'all' ? 'selected' : '' ?>><?= e(t('sal_filter_all_years')) ?></option>
      <?php foreach ($yearOptions as $y): ?>
        <option value="<?= (int)$y ?>" <?= (string)$year === (string)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label"><?= e(t('sal_filter_employee')) ?></label>
    <select name="employee" class="form-select">
      <option value=""><?= e(t('sal_filter_all_employees')) ?></option>
      <?php foreach ($allEmployees as $emp): ?>
        <option value="<?= $emp['id'] ?>" <?= (string)$empId === (string)$emp['id'] ? 'selected' : '' ?>><?= e($emp['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2 align-self-end">
    <button class="btn btn-outline-secondary w-100"><?= e(t('report_filter_button')) ?></button>
  </div>
</form>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h5><?= e(t('sal_employees_title')) ?></h5>
      <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead><tr>
          <th><?= e(t('sal_th_employee')) ?></th>
          <th><?= e(t('sal_th_monthly')) ?></th>
          <th><?= e(t('sal_th_paid_period')) ?></th>
          <th><?= e(t('sal_th_paid_total')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td>
              <?= e($emp['name']) ?>
              <?php if (!$emp['is_active']): ?><span class="badge bg-secondary"><?= e(t('sal_inactive')) ?></span><?php endif; ?>
              <?php if ($emp['designation']): ?><div class="text-muted" style="font-size:.8rem"><?= e($emp['designation']) ?></div><?php endif; ?>
            </td>
            <td data-label="<?= e(t('sal_th_monthly')) ?>"><?= money($emp['monthly_salary']) ?></td>
            <td data-label="<?= e(t('sal_th_paid_period')) ?>"><?= money($emp['paid_period']) ?></td>
            <td data-label="<?= e(t('sal_th_paid_total')) ?>"><?= money($emp['paid_total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$employees): ?><tr><td class="text-muted text-center py-4"><?= e(t('sal_no_employees')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card p-3">
      <h5><?= e(t('sal_payments_title')) ?></h5>
      <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead><tr>
          <th><?= e(t('sal_th_employee')) ?></th>
          <th><?= e(t('common_date')) ?></th>
          <th><?= e(t('common_amount')) ?></th>
          <th><?= e(t('common_note')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e($p['employee_name']) ?></td>
            <td data-label="<?= e(t('common_date')) ?>"><?= e($p['payment_date']) ?></td>
            <td data-label="<?= e(t('common_amount')) ?>"><?= money($p['amount']) ?></td>
            <td data-label="<?= e(t('common_note')) ?>"><?= e($p['note']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('<?= e(t('sal_confirm_delete_payment')) ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-link text-danger p-0"><?= e(t('common_delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td class="text-muted text-center py-4"><?= e(t('sal_no_payments')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
