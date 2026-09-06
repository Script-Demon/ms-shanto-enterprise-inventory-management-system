<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $carNumber   = trim($_POST['car_number'] ?? '');
    $driverName  = trim($_POST['driver_name'] ?? '');
    $driverPhone = trim($_POST['driver_phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $charge      = (float)($_POST['charge_amount'] ?? 0);
    $entryDate   = $_POST['entry_date'] ?? date('Y-m-d');
    $paidNow     = (float)($_POST['paid_now'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $entryDate = date('Y-m-d');
    }

    if ($carNumber === '') {
        $error = t('trn_err_car_required');
    } elseif ($charge <= 0) {
        $error = t('trn_err_charge');
    } elseif ($paidNow < 0) {
        $error = t('trn_err_amount');
    } elseif ($paidNow > $charge) {
        $error = t('trn_err_paid_exceeds_charge', money($charge));
    } elseif (($tooLong = first_error(
        too_long('transport_entries.car_number', $carNumber, t('trn_field_car_number')),
        too_long('transport_entries.driver_name', $driverName, t('trn_field_driver_name')),
        too_long('transport_entries.driver_phone', $driverPhone, t('trn_field_driver_phone')),
        too_long('transport_entries.description', $description, t('trn_field_description'))
    )) !== null) {
        $error = $tooLong;
    } else {
        // The entry and its opening payment are one action to the user, so they
        // succeed or fail together — never a trip with a half-recorded payment.
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO transport_entries
                (entry_date, car_number, driver_name, driver_phone, description, charge_amount)
                VALUES (?,?,?,?,?,?)");
            $ins->execute([$entryDate, $carNumber, $driverName ?: null, $driverPhone ?: null, $description ?: null, $charge]);

            if ($paidNow > 0) {
                // No note: it would have to be a translated string frozen into
                // the row, which would then stay in one language forever. The
                // payment already carries the trip's own date, which says it.
                $entryId = (int)$pdo->lastInsertId();
                $pay = $pdo->prepare("INSERT INTO transport_payments (entry_id, amount, payment_date, note) VALUES (?,?,?,NULL)");
                $pay->execute([$entryId, $paidNow, $entryDate]);
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        flash_set(t('flash_transport_added'));
        header('Location: ' . url('transport/index.php'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('trn_add_title')) ?></h4>
  <a href="<?= url('transport/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('trn_back')) ?></a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card p-4" style="max-width:650px;">
  <?= csrf_field() ?>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_car_number')) ?></label>
      <input type="text" name="car_number" class="form-control" required value="<?= e($_POST['car_number'] ?? '') ?>" maxlength="<?= field_max('transport_entries.car_number') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('common_date')) ?></label>
      <input type="date" name="entry_date" class="form-control" value="<?= e($_POST['entry_date'] ?? date('Y-m-d')) ?>">
    </div>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_driver_name')) ?></label>
      <input type="text" name="driver_name" class="form-control" value="<?= e($_POST['driver_name'] ?? '') ?>" maxlength="<?= field_max('transport_entries.driver_name') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_driver_phone')) ?></label>
      <input type="text" name="driver_phone" class="form-control" value="<?= e($_POST['driver_phone'] ?? '') ?>" maxlength="<?= field_max('transport_entries.driver_phone') ?>">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('trn_field_description')) ?></label>
    <input type="text" name="description" class="form-control" value="<?= e($_POST['description'] ?? '') ?>" maxlength="<?= field_max('transport_entries.description') ?>">
    <div class="form-text"><?= e(t('trn_field_description_hint')) ?></div>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_charge')) ?></label>
      <input type="number" step="0.01" min="0.01" name="charge_amount" class="form-control" required value="<?= e($_POST['charge_amount'] ?? '') ?>">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label"><?= e(t('trn_field_paid_now')) ?></label>
      <input type="number" step="0.01" min="0" name="paid_now" class="form-control" value="<?= e($_POST['paid_now'] ?? '0') ?>">
      <div class="form-text"><?= e(t('trn_field_paid_now_hint')) ?></div>
    </div>
  </div>
  <div>
    <button class="btn btn-primary"><?= e(t('trn_save_button')) ?></button>
    <a href="<?= url('transport/index.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a>
  </div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
