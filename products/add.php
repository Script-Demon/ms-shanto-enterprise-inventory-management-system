<?php
require_once __DIR__ . '/../includes/auth.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $categoryId = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $unit = trim($_POST['unit'] ?? '') ?: 'pcs';
    $costPrice = (float)($_POST['cost_price'] ?? 0);
    $sellPrice = (float)($_POST['sell_price'] ?? 0);
    $stockQty = (float)($_POST['stock_qty'] ?? 0);
    $reorderLevel = (float)($_POST['reorder_level'] ?? 0);

    // The picture is optional, so "no file chosen" is not an error here.
    $image = null;
    $imageErr = store_uploaded_image($_FILES['image'] ?? null, product_image_dir(), 'p_', $image);

    $tooLong = first_error(
        too_long('products.name', $name, t('prod_field_name')),
        too_long('products.sku', $sku, t('prod_field_sku')),
        too_long('products.unit', $unit, t('prod_field_unit'))
    );

    if ($imageErr !== null && $imageErr !== 'none') {
        $error = t('prod_err_image_' . $imageErr);
    } elseif ($name === '') {
        $error = t('prod_error_name_required');
    } elseif ($tooLong !== null) {
        $error = $tooLong;
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, unit, cost_price, sell_price, stock_qty, reorder_level, image) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $sku ?: null, $categoryId, $unit, $costPrice, $sellPrice, $stockQty, $reorderLevel, $image]);
        flash_set(t('flash_product_added'));
        header('Location: ' . url('products/list.php'));
        exit;
    }

    // Something else on the form failed after the file was already moved into
    // place. The browser cannot re-send it, so don't leave it orphaned on disk.
    if ($image !== null) {
        delete_uploaded_image(product_image_dir(), $image);
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('prod_add_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card p-4" style="max-width:650px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(t('prod_field_name')) ?></label><input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>" maxlength="<?= field_max('products.name') ?>"></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label"><?= e(t('prod_field_sku')) ?></label><input type="text" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? '') ?>" maxlength="<?= field_max('products.sku') ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label"><?= e(t('prod_field_category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(t('common_none')) ?></option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('prod_field_image')) ?></label>
    <div class="image-field">
      <div class="image-preview" id="imagePreview">
        <span class="image-placeholder"><?= icon('image') ?><span><?= e(t('prod_no_image')) ?></span></span>
      </div>
      <div class="image-field-input">
        <input type="file" name="image" id="imageInput" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="form-text"><?= e(t('prod_image_hint')) ?></div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-3 mb-3"><label class="form-label"><?= e(t('prod_field_unit')) ?></label><input type="text" name="unit" class="form-control" value="pcs" maxlength="<?= field_max('products.unit') ?>"></div>
    <div class="col-md-3 mb-3"><label class="form-label"><?= e(t('prod_field_cost_price')) ?></label><input type="number" step="0.01" name="cost_price" class="form-control" value="0"></div>
    <div class="col-md-3 mb-3"><label class="form-label"><?= e(t('prod_field_sell_price')) ?></label><input type="number" step="0.01" name="sell_price" class="form-control" value="0"></div>
    <div class="col-md-3 mb-3"><label class="form-label"><?= e(t('prod_field_opening_stock')) ?></label><input type="number" step="0.01" name="stock_qty" class="form-control" value="0"></div>
  </div>
  <div class="mb-3 col-md-4"><label class="form-label"><?= e(t('prod_field_reorder_level')) ?></label><input type="number" step="0.01" name="reorder_level" class="form-control" value="0"></div>
  <div><button class="btn btn-primary"><?= e(t('prod_save_button')) ?></button> <a href="<?= url('products/list.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= url('assets/js/image-preview.js') ?>"></script>
