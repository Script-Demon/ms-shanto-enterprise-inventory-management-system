<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? $_POST['entry_id'] ?? 0);
$error = null;

function transport_load(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT te.*,
            COALESCE((SELECT SUM(tp.amount) FROM transport_payments tp WHERE tp.entry_id = te.id), 0) AS paid
        FROM transport_entries te WHERE te.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$entry = transport_load($pdo, $id);
if (!$entry) {
    flash_set(t('trn_err_not_found'), 'danger');
    header('Location: ' . url('transport/index.php'));
    exit;
}
$outstanding = $entry['charge_amount'] - $entry['paid'];

/* ---------------- Record a payment ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $payDate = $_POST['payment_date'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payDate)) {
        $payDate = date('Y-m-d');
    }

    if ($amount <= 0) {
        $error = t('trn_err_amount');
    } elseif ($amount > $outstanding + 0.001) {
        // The 0.001 slack keeps a "pay the exact outstanding" click from being
        // rejected by decimal rounding on the way through the form.
        $error = t('trn_err_amount_exceeds', money($outstanding));
    } elseif (($noteErr = too_long('transport_payments.note', $note, t('common_note'))) !== null) {
        $error = $noteErr;
    } else {
        $ins = $pdo->prepare("INSERT INTO transport_payments (entry_id, amount, payment_date, note) VALUES (?,?,?,?)");
        $ins->execute([$id, $amount, $payDate, $note ?: null]);
        flash_set(t('flash_transport_paid'));
        header('Location: ' . url('transport/view.php?id=' . $id));
        exit;
    }
}

/* ---------------- Delete a payment ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_payment') {
    verify_csrf();
    $pid = (int)($_POST['payment_id'] ?? 0);
    // Scoped to this entry so a stray id cannot remove another trip's payment.
    $del = $pdo->prepare("DELETE FROM transport_payments WHERE id = ? AND entry_id = ?");
    $del->execute([$pid, $id]);
    flash_set(t('flash_transport_payment_deleted'));
    header('Location: ' . url('transport/view.php?id=' . $id));
    exit;
}

// Deleting the whole entry posts to transport/delete.php, which the list uses
// too — one endpoint, so the "not while it has payments" rule has one home.

// Re-read after a failed write so the page shows current figures either way.
$entry = transport_load($pdo, $id);
$outstanding = $entry['charge_amount'] - $entry['paid'];

$payStmt = $pdo->prepare("SELECT * FROM transport_payments WHERE entry_id = ? ORDER BY payment_date DESC, id DESC");
$payStmt->execute([$id]);
$payments = $payStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e($entry['car_number']) ?></h4>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('transport/edit.php?id=' . $id) ?>" class="btn btn-outline-secondary"><?= e(t('common_edit')) ?></a>
    <a href="<?= url('transport/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('trn_back')) ?></a>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-md-5">
    <div class="card p-4">
      <h5 class="mb-3"><?= e(t('trn_view_details')) ?></h5>
      <p class="mb-0">
        <strong><?= e(t('common_date')) ?>:</strong> <?= e($entry['entry_date']) ?><br>
        <strong><?= e(t('trn_field_car_number')) ?>:</strong> <?= e($entry['car_number']) ?><br>
        <strong><?= e(t('trn_field_driver_name')) ?>:</strong> <?= $entry['driver_name'] ? e($entry['driver_name']) : '—' ?><br>
        <strong><?= e(t('common_phone')) ?>:</strong>
        <?php if ($entry['driver_phone']): ?><a href="tel:<?= e($entry['driver_phone']) ?>"><?= e($entry['driver_phone']) ?></a><?php else: ?>—<?php endif; ?><br>
        <strong><?= e(t('trn_field_description')) ?>:</strong> <?= $entry['description'] ? e($entry['description']) : '—' ?>
      </p>

      <div class="totals-box mt-3">
        <div class="d-flex justify-content-between"><span><?= e(t('trn_th_charge')) ?></span><strong><?= money($entry['charge_amount']) ?></strong></div>
        <div class="d-flex justify-content-between mt-2"><span><?= e(t('trn_th_paid')) ?></span><strong><?= money($entry['paid']) ?></strong></div>
        <div class="d-flex justify-content-between mt-2 fs-5">
          <span><?= e(t('trn_th_outstanding')) ?></span>
          <strong class="<?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>"><?= money($outstanding) ?></strong>
        </div>
      </div>

      <form method="post" action="<?= url('transport/delete.php') ?>" class="mt-3" onsubmit="return confirm('<?= e(t('trn_confirm_delete_entry')) ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger btn-sm"><?= e(t('trn_delete_entry')) ?></button>
      </form>
    </div>
  </div>

  <div class="col-md-7">
    <?php if ($outstanding > 0): ?>
    <form method="post" class="card p-4 mb-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="pay">
      <h5 class="mb-3"><?= e(t('trn_record_payment')) ?></h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label"><?= e(t('common_amount')) ?></label>
          <input type="number" step="0.01" min="0.01" max="<?= e($outstanding) ?>" name="amount" id="payAmount" class="form-control" required value="<?= e(number_format($outstanding, 2, '.', '')) ?>">
          <div class="form-text"><?= e(t('trn_pay_hint_outstanding')) ?> <?= money($outstanding) ?></div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label"><?= e(t('common_date')) ?></label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('common_note')) ?></label>
        <input type="text" name="note" class="form-control" maxlength="<?= field_max('transport_payments.note') ?>">
      </div>
      <div><button class="btn btn-primary"><?= e(t('trn_pay_save')) ?></button></div>
    </form>
    <?php else: ?>
      <div class="alert alert-success"><?= e(t('trn_fully_paid')) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="p-3 pb-0"><h5><?= e(t('trn_payments_title')) ?></h5></div>
      <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead><tr>
          <th><?= e(t('common_date')) ?></th>
          <th><?= e(t('common_amount')) ?></th>
          <th><?= e(t('common_note')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e($p['payment_date']) ?></td>
            <td data-label="<?= e(t('common_amount')) ?>"><?= money($p['amount']) ?></td>
            <td data-label="<?= e(t('common_note')) ?>"><?= e($p['note']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('<?= e(t('trn_confirm_delete_payment')) ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_payment">
                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                <button class="btn btn-link text-danger p-0"><?= e(t('common_delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="4" class="text-muted text-center py-4"><?= e(t('trn_no_payments')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
