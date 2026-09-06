<?php
require_once __DIR__ . '/includes/bootstrap.php';

$count = $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
if ($count > 0) {
    die('Setup already completed. <a href="' . e(url('login.php')) . '">Go to login</a>.');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $tooLong = first_error(
        too_long('users.name', $name, t('setup_your_name')),
        too_long('users.username', $username, t('setup_username'))
    );

    if ($name === '' || $username === '' || strlen($password) < 6) {
        $error = t('setup_error_fill_all');
    } elseif ($tooLong !== null) {
        $error = $tooLong;
    } elseif ($password !== $confirm) {
        $error = t('setup_error_mismatch');
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        header('Location: ' . url('login.php'));
        exit;
    }
}

$__path = strtok($_SERVER['REQUEST_URI'], '?');
$__urlEn = $__path . '?' . http_build_query(array_merge($_GET, ['lang' => 'en']));
$__urlBn = $__path . '?' . http_build_query(array_merge($_GET, ['lang' => 'bn']));
$__curLang = $_SESSION['lang'] ?? 'bn';
?>
<!DOCTYPE html>
<html lang="<?= e($__curLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('setup_page_title')) ?> - <?= e($config['shop_name'] ?? 'Inventory System') ?></title>
<script>
(function () { try { var t = localStorage.getItem("shanto-theme"); if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t); } catch (e) {} })();
</script>
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="container" style="max-width:450px;">
  <div class="d-flex justify-content-end mb-2">
    <div class="btn-group btn-group-sm" role="group">
      <a href="<?= e($__urlBn) ?>" class="btn btn-<?= $__curLang === 'bn' ? 'secondary' : 'outline-secondary' ?>">বাংলা</a>
      <a href="<?= e($__urlEn) ?>" class="btn btn-<?= $__curLang === 'en' ? 'secondary' : 'outline-secondary' ?>">English</a>
    </div>
  </div>
  <div class="auth-brand">
    <?= brand_logo_html() ?>
    <h3 class="mb-0 text-center"><?= e(t('setup_title')) ?></h3>
  </div>
  <p class="text-muted text-center"><?= e(t('setup_subtitle')) ?></p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card p-4 shadow-sm">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label"><?= e(t('setup_your_name')) ?></label><input type="text" name="name" class="form-control" required maxlength="<?= field_max('users.name') ?>"></div>
    <div class="mb-3"><label class="form-label"><?= e(t('setup_username')) ?></label><input type="text" name="username" class="form-control" required maxlength="<?= field_max('users.username') ?>"></div>
    <div class="mb-3"><label class="form-label"><?= e(t('setup_password')) ?></label><input type="password" name="password" class="form-control" required minlength="6"></div>
    <div class="mb-3"><label class="form-label"><?= e(t('setup_confirm_password')) ?></label><input type="password" name="confirm" class="form-control" required minlength="6"></div>
    <button class="btn btn-primary w-100" type="submit"><?= e(t('setup_button')) ?></button>
  </form>
</div>
</body>
</html>
