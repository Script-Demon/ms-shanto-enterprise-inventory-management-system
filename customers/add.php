<?php
require_once __DIR__ . '/../includes/auth.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $tooLong = first_error(
        too_long('customers.name', $name, t('cust_field_name')),
        too_long('customers.phone', $phone, t('cust_field_phone')),
        too_long('customers.address', $address, t('cust_field_address'))
    );

    if ($name === '') {
        $error = t('cust_error_name_required');
    } elseif ($tooLong !== null) {
        $error = $tooLong;
    } else {
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?,?,?)");
        $stmt->execute([$name, $phone ?: null, $address ?: null]);
        flash_set(t('flash_customer_added'));
        header('Location: ' . url('customers/list.php'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('cust_add_title')) ?></h4>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card p-4" style="max-width:500px;">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(t('cust_field_name')) ?></label><input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>" maxlength="<?= field_max('customers.name') ?>"></div>
  <div class="mb-3"><label class="form-label"><?= e(t('cust_field_phone')) ?></label><input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? '') ?>" maxlength="<?= field_max('customers.phone') ?>"></div>
  <div class="mb-3"><label class="form-label"><?= e(t('cust_field_address')) ?></label><textarea name="address" class="form-control" maxlength="<?= field_max('customers.address') ?>"><?= e($_POST['address'] ?? '') ?></textarea></div>
  <div><button class="btn btn-primary"><?= e(t('cust_save_button')) ?></button> <a href="<?= url('customers/list.php') ?>" class="btn btn-link"><?= e(t('common_cancel')) ?></a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>
