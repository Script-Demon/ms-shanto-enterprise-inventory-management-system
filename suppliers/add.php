<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $tooLong = first_error(
        too_long('suppliers.name', $name, t('sup_field_name')),
        too_long('suppliers.company', $company, t('sup_field_company')),
        too_long('suppliers.phone', $phone, t('sup_field_phone')),
        too_long('suppliers.note', $note, t('sup_field_note'))
    );

    if ($name === '') {
        $error = t('sup_error_name_required');
    } elseif ($tooLong !== null) {
        $error = $tooLong;
    } else {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, company, phone, note) VALUES (?,?,?,?)");
        $stmt->execute([$name, $company ?: null, $phone ?: null, $note ?: null]);
        flash_set(t('flash_supplier_added'));
        header('Location: ' . url('suppliers/list.php'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('sup_add_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card p-4" style="max-width:500px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(t('sup_field_name')) ?></label><input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>" maxlength="<?= field_max('suppliers.name') ?>"></div>
  <div class="mb-3"><label class="form-label"><?= e(t('sup_field_company')) ?></label><input type="text" name="company" class="form-control" value="<?= e($_POST['company'] ?? '') ?>" maxlength="<?= field_max('suppliers.company') ?>"></div>
  <div class="mb-3"><label class="form-label"><?= e(t('sup_field_phone')) ?></label><input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" maxlength="<?= field_max('suppliers.phone') ?>"></div>
  <div class="mb-3"><label class="form-label"><?= e(t('sup_field_note')) ?></label><textarea name="note" class="form-control" maxlength="<?= field_max('suppliers.note') ?>"><?= e($_POST['note'] ?? '') ?></textarea></div>
  <div><button class="btn btn-primary"><?= e(t('sup_save_button')) ?></button> <a href="<?= url('suppliers/list.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
