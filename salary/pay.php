<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
$preselect = (int)($_GET['employee'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
    $chk->execute([$employeeId]);

    if (!$chk->fetch()) {
        $error = t('sal_err_select_employee');
    } elseif ($amount <= 0) {
        $error = t('sal_err_amount');
    } elseif (($noteErr = too_long('salary_payments.note', $note, t('sal_pay_note'))) !== null) {
        $error = $noteErr;
    } else {
        $ins = $pdo->prepare("INSERT INTO salary_payments (employee_id, amount, payment_date, note) VALUES (?,?,?,?)");
        $ins->execute([$employeeId, $amount, $date, $note ?: null]);
        flash_set(t('flash_salary_paid'));
        header('Location: ' . url('salary/index.php'));
        exit;
    }
}

$employees = $pdo->query("SELECT id, name, designation, monthly_salary FROM employees WHERE is_active = 1 ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('sal_pay_title')) ?></h4>
  <a href="<?= url('salary/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('sal_back_to_salary')) ?></a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$employees): ?>
  <div class="alert alert-info"><?= e(t('sal_no_employees')) ?></div>
  <a href="<?= url('salary/employees.php') ?>" class="btn btn-primary"><?= e(t('sal_add_employee')) ?></a>
<?php else: ?>
<form method="post" class="card p-4" style="max-width:520px;">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label"><?= e(t('sal_filter_employee')) ?></label>
    <select name="employee_id" id="employeeSelect" class="form-select" required>
      <option value=""><?= e(t('stock_select_placeholder')) ?></option>
      <?php foreach ($employees as $emp): ?>
        <option value="<?= $emp['id'] ?>"
                data-salary="<?= e($emp['monthly_salary']) ?>"
                <?= $preselect === (int)$emp['id'] ? 'selected' : '' ?>>
          <?= e($emp['name']) ?><?= $emp['designation'] ? ' — ' . e($emp['designation']) : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('sal_pay_amount')) ?></label>
    <input type="number" step="0.01" min="0.01" name="amount" id="amountInput" class="form-control" required>
    <div class="form-text" id="salaryHint"></div>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('sal_pay_date')) ?></label>
    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('sal_pay_note')) ?></label>
    <input type="text" name="note" class="form-control" maxlength="<?= field_max('salary_payments.note') ?>">
    <div class="form-text"><?= e(t('sal_pay_note_hint')) ?></div>
  </div>
  <div><button class="btn btn-primary"><?= e(t('sal_pay_save')) ?></button></div>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
/* Prefill the amount with the employee's agreed monthly salary — still editable
   for advances or part-payments. */
(function () {
  var sel = document.getElementById('employeeSelect');
  var amount = document.getElementById('amountInput');
  var hint = document.getElementById('salaryHint');
  if (!sel || !amount) return;

  var LABEL = <?= json_encode(t('sal_field_monthly_salary')) ?>;
  var CURRENCY = <?= json_encode($config['currency'] ?? '') ?>;

  function fill() {
    var opt = sel.options[sel.selectedIndex];
    var salary = opt ? opt.getAttribute('data-salary') : null;
    if (!salary) { hint.textContent = ''; return; }
    hint.textContent = LABEL + ': ' + CURRENCY + parseFloat(salary).toFixed(2);
    if (!amount.value) amount.value = parseFloat(salary).toFixed(2);
  }
  sel.addEventListener('change', function () { amount.value = ''; fill(); });
  fill();
})();
</script>
