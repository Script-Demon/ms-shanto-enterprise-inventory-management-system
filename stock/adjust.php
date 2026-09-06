<?php
require_once __DIR__ . '/../includes/auth.php';

$products = $pdo->query("SELECT id, name, sku, unit, stock_qty FROM products ORDER BY name")->fetchAll();
$selectedProductId = (int)($_GET['product_id'] ?? 0);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    $changeQty = (float)($_POST['change_qty'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        $error = t('stock_error_select_product');
    } elseif ($changeQty == 0) {
        $error = t('stock_error_nonzero');
    } elseif ($product['stock_qty'] + $changeQty < 0) {
        $error = t('stock_error_negative_result', qty($product['stock_qty']));
    } elseif (($reasonErr = too_long('stock_adjustments.reason', $reason, t('common_reason'))) !== null) {
        $error = $reasonErr;
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$changeQty, $productId]);
        $pdo->prepare("INSERT INTO stock_adjustments (product_id, change_qty, reason) VALUES (?,?,?)")->execute([$productId, $changeQty, $reason ?: null]);
        $pdo->commit();
        flash_set(t('flash_stock_updated'));
        header('Location: ' . url('stock/adjust.php'));
        exit;
    }
}

$recent = $pdo->query("SELECT sa.*, p.name AS product_name FROM stock_adjustments sa JOIN products p ON p.id = sa.product_id ORDER BY sa.id DESC LIMIT 20")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('stock_page_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row">
  <div class="col-md-5">
    <form method="post" class="card p-4">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= e(t('stock_field_product')) ?></label>
        <select name="product_id" class="form-select" required>
          <option value=""><?= e(t('stock_select_placeholder')) ?></option>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id']===$selectedProductId?'selected':'' ?>><?= e($p['name']) ?> (<?= e(t('stock_stock_label')) ?> <?= qty($p['stock_qty']) ?> <?= e($p['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('stock_field_qty_change')) ?></label>
        <input type="number" step="0.01" name="change_qty" class="form-control" required placeholder="e.g. 10 / -5">
        <div class="form-text"><?= e(t('stock_hint_qty_change')) ?></div>
      </div>
      <div class="mb-3"><label class="form-label"><?= e(t('stock_field_reason')) ?></label><input type="text" name="reason" class="form-control" placeholder="<?= e(t('stock_reason_placeholder')) ?>" maxlength="<?= field_max('stock_adjustments.reason') ?>"></div>
      <button class="btn btn-primary"><?= e(t('stock_apply_button')) ?></button>
    </form>
  </div>
  <div class="col-md-7">
    <h5><?= e(t('stock_recent_title')) ?></h5>
    <table class="table table-sm bg-white">
      <thead><tr><th><?= e(t('common_product')) ?></th><th><?= e(t('common_date')) ?></th><th><?= e(t('stock_th_change')) ?></th><th><?= e(t('common_reason')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= e($r['product_name']) ?></td>
          <td data-label="<?= e(t('common_date')) ?>"><?= e($r['adjusted_at']) ?></td>
          <td data-label="<?= e(t('stock_th_change')) ?>" class="<?= $r['change_qty']<0?'text-danger':'text-success' ?>"><?= ($r['change_qty']>0?'+':'') . qty($r['change_qty']) ?></td>
          <td data-label="<?= e(t('common_reason')) ?>"><?= e($r['reason']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="4" class="text-muted text-center py-3"><?= e(t('stock_no_adjustments')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
