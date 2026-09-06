<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . url('index.php'));
    exit;
}

$count = $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
if ($count == 0) {
    header('Location: ' . url('setup.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: ' . url('index.php'));
        exit;
    }
    $error = t('login_error_invalid');
}

$__curLang = $_SESSION['lang'] ?? 'bn';
?>
<!DOCTYPE html>
<html lang="<?= e($__curLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('login_page_title')) ?> - <?= e($config['shop_name'] ?? 'Inventory System') ?></title>
<script>
(function () { try { var t = localStorage.getItem("shanto-theme"); if (t === "dark" || t === "light") document.documentElement.setAttribute("data-theme", t); } catch (e) {} })();
</script>
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="container" style="max-width:400px;">
  <div class="auth-brand">
    <?= brand_logo_html() ?>
    <h3 class="mb-0 text-center"><?= e($config['shop_name'] ?? 'Inventory System') ?></h3>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card p-4 shadow-sm">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label"><?= e(t('login_username')) ?></label>
      <input type="text" name="username" class="form-control" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label"><?= e(t('login_password')) ?></label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= e(t('login_button')) ?></button>
  </form>
</div>
</body>
</html>
