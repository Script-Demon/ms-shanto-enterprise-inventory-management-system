<?php
require_once __DIR__ . '/../includes/auth.php';

$errors = [];

/* ---------------- Shop information ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'shop') {
    verify_csrf();
    $shopName = trim($_POST['shop_name'] ?? '');
    $shopAddress = trim($_POST['shop_address'] ?? '');
    $shopPhone = trim($_POST['shop_phone'] ?? '');
    $currency = trim($_POST['currency'] ?? '');

    if ($shopName === '') {
        $errors[] = t('set_err_name_required');
    } else {
        setting_save($pdo, 'shop_name', $shopName);
        setting_save($pdo, 'shop_address', $shopAddress);
        setting_save($pdo, 'shop_phone', $shopPhone);
        setting_save($pdo, 'currency', $currency !== '' ? $currency : '৳');
        flash_set(t('flash_settings_saved'));
        header('Location: ' . url('settings/index.php'));
        exit;
    }
}

/* ---------------- Logo ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logo') {
    verify_csrf();
    $file = $_FILES['logo'] ?? null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = t('set_err_logo_none');
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = t('set_err_logo_upload');
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = t('set_err_logo_size');
    } else {
        // Trust the image itself, not the filename or the browser's content type.
        $info = @getimagesize($file['tmp_name']);
        $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($allowed[$info[2]])) {
            $errors[] = t('set_err_logo_type');
        } else {
            if (!is_dir(logo_dir()) && !@mkdir(logo_dir(), 0755, true)) {
                $errors[] = t('set_err_logo_dir');
            } else {
                $ext = $allowed[$info[2]];
                $newName = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (!@move_uploaded_file($file['tmp_name'], logo_dir() . '/' . $newName)) {
                    $errors[] = t('set_err_logo_dir');
                } else {
                    $old = $config['logo_file'] ?? '';
                    if ($old !== '' && is_file(logo_dir() . '/' . $old)) {
                        @unlink(logo_dir() . '/' . $old);
                    }
                    setting_save($pdo, 'logo_file', $newName);
                    flash_set(t('flash_logo_updated'));
                    header('Location: ' . url('settings/index.php'));
                    exit;
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logo_remove') {
    verify_csrf();
    $old = $config['logo_file'] ?? '';
    if ($old !== '' && is_file(logo_dir() . '/' . $old)) {
        @unlink(logo_dir() . '/' . $old);
    }
    setting_save($pdo, 'logo_file', '');
    flash_set(t('flash_logo_removed'));
    header('Location: ' . url('settings/index.php'));
    exit;
}

/* ---------------- Login account ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'account') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = t('set_err_current_password');
    } elseif ($name === '' || $username === '') {
        $errors[] = t('set_err_account_required');
    } elseif (($tooLong = first_error(
        too_long('users.name', $name, t('set_display_name')),
        too_long('users.username', $username, t('set_username'))
    )) !== null) {
        $errors[] = $tooLong;
    } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
        $errors[] = t('set_err_password_short');
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $errors[] = t('setup_error_mismatch');
    } else {
        $dup = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $dup->execute([$username, $_SESSION['user_id']]);
        if ($dup->fetch()) {
            $errors[] = t('set_err_username_taken');
        } else {
            if ($newPassword !== '') {
                $upd = $pdo->prepare('UPDATE users SET name = ?, username = ?, password_hash = ? WHERE id = ?');
                $upd->execute([$name, $username, password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            } else {
                $upd = $pdo->prepare('UPDATE users SET name = ?, username = ? WHERE id = ?');
                $upd->execute([$name, $username, $_SESSION['user_id']]);
            }
            $_SESSION['user_name'] = $name;
            flash_set(t('flash_account_updated'));
            header('Location: ' . url('settings/index.php'));
            exit;
        }
    }
}

$meStmt = $pdo->prepare('SELECT name, username FROM users WHERE id = ?');
$meStmt->execute([$_SESSION['user_id']]);
$me = $meStmt->fetch() ?: ['name' => '', 'username' => ''];

$currentLogo = $config['logo_file'] ?? '';
$hasLogo = $currentLogo !== '' && is_file(logo_dir() . '/' . $currentLogo);

include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><?= e(t('set_page_title')) ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="row g-3">
  <!-- Shop information -->
  <div class="col-md-6">
    <form method="post" class="card p-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="shop">
      <h5 class="mb-3"><?= e(t('set_shop_section')) ?></h5>

      <div class="mb-3">
        <label class="form-label"><?= e(t('set_shop_name')) ?></label>
        <input type="text" name="shop_name" class="form-control" required value="<?= e($config['shop_name'] ?? '') ?>">
        <div class="form-text"><?= e(t('set_shop_name_hint')) ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('set_shop_address')) ?></label>
        <input type="text" name="shop_address" class="form-control" value="<?= e($config['shop_address'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('set_shop_phone')) ?></label>
        <input type="text" name="shop_phone" class="form-control" value="<?= e($config['shop_phone'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('set_currency')) ?></label>
        <input type="text" name="currency" class="form-control" maxlength="5" value="<?= e($config['currency'] ?? '৳') ?>">
      </div>
      <div><button class="btn btn-primary"><?= e(t('set_save_shop')) ?></button></div>
    </form>
  </div>

  <div class="col-md-6">
    <!-- Logo -->
    <form method="post" enctype="multipart/form-data" class="card p-4 mb-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="logo">
      <h5 class="mb-3"><?= e(t('set_logo_section')) ?></h5>

      <div class="logo-preview mb-3">
        <?php if ($hasLogo): ?>
          <img src="<?= e(url('assets/uploads/' . $currentLogo)) ?>" alt="<?= e($config['shop_name'] ?? '') ?>">
        <?php else: ?>
          <span class="logo-placeholder"><?= icon('image') ?><span><?= e(t('set_no_logo')) ?></span></span>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= e(t('set_logo_upload')) ?></label>
        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" required>
        <div class="form-text"><?= e(t('set_logo_hint')) ?></div>
      </div>
      <div><button class="btn btn-primary"><?= e(t('set_logo_save')) ?></button></div>
    </form>

    <?php if ($hasLogo): ?>
    <form method="post" onsubmit="return confirm('<?= e(t('set_logo_remove_confirm')) ?>');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="logo_remove">
      <button class="btn btn-outline-danger"><?= e(t('set_logo_remove')) ?></button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Install as an app -->
  <div class="col-md-6">
    <div class="card p-4">
      <h5 class="mb-3"><?= e(t('set_install_section')) ?></h5>
      <p class="text-muted mb-3" style="font-size:.88rem;"><?= e(t('set_install_desc')) ?></p>

      <div class="install-preview mb-3">
        <img src="<?= e(url('icon.php?s=192')) ?>" alt="" width="60" height="60">
        <div>
          <div class="install-app-name"><?= e($config['shop_name'] ?? '') ?></div>
          <div class="text-muted" style="font-size:.8rem;"><?= e(t('set_install_icon_note')) ?></div>
        </div>
      </div>

      <button type="button" class="btn btn-primary" id="installBtn" disabled><?= e(t('set_install_button')) ?></button>
      <div class="form-text" id="installHint"><?= e(t('set_install_checking')) ?></div>
    </div>
  </div>

  <!-- Delete old data -->
  <div class="col-md-6">
    <div class="card p-4">
      <h5 class="mb-3"><?= e(t('clean_section')) ?></h5>
      <p class="text-muted mb-3" style="font-size:.88rem;"><?= e(t('clean_section_desc')) ?></p>
      <div><a href="<?= url('settings/cleanup.php') ?>" class="btn btn-outline-danger"><?= e(t('clean_open_button')) ?></a></div>
    </div>
  </div>

  <!-- Login account -->
  <div class="col-md-6">
    <form method="post" class="card p-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="account">
      <h5 class="mb-3"><?= e(t('set_account_section')) ?></h5>

      <div class="mb-3">
        <label class="form-label"><?= e(t('set_display_name')) ?></label>
        <input type="text" name="name" class="form-control" required value="<?= e($me['name']) ?>" maxlength="<?= field_max('users.name') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('set_username')) ?></label>
        <input type="text" name="username" class="form-control" required value="<?= e($me['username']) ?>" maxlength="<?= field_max('users.username') ?>" autocomplete="username">
      </div>

      <div class="mb-3">
        <label class="form-label"><?= e(t('set_new_password')) ?></label>
        <input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password">
        <div class="form-text"><?= e(t('set_password_hint')) ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(t('set_confirm_password')) ?></label>
        <input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password">
      </div>

      <div class="mb-3">
        <label class="form-label"><?= e(t('set_current_password')) ?></label>
        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        <div class="form-text"><?= e(t('set_current_password_hint')) ?></div>
      </div>

      <div><button class="btn btn-primary"><?= e(t('set_save_account')) ?></button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
/* Install button. Runs after footer.php has captured Chrome's install prompt. */
(function () {
  var btn = document.getElementById('installBtn');
  var hint = document.getElementById('installHint');
  if (!btn || !hint) return;

  var TXT = {
    ready: <?= json_encode(t('set_install_ready')) ?>,
    done: <?= json_encode(t('set_install_done')) ?>,
    ios: <?= json_encode(t('set_install_ios')) ?>,
    unavailable: <?= json_encode(t('set_install_unavailable')) ?>,
    insecure: <?= json_encode(t('set_install_https')) ?>
  };

  var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var isSecure = window.isSecureContext;

  function refresh() {
    if (isStandalone || (window.APP_INSTALL && window.APP_INSTALL.installed)) {
      btn.disabled = true;
      hint.textContent = TXT.done;
      return;
    }
    if (!isSecure) {
      btn.disabled = true;
      hint.textContent = TXT.insecure;
      return;
    }
    if (window.APP_INSTALL && window.APP_INSTALL.prompt) {
      btn.disabled = false;
      hint.textContent = TXT.ready;
      return;
    }
    btn.disabled = true;
    hint.textContent = isIOS ? TXT.ios : TXT.unavailable;
  }

  document.addEventListener('app-install-ready', refresh);
  document.addEventListener('app-installed', refresh);

  btn.addEventListener('click', function () {
    var p = window.APP_INSTALL && window.APP_INSTALL.prompt;
    if (!p) return;
    p.prompt();
    p.userChoice.then(function () {
      window.APP_INSTALL.prompt = null;
      refresh();
    });
  });

  refresh();
})();
</script>
