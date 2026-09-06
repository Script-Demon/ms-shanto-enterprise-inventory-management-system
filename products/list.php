<?php
require_once __DIR__ . '/../includes/auth.php';

$search = trim($_GET['q'] ?? '');
$categoryId = $_GET['category'] ?? '';

$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId !== '') {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}
$sql .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('prod_page_title')) ?></h4>
  <a href="<?= url('products/add.php') ?>" class="btn btn-primary"><?= e(t('prod_add_button')) ?></a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="<?= e(t('prod_search_placeholder')) ?>" value="<?= e($search) ?>"></div>
  <div class="col-md-3">
    <select name="category" class="form-select">
      <option value=""><?= e(t('prod_all_categories')) ?></option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><?= e(t('prod_filter_button')) ?></button></div>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr><th class="th-thumb"><?= e(t('prod_th_image')) ?></th><th><?= e(t('prod_th_name')) ?></th><th><?= e(t('prod_th_sku')) ?></th><th><?= e(t('prod_th_category')) ?></th><th><?= e(t('prod_th_unit')) ?></th><th><?= e(t('prod_th_cost')) ?></th><th><?= e(t('prod_th_sell_price')) ?></th><th><?= e(t('prod_th_stock')) ?></th><th><?= e(t('prod_th_reorder_lvl')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr class="<?= $p['stock_qty'] <= $p['reorder_level'] ? 'low-stock' : '' ?>">
      <td class="td-thumb" data-label="<?= e(t('prod_th_image')) ?>">
        <?php if ((string)$p['image'] !== '' && is_file(product_image_dir() . '/' . $p['image'])): ?>
          <img class="product-thumb" src="<?= e(url('assets/uploads/products/' . $p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <?php else: ?>
          <span class="product-thumb product-thumb-empty"><?= icon('image') ?></span>
        <?php endif; ?>
      </td>
      <td><?= e($p['name']) ?></td>
      <td data-label="<?= e(t('prod_th_sku')) ?>"><?= e($p['sku']) ?></td>
      <td data-label="<?= e(t('prod_th_category')) ?>"><?= e($p['category_name']) ?></td>
      <td data-label="<?= e(t('prod_th_unit')) ?>"><?= e($p['unit']) ?></td>
      <td data-label="<?= e(t('prod_th_cost')) ?>"><?= money($p['cost_price']) ?></td>
      <td data-label="<?= e(t('prod_th_sell_price')) ?>"><?= money($p['sell_price']) ?></td>
      <td data-label="<?= e(t('prod_th_stock')) ?>"><?= qty($p['stock_qty']) ?></td>
      <td data-label="<?= e(t('prod_th_reorder_lvl')) ?>"><?= qty($p['reorder_level']) ?></td>
      <td class="table-actions">
        <a href="<?= url('products/edit.php?id=' . $p['id']) ?>"><?= e(t('common_edit')) ?></a>
        <form method="post" action="<?= url('products/delete.php') ?>" onsubmit="return confirm('<?= e(t('prod_confirm_delete')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-link text-danger p-0"><?= e(t('common_delete')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="10" class="text-muted text-center py-4"><?= e(t('prod_no_products')) ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
