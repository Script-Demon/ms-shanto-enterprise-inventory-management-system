<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch();
if (!$employee) {
    flash_set(t('flash_employee_not_found'), 'danger');
    header('Location: ' . url('salary/employees.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $salary = (float)($_POST['monthly_salary'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

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
        $upd = $pdo->prepare("UPDATE employees SET name=?, phone=?, designation=?, monthly_salary=?, is_active=? WHERE id=?");
        $upd->execute([$name, $phone ?: null, $designation ?: null, $salary, $isActive, $id]);
        flash_set(t('flash_employee_updated'));
        header('Location: ' . url('salary/employees.php'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('sal_edit_employee')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card p-4" style="max-width:560px;">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label"><?= e(t('sal_field_name')) ?></label>
    <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? $employee['name']) ?>" maxlength="<?= field_max('employees.name') ?>">
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('sal_field_designation')) ?></label>
      <input type="text" name="designation" class="form-control" value="<?= e($_POST['designation'] ?? $employee['designation']) ?>" maxlength="<?= field_max('employees.designation') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('sal_field_phone')) ?></label>
      <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $employee['phone']) ?>" maxlength="<?= field_max('employees.phone') ?>">
    </div>
  </div>
  <div class="mb-3" style="max-width:220px;">
    <label class="form-label"><?= e(t('sal_field_monthly_salary')) ?></label>
    <input type="number" step="0.01" min="0" name="monthly_salary" class="form-control" value="<?= e($employee['monthly_salary']) ?>">
  </div>
  <div class="mb-3">
    <label class="d-flex align-items-center gap-2">
      <input type="checkbox" name="is_active" value="1" <?= $employee['is_active'] ? 'checked' : '' ?>>
      <span><?= e(t('sal_field_active')) ?></span>
    </label>
  </div>
  <div>
    <button class="btn btn-primary"><?= e(t('sal_update_employee')) ?></button>
    <a href="<?= url('salary/employees.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
