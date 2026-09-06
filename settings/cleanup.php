<?php
require_once __DIR__ . '/../includes/auth.php';

/* Bulk removal of old records by date range, to keep the database small.
   Nothing here touches master data (products, categories, customers,
   employees, settings, your login) — only dated history.

   Two deliberate rules:
   - Stock is never adjusted. These are archived records; the stock figure is
     a current value, so restoring quantities from a five-year-old invoice
     would silently inflate your inventory.
   - Records that still carry money are protected by default. An unpaid
     invoice is the evidence a customer owes you, and an unsettled trip the
     evidence you owe a driver; deleting those loses the debt, so it takes an
     explicit tick to include them. */

$error = null;
$preview = null;

$from        = trim($_POST['from'] ?? $_GET['from'] ?? '');
$to          = trim($_POST['to'] ?? $_GET['to'] ?? '');
$doInvoices  = !empty($_POST['t_invoices']);
$doStock     = !empty($_POST['t_stock']);
$doSalary    = !empty($_POST['t_salary']);
$doTransport = !empty($_POST['t_transport']);
$includeOwing = !empty($_POST['include_owing']);

// On first load nothing is ticked, so default the form to a sensible selection.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $doInvoices = $doStock = $doSalary = $doTransport = true;
    $to = date('Y-m-d', strtotime('-1 year'));
    $from = '2000-01-01';
}

function valid_date($d) {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/* One counting routine, used by both the preview and the delete, so what you
   are shown and what is removed can never drift apart. */
function cleanup_counts(PDO $pdo, $from, $to, $includeOwing) {
    $c = ['invoices' => 0, 'invoice_items' => 0, 'invoice_payments' => 0, 'invoices_kept' => 0,
          'stock' => 0, 'salary' => 0, 'transport' => 0, 'transport_payments' => 0, 'transport_kept' => 0];
    $range = [$from, $to];

    $paidOnly = $includeOwing ? '' : ' AND i.due_amount <= 0.004';
    $s = $pdo->prepare("SELECT COUNT(*) n FROM invoices i WHERE i.invoice_date BETWEEN ? AND ?$paidOnly");
    $s->execute($range); $c['invoices'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
        WHERE i.invoice_date BETWEEN ? AND ?$paidOnly");
    $s->execute($range); $c['invoice_items'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM payments p JOIN invoices i ON i.id = p.invoice_id
        WHERE i.invoice_date BETWEEN ? AND ?$paidOnly");
    $s->execute($range); $c['invoice_payments'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? AND i.due_amount > 0.004");
    $s->execute($range); $c['invoices_kept'] = $includeOwing ? 0 : (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM stock_adjustments WHERE DATE(adjusted_at) BETWEEN ? AND ?");
    $s->execute($range); $c['stock'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM salary_payments WHERE payment_date BETWEEN ? AND ?");
    $s->execute($range); $c['salary'] = (int)$s->fetch()['n'];

    // A trip is "settled" when its payments cover the charge.
    $settled = $includeOwing ? '' : ' AND (te.charge_amount - COALESCE((SELECT SUM(tp.amount)
                 FROM transport_payments tp WHERE tp.entry_id = te.id), 0)) <= 0.004';
    $s = $pdo->prepare("SELECT COUNT(*) n FROM transport_entries te WHERE te.entry_date BETWEEN ? AND ?$settled");
    $s->execute($range); $c['transport'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM transport_payments tp JOIN transport_entries te ON te.id = tp.entry_id
        WHERE te.entry_date BETWEEN ? AND ?$settled");
    $s->execute($range); $c['transport_payments'] = (int)$s->fetch()['n'];

    $s = $pdo->prepare("SELECT COUNT(*) n FROM transport_entries te WHERE te.entry_date BETWEEN ? AND ?
        AND (te.charge_amount - COALESCE((SELECT SUM(tp.amount) FROM transport_payments tp WHERE tp.entry_id = te.id), 0)) > 0.004");
    $s->execute($range); $c['transport_kept'] = $includeOwing ? 0 : (int)$s->fetch()['n'];

    return $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if (!valid_date($from) || !valid_date($to)) {
        $error = t('clean_err_dates');
    } elseif ($from > $to) {
        $error = t('clean_err_order');
    } elseif (!$doInvoices && !$doStock && !$doSalary && !$doTransport) {
        $error = t('clean_err_nothing_selected');
    } elseif ($action === 'delete' && empty($_POST['confirm'])) {
        $error = t('clean_err_confirm');
    } else {
        $counts = cleanup_counts($pdo, $from, $to, $includeOwing);

        if ($action === 'preview') {
            $preview = $counts;
        } elseif ($action === 'delete') {
            $paidOnly = $includeOwing ? '' : ' AND due_amount <= 0.004';
            $removed = ['invoices' => 0, 'stock' => 0, 'salary' => 0, 'transport' => 0];

            $pdo->beginTransaction();
            try {
                if ($doInvoices) {
                    // invoice_items and payments both cascade from invoices.
                    $st = $pdo->prepare("DELETE FROM invoices WHERE invoice_date BETWEEN ? AND ?$paidOnly");
                    $st->execute([$from, $to]);
                    $removed['invoices'] = $st->rowCount();
                }
                if ($doStock) {
                    $st = $pdo->prepare("DELETE FROM stock_adjustments WHERE DATE(adjusted_at) BETWEEN ? AND ?");
                    $st->execute([$from, $to]);
                    $removed['stock'] = $st->rowCount();
                }
                if ($doSalary) {
                    $st = $pdo->prepare("DELETE FROM salary_payments WHERE payment_date BETWEEN ? AND ?");
                    $st->execute([$from, $to]);
                    $removed['salary'] = $st->rowCount();
                }
                if ($doTransport) {
                    // Collect the ids first: transport_payments has no cascade, and
                    // once they are gone the "settled" test would no longer match.
                    $settled = $includeOwing ? '' : ' AND (te.charge_amount - COALESCE((SELECT SUM(tp.amount)
                                 FROM transport_payments tp WHERE tp.entry_id = te.id), 0)) <= 0.004';
                    $sel = $pdo->prepare("SELECT te.id FROM transport_entries te WHERE te.entry_date BETWEEN ? AND ?$settled");
                    $sel->execute([$from, $to]);
                    $ids = array_column($sel->fetchAll(), 'id');

                    foreach (array_chunk($ids, 500) as $chunk) {
                        $ph = implode(',', array_fill(0, count($chunk), '?'));
                        $pdo->prepare("DELETE FROM transport_payments WHERE entry_id IN ($ph)")->execute($chunk);
                        $st = $pdo->prepare("DELETE FROM transport_entries WHERE id IN ($ph)");
                        $st->execute($chunk);
                        $removed['transport'] += $st->rowCount();
                    }
                }
                $pdo->commit();
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }

            flash_set(t('clean_flash_done', $removed['invoices'], $removed['stock'], $removed['salary'], $removed['transport']));
            header('Location: ' . url('settings/cleanup.php'));
            exit;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('clean_page_title')) ?></h4>
  <a href="<?= url('settings/index.php') ?>" class="btn btn-outline-secondary"><?= e(t('clean_back')) ?></a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="alert alert-warning">
  <strong><?= e(t('clean_warning_title')) ?></strong><br>
  <?= e(t('clean_warning_body')) ?>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <form method="post" class="card p-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label"><?= e(t('clean_from')) ?></label>
          <input type="date" name="from" class="form-control" required value="<?= e($from) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label"><?= e(t('clean_to')) ?></label>
          <input type="date" name="to" class="form-control" required value="<?= e($to) ?>">
        </div>
      </div>

      <label class="form-label"><?= e(t('clean_what')) ?></label>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="t_invoices" id="t_invoices" value="1" <?= $doInvoices ? 'checked' : '' ?>>
        <label class="form-check-label" for="t_invoices"><?= e(t('clean_type_invoices')) ?></label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="t_stock" id="t_stock" value="1" <?= $doStock ? 'checked' : '' ?>>
        <label class="form-check-label" for="t_stock"><?= e(t('clean_type_stock')) ?></label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="t_salary" id="t_salary" value="1" <?= $doSalary ? 'checked' : '' ?>>
        <label class="form-check-label" for="t_salary"><?= e(t('clean_type_salary')) ?></label>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="t_transport" id="t_transport" value="1" <?= $doTransport ? 'checked' : '' ?>>
        <label class="form-check-label" for="t_transport"><?= e(t('clean_type_transport')) ?></label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="include_owing" id="include_owing" value="1" <?= $includeOwing ? 'checked' : '' ?>>
        <label class="form-check-label" for="include_owing"><?= e(t('clean_include_owing')) ?></label>
        <div class="form-text"><?= e(t('clean_include_owing_hint')) ?></div>
      </div>

      <div><button class="btn btn-primary"><?= e(t('clean_preview_button')) ?></button></div>
    </form>
  </div>

  <div class="col-md-6">
    <?php if ($preview !== null): ?>
      <?php
        $totalRows = ($doInvoices ? $preview['invoices'] + $preview['invoice_items'] + $preview['invoice_payments'] : 0)
                   + ($doStock ? $preview['stock'] : 0)
                   + ($doSalary ? $preview['salary'] : 0)
                   + ($doTransport ? $preview['transport'] + $preview['transport_payments'] : 0);
      ?>
      <div class="card p-4">
        <h5 class="mb-3"><?= e(t('clean_preview_title')) ?></h5>
        <p class="text-muted"><?= e(t('clean_preview_range', $from, $to)) ?></p>

        <div class="table-responsive">
        <table class="table table-sm mb-3">
          <thead><tr><th><?= e(t('clean_th_record')) ?></th><th class="text-end"><?= e(t('clean_th_rows')) ?></th></tr></thead>
          <tbody>
            <?php if ($doInvoices): ?>
              <tr><td><?= e(t('clean_type_invoices')) ?></td><td class="text-end"><?= (int)$preview['invoices'] ?></td></tr>
              <tr><td class="ps-4 text-muted"><?= e(t('clean_row_invoice_items')) ?></td><td class="text-end text-muted"><?= (int)$preview['invoice_items'] ?></td></tr>
              <tr><td class="ps-4 text-muted"><?= e(t('clean_row_invoice_payments')) ?></td><td class="text-end text-muted"><?= (int)$preview['invoice_payments'] ?></td></tr>
            <?php endif; ?>
            <?php if ($doStock): ?><tr><td><?= e(t('clean_type_stock')) ?></td><td class="text-end"><?= (int)$preview['stock'] ?></td></tr><?php endif; ?>
            <?php if ($doSalary): ?><tr><td><?= e(t('clean_type_salary')) ?></td><td class="text-end"><?= (int)$preview['salary'] ?></td></tr><?php endif; ?>
            <?php if ($doTransport): ?>
              <tr><td><?= e(t('clean_type_transport')) ?></td><td class="text-end"><?= (int)$preview['transport'] ?></td></tr>
              <tr><td class="ps-4 text-muted"><?= e(t('clean_row_transport_payments')) ?></td><td class="text-end text-muted"><?= (int)$preview['transport_payments'] ?></td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot><tr><th><?= e(t('clean_th_total')) ?></th><th class="text-end"><?= $totalRows ?></th></tr></tfoot>
        </table>
        </div>

        <?php if (!$includeOwing && (($doInvoices && $preview['invoices_kept'] > 0) || ($doTransport && $preview['transport_kept'] > 0))): ?>
          <div class="alert alert-info">
            <?= e(t('clean_kept_notice')) ?>
            <ul class="mb-0">
              <?php if ($doInvoices && $preview['invoices_kept'] > 0): ?><li><?= e(t('clean_kept_invoices', $preview['invoices_kept'])) ?></li><?php endif; ?>
              <?php if ($doTransport && $preview['transport_kept'] > 0): ?><li><?= e(t('clean_kept_transport', $preview['transport_kept'])) ?></li><?php endif; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($totalRows === 0): ?>
          <div class="alert alert-secondary mb-0"><?= e(t('clean_nothing_to_delete')) ?></div>
        <?php else: ?>
          <form method="post" onsubmit="return confirm('<?= e(t('clean_confirm_dialog', $totalRows)) ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="from" value="<?= e($from) ?>">
            <input type="hidden" name="to" value="<?= e($to) ?>">
            <?php if ($doInvoices): ?><input type="hidden" name="t_invoices" value="1"><?php endif; ?>
            <?php if ($doStock): ?><input type="hidden" name="t_stock" value="1"><?php endif; ?>
            <?php if ($doSalary): ?><input type="hidden" name="t_salary" value="1"><?php endif; ?>
            <?php if ($doTransport): ?><input type="hidden" name="t_transport" value="1"><?php endif; ?>
            <?php if ($includeOwing): ?><input type="hidden" name="include_owing" value="1"><?php endif; ?>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="confirm" id="confirm" value="1" required>
              <label class="form-check-label" for="confirm"><?= e(t('clean_confirm_label')) ?></label>
            </div>
            <button class="btn btn-danger"><?= e(t('clean_delete_button', $totalRows)) ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card p-4">
        <h5 class="mb-3"><?= e(t('clean_safe_title')) ?></h5>
        <p class="text-muted mb-2"><?= e(t('clean_safe_body')) ?></p>
        <ul class="text-muted mb-0" style="font-size:.9rem;">
          <li><?= e(t('clean_safe_master')) ?></li>
          <li><?= e(t('clean_safe_stock')) ?></li>
          <li><?= e(t('clean_safe_owing')) ?></li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
