<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM transport_entries WHERE id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch();
if (!$entry) {
    flash_set(t('trn_err_not_found'), 'danger');
    header('Location: ' . url('transport/index.php'));
    exit;
}

$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) p FROM transport_payments WHERE entry_id = ?");
$paidStmt->execute([$id]);
$paid = (float)$paidStmt->fetch()['p'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $carNumber   = trim($_POST['car_number'] ?? '');
    $driverName  = trim($_POST['driver_name'] ?? '');
    $driverPhone = trim($_POST['driver_phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $charge      = (float)($_POST['charge_amount'] ?? 0);
    $entryDate   = $_POST['entry_date'] ?? $entry['entry_date'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $entryDate = $entry['entry_date'];
    }

    if ($carNumber === '') {
        $error = t('trn_err_car_required');
    } elseif ($charge <= 0) {
        $error = t('trn_err_charge');
    } elseif ($charge < $paid) {
        // Cutting the charge below what has already been paid would leave a
        // negative outstanding, which the rest of the module treats as settled.
        $error = t('trn_err_charge_below_paid', money($paid));
    } elseif (($tooLong = first_error(
        too_long('transport_entries.car_number', $carNumber, t('trn_field_car_number')),
        too_long('transport_entries.driver_name', $driverName, t('trn_field_driver_name')),
        too_long('transport_entries.driver_phone', $driverPhone, t('trn_field_driver_phone')),
        too_long('transport_entries.description', $description, t('trn_field_description'))
    )) !== null) {
        $error = $tooLong;
    } else {
        $upd = $pdo->prepare("UPDATE transport_entries
            SET entry_date=?, car_number=?, driver_name=?, driver_phone=?, description=?, charge_amount=?
            WHERE id=?");
        $upd->execute([$entryDate, $carNumber, $driverName ?: null, $driverPhone ?: null, $description ?: null, $charge, $id]);
        flash_set(t('flash_transport_updated'));
        header('Location: ' . url('transport/view.php?id=' . $id));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('trn_edit_title')) ?></h4>
  <a href="<?= url('transport/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('trn_back')) ?></a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card p-4" style="max-width:650px;">
  <?= csrf_field() ?>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_car_number')) ?></label>
      <input type="text" name="car_number" class="form-control" required value="<?= e($_POST['car_number'] ?? $entry['car_number']) ?>" maxlength="<?= field_max('transport_entries.car_number') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('common_date')) ?></label>
      <input type="date" name="entry_date" class="form-control" value="<?= e($_POST['entry_date'] ?? $entry['entry_date']) ?>">
    </div>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_driver_name')) ?></label>
      <input type="text" name="driver_name" class="form-control" value="<?= e($_POST['driver_name'] ?? $entry['driver_name']) ?>" maxlength="<?= field_max('transport_entries.driver_name') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_driver_phone')) ?></label>
      <input type="text" name="driver_phone" class="form-control" value="<?= e($_POST['driver_phone'] ?? $entry['driver_phone']) ?>" maxlength="<?= field_max('transport_entries.driver_phone') ?>">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('trn_field_description')) ?></label>
    <input type="text" name="description" class="form-control" value="<?= e($_POST['description'] ?? $entry['description']) ?>" maxlength="<?= field_max('transport_entries.description') ?>">
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_charge')) ?></label>
      <input type="number" step="0.01" min="0.01" name="charge_amount" class="form-control" required value="<?= e($_POST['charge_amount'] ?? $entry['charge_amount']) ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_th_paid')) ?></label>
      <input type="text" class="form-control" value="<?= e(money($paid)) ?>" disabled>
      <div class="form-text"><?= e(t('trn_edit_paid_hint')) ?></div>
    </div>
  </div>
  <div>
    <button class="btn btn-primary"><?= e(t('trn_update_button')) ?></button>
    <a href="<?= url('transport/view.php?id=' . $id) ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
