<?php
require_once __DIR__ . '/../includes/auth.php';

// One search box covers all three identifying fields, so the shopkeeper can
// type whichever of them they happen to remember.
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM suppliers WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR company LIKE ? OR phone LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <h4 class="mb-0"><?= e(t('sup_page_title')) ?></h4>
  <a href="<?= url('suppliers/add.php') ?>" class="btn btn-primary"><?= e(t('sup_add_button')) ?></a>
</div>
<form class="row g-2 mb-3">
  <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="<?= e(t('sup_search_placeholder')) ?>" value="<?= e($search) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><?= e(t('sup_search_button')) ?></button></div>
  <?php if ($search !== ''): ?>
    <div class="col-md-2"><a href="<?= url('suppliers/list.php') ?>" class="btn btn-link w-100"><?= e(t('sup_clear_button')) ?></a></div>
  <?php endif; ?>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
  <thead><tr><th><?= e(t('sup_th_name')) ?></th><th><?= e(t('sup_th_company')) ?></th><th><?= e(t('sup_th_phone')) ?></th><th><?= e(t('sup_th_note')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($suppliers as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td data-label="<?= e(t('sup_th_company')) ?>"><?= e($s['company']) ?></td>
      <td data-label="<?= e(t('sup_th_phone')) ?>"><?= e($s['phone']) ?></td>
      <td data-label="<?= e(t('sup_th_note')) ?>"><?= e($s['note']) ?></td>
      <td class="table-actions">
        <a href="<?= url('suppliers/edit.php?id=' . $s['id']) ?>"><?= e(t('common_edit')) ?></a>
        <form method="post" action="<?= url('suppliers/delete.php') ?>" onsubmit="return confirm('<?= e(t('sup_confirm_delete')) ?>');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $s['id'] ?>">
          <button type="submit" class="btn btn-link text-danger p-0"><?= e(t('common_delete')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$suppliers): ?>
    <tr><td colspan="5" class="text-muted text-center py-4">
      <?= e($search === '' ? t('sup_no_suppliers') : t('sup_no_matches', $search)) ?>
    </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
