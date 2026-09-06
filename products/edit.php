<?php
require_once __DIR__ . '/../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    flash_set(t('flash_product_not_found'), 'danger');
    header('Location: ' . url('products/list.php'));
    exit;
}

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
    $reorderLevel = (float)($_POST['reorder_level'] ?? 0);

    // Uploading a new picture replaces the old one; ticking "remove" clears it.
    // An upload wins if somehow both arrive together.
    $newImage = null;
    $imageErr = store_uploaded_image($_FILES['image'] ?? null, product_image_dir(), 'p_', $newImage);

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
        $image = $product['image'];
        if ($newImage !== null) {
            delete_uploaded_image(product_image_dir(), $image);
            $image = $newImage;
        } elseif (isset($_POST['remove_image'])) {
            delete_uploaded_image(product_image_dir(), $image);
            $image = null;
        }

        $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category_id=?, unit=?, cost_price=?, sell_price=?, reorder_level=?, image=? WHERE id=?");
        $stmt->execute([$name, $sku ?: null, $categoryId, $unit, $costPrice, $sellPrice, $reorderLevel, $image, $id]);
        flash_set(t('flash_product_updated'));
        header('Location: ' . url('products/list.php'));
        exit;
    }

    // The form is about to be redrawn and the browser cannot re-send the file,
    // so a picture stored before the failure would be orphaned on disk.
    if ($newImage !== null) {
        delete_uploaded_image(product_image_dir(), $newImage);
    }
}

$hasImage = (string)$product['image'] !== '' && is_file(product_image_dir() . '/' . $product['image']);

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('prod_edit_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card p-4" style="max-width:650px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(t('prod_field_name')) ?></label><input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? $product['name']) ?>" maxlength="<?= field_max('products.name') ?>"></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label"><?= e(t('prod_field_sku')) ?></label><input type="text" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? $product['sku']) ?>" maxlength="<?= field_max('products.sku') ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label"><?= e(t('prod_field_category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(t('common_none')) ?></option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $c['id']==$product['category_id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label"><?= e(t('prod_field_image')) ?></label>
    <div class="image-field">
      <div class="image-preview" id="imagePreview">
        <?php if ($hasImage): ?>
          <img src="<?= e(url('assets/uploads/products/' . $product['image'])) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
          <span class="image-placeholder"><?= icon('image') ?><span><?= e(t('prod_no_image')) ?></span></span>
        <?php endif; ?>
      </div>
      <div class="image-field-input">
        <input type="file" name="image" id="imageInput" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="form-text"><?= e($hasImage ? t('prod_image_replace_hint') : t('prod_image_hint')) ?></div>
        <?php if ($hasImage): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="remove_image" id="removeImage" value="1">
            <label class="form-check-label" for="removeImage"><?= e(t('prod_image_remove')) ?></label>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label"><?= e(t('prod_field_unit')) ?></label><input type="text" name="unit" class="form-control" value="<?= e($product['unit']) ?>" maxlength="<?= field_max('products.unit') ?>"></div>
    <div class="col-md-4 mb-3"><label class="form-label"><?= e(t('prod_field_cost_price')) ?></label><input type="number" step="0.01" name="cost_price" class="form-control" value="<?= e($product['cost_price']) ?>"></div>
    <div class="col-md-4 mb-3"><label class="form-label"><?= e(t('prod_field_sell_price')) ?></label><input type="number" step="0.01" name="sell_price" class="form-control" value="<?= e($product['sell_price']) ?>"></div>
  </div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label"><?= e(t('prod_field_reorder_level')) ?></label><input type="number" step="0.01" name="reorder_level" class="form-control" value="<?= e($product['reorder_level']) ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label"><?= e(t('prod_field_current_stock')) ?></label>
      <input type="text" class="form-control" value="<?= e(qty($product['stock_qty'])) ?> <?= e($product['unit']) ?>" disabled>
      <div class="form-text"><?= sprintf(e(t('prod_hint_adjust_stock')), '<a href="' . url('stock/adjust.php?product_id=' . $product['id']) . '">' . e(t('prod_hint_adjust_stock_link')) . '</a>') ?></div>
    </div>
  </div>
  <div><button class="btn btn-primary"><?= e(t('prod_update_button')) ?></button> <a href="<?= url('products/list.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= url('assets/js/image-preview.js') ?>"></script>
