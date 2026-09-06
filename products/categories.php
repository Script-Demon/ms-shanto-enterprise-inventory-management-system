<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        // INSERT IGNORE would quietly truncate an over-long name, so check first.
        $error = too_long('categories.name', $name, t('cat_th_name'));
        if ($error === null && $name !== '') {
            $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            flash_set(t('flash_category_added'));
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        flash_set(t('flash_category_deleted'));
    }
    if ($error === null) {
        header('Location: ' . url('products/categories.php'));
        exit;
    }
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('cat_page_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row">
  <div class="col-md-5">
    <form method="post" class="card p-3 mb-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label class="form-label"><?= e(t('cat_new_category')) ?></label>
      <div class="input-group">
        <input type="text" name="name" class="form-control" required maxlength="<?= field_max('categories.name') ?>">
        <button class="btn btn-primary"><?= e(t('cat_add_button')) ?></button>
      </div>
    </form>
  </div>
  <div class="col-md-7">
    <table class="table table-hover bg-white">
      <thead><tr><th><?= e(t('cat_th_name')) ?></th><th><?= e(t('cat_th_products')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td data-label="<?= e(t('cat_th_products')) ?>"><?= (int)$c['product_count'] ?></td>
          <td>
            <form method="post" onsubmit="return confirm('<?= e(t('cat_confirm_delete')) ?>');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-link text-danger p-0" <?= $c['product_count']>0 ? 'disabled title="' . e(t('cat_has_products_title')) . '"' : '' ?>><?= e(t('common_delete')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$categories): ?><tr><td colspan="3" class="text-muted text-center py-3"><?= e(t('cat_no_categories')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
