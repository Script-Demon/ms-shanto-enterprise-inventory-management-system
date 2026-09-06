<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $salary = (float)($_POST['monthly_salary'] ?? 0);

        $tooLong = first_error(
            too_long('employees.name', $name, t('sal_field_name')),
            too_long('employees.phone', $phone, t('sal_field_phone')),
            too_long('employees.designation', $designation, t('sal_field_designation'))
        );

        if ($name === '') {
            $error = t('sal_err_name_required');
        } elseif ($tooLong !== null) {
            $error = $tooLong;
        } else {
            $stmt = $pdo->prepare("INSERT INTO employees (name, phone, designation, monthly_salary) VALUES (?,?,?,?)");
            $stmt->execute([$name, $phone ?: null, $designation ?: null, $salary]);
            flash_set(t('flash_employee_added'));
            header('Location: ' . url('salary/employees.php'));
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Salary history is financial record — never silently delete it with the employee.
        $cnt = $pdo->prepare("SELECT COUNT(*) c FROM salary_payments WHERE employee_id = ?");
        $cnt->execute([$id]);
        if ($cnt->fetch()['c'] > 0) {
            $error = t('sal_err_employee_has_payments');
        } else {
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
            flash_set(t('flash_employee_deleted'));
            header('Location: ' . url('salary/employees.php'));
            exit;
        }
    }
}

$employees = $pdo->query("SELECT e.*,
        COALESCE((SELECT SUM(amount) FROM salary_payments sp WHERE sp.employee_id = e.id),0) AS paid_total,
        (SELECT COUNT(*) FROM salary_payments sp2 WHERE sp2.employee_id = e.id) AS payment_count
    FROM employees e ORDER BY e.is_active DESC, e.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('sal_employees_title')) ?></h4>
  <a href="<?= url('salary/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('sal_back_to_salary')) ?></a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-md-4">
    <form method="post" class="card p-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <h5 class="mb-3"><?= e(t('sal_add_employee')) ?></h5>
      <div class="mb-3">
        <label class="form-label"><?= e(t('sal_field_name')) ?></label>
        <input type="text" name="name" class="form-control" required maxlength="<?= field_max('employees.name') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('sal_field_designation')) ?></label>
        <input type="text" name="designation" class="form-control" maxlength="<?= field_max('employees.designation') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('sal_field_phone')) ?></label>
        <input type="text" name="phone" class="form-control" maxlength="<?= field_max('employees.phone') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('sal_field_monthly_salary')) ?></label>
        <input type="number" step="0.01" min="0" name="monthly_salary" class="form-control" value="0">
      </div>
      <button class="btn btn-primary"><?= e(t('sal_save_employee')) ?></button>
    </form>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th><?= e(t('sal_th_employee')) ?></th>
          <th><?= e(t('sal_field_designation')) ?></th>
          <th><?= e(t('common_phone')) ?></th>
          <th><?= e(t('sal_th_monthly')) ?></th>
          <th><?= e(t('sal_th_paid_total')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td>
              <?= e($emp['name']) ?>
              <?php if (!$emp['is_active']): ?> <span class="badge bg-secondary"><?= e(t('sal_inactive')) ?></span><?php endif; ?>
            </td>
            <td data-label="<?= e(t('sal_field_designation')) ?>"><?= e($emp['designation']) ?></td>
            <td data-label="<?= e(t('common_phone')) ?>"><?php if ($emp['phone']): ?><a href="tel:<?= e($emp['phone']) ?>"><?= e($emp['phone']) ?></a><?php endif; ?></td>
            <td data-label="<?= e(t('sal_th_monthly')) ?>"><?= money($emp['monthly_salary']) ?></td>
            <td data-label="<?= e(t('sal_th_paid_total')) ?>"><?= money($emp['paid_total']) ?></td>
            <td class="table-actions">
              <a href="<?= url('salary/employee_edit.php?id=' . $emp['id']) ?>"><?= e(t('common_edit')) ?></a>
              <form method="post" onsubmit="return confirm('<?= e(t('sal_confirm_delete_employee')) ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                <button class="btn btn-link text-danger p-0" <?= $emp['payment_count'] > 0 ? 'disabled title="' . e(t('sal_err_employee_has_payments')) . '"' : '' ?>><?= e(t('common_delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$employees): ?><tr><td class="text-muted text-center py-4"><?= e(t('sal_no_employees')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
