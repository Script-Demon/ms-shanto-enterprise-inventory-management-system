<?php
$__path = strtok($_SERVER['REQUEST_URI'], '?');
$__urlEn = $__path . '?' . http_build_query(array_merge($_GET, ['lang' => 'en']));
$__urlBn = $__path . '?' . http_build_query(array_merge($_GET, ['lang' => 'bn']));
$__curLang = $_SESSION['lang'] ?? 'bn';

// Exact-page highlight for menu links
function nav_is($needle) {
    return strpos($_SERVER['PHP_SELF'], $needle) !== false;
}

// Which bottom-bar tab is active (mobile)
$__section = 'dashboard';
if (nav_is('/products/')) {
    $__section = 'products';
} elseif (nav_is('/invoices/create.php')) {
    $__section = 'new';
} elseif (nav_is('/invoices/')) {
    $__section = 'invoices';
} elseif (nav_is('/customers/') || nav_is('/suppliers/') || nav_is('/stock/') || nav_is('/reports/') || nav_is('/transport/')) {
    $__section = 'more';
}
$__brandMark = first_char($config['shop_name'] ?? 'S');

// Every navigation destination, in one place — the desktop sidebar and the
// mobile menu both render from this, so neither can drift out of sync.
$__navItems = [
    ['url' => 'index.php',               'icon' => 'home',    'label' => t('nav_dashboard'),    'match' => 'index.php'],
    ['url' => 'products/list.php',       'icon' => 'package', 'label' => t('nav_products'),     'match' => 'products/list.php'],
    ['url' => 'products/categories.php', 'icon' => 'tag',     'label' => t('nav_categories'),   'match' => 'products/categories.php'],
    ['url' => 'stock/adjust.php',        'icon' => 'layers',  'label' => t('nav_adjust_stock'), 'match' => 'stock/adjust.php'],
    ['url' => 'customers/list.php',      'icon' => 'users',   'label' => t('nav_customers'),    'match' => 'customers/'],
    ['url' => 'suppliers/list.php',      'icon' => 'store',   'label' => t('nav_suppliers'),    'match' => 'suppliers/'],
    ['url' => 'invoices/list.php',       'icon' => 'receipt', 'label' => t('nav_invoices'),     'match' => 'invoices/list.php'],
    ['url' => 'salary/index.php',        'icon' => 'wallet',  'label' => t('nav_salary'),       'match' => 'salary/'],
    ['url' => 'transport/index.php',     'icon' => 'truck',   'label' => t('nav_transport'),    'match' => 'transport/'],
    ['url' => 'reports/sales.php',       'icon' => 'chart',   'label' => t('nav_reports'),      'match' => 'reports/'],
    ['url' => 'settings/index.php',      'icon' => 'settings','label' => t('nav_settings'),     'match' => 'settings/'],
];
?>
<!DOCTYPE html>
<html lang="<?= e($__curLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?= e($config['shop_name'] ?? 'Inventory System') ?></title>
<script>
/* Applies the saved theme before first paint so the page never flashes
   the wrong colours. No saved choice = light. */
(function () {
  try {
    var t = localStorage.getItem('shanto-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">

<!-- Installable app (add to home screen / desktop) -->
<link rel="manifest" href="<?= url('manifest.php') ?>">
<link rel="apple-touch-icon" href="<?= url('icon.php?s=192') ?>">
<link rel="icon" type="image/png" href="<?= url('icon.php?s=192') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($config['shop_name'] ?? 'Inventory') ?>">
<meta name="application-name" content="<?= e($config['shop_name'] ?? 'Inventory') ?>">
</head>
<body>
<?php if (!empty($_SESSION['user_id'])): ?>

<!-- ===== Desktop: left sidebar ===== -->
<aside class="sidebar no-print">
  <a class="brand sidebar-brand" href="<?= url('index.php') ?>">
    <?= brand_logo_html() ?>
    <span class="brand-name"><?= e($config['shop_name'] ?? 'Inventory') ?></span>
  </a>

  <a class="sidebar-cta" href="<?= url('invoices/create.php') ?>">
    <?= icon('plus', 'ico ico-sm') ?><span><?= e(t('nav_new_invoice')) ?></span>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($__navItems as $item): ?>
      <a class="side-link <?= nav_is($item['match']) ? 'is-active' : '' ?>" href="<?= url($item['url']) ?>">
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

</aside>

<!-- ===== Desktop: top-right utility bar ===== -->
<header class="topbar no-print">
  <button type="button" class="icon-btn theme-toggle" aria-label="<?= e(t('nav_theme')) ?>" title="<?= e(t('nav_theme')) ?>">
    <?= icon('moon', 'ico ico-moon') ?><?= icon('sun', 'ico ico-sun') ?>
  </button>
  <div class="lang-toggle">
    <a href="<?= e($__urlBn) ?>" class="<?= $__curLang === 'bn' ? 'is-active' : '' ?>">বাংলা</a>
    <a href="<?= e($__urlEn) ?>" class="<?= $__curLang === 'en' ? 'is-active' : '' ?>">English</a>
  </div>
  <div class="topbar-user">
    <span class="user-avatar"><?= e(first_char($_SESSION['user_name'] ?? 'U')) ?></span>
    <span class="user-name"><?= e($_SESSION['user_name'] ?? '') ?></span>
    <a class="icon-btn" href="<?= url('logout.php') ?>" title="<?= e(t('nav_logout')) ?>"><?= icon('logout', 'ico ico-sm') ?></a>
  </div>
</header>

<!-- ===== Mobile: top bar ===== -->
<header class="app-bar no-print">
  <div class="app-bar-inner">
    <a class="brand" href="<?= url('index.php') ?>">
      <?= brand_logo_html() ?>
      <span class="brand-name"><?= e($config['shop_name'] ?? 'Inventory') ?></span>
    </a>
    <button type="button" class="icon-btn theme-toggle menu-toggle" aria-label="<?= e(t('nav_theme')) ?>" title="<?= e(t('nav_theme')) ?>">
      <?= icon('moon', 'ico ico-moon') ?><?= icon('sun', 'ico ico-sun') ?>
    </button>
  </div>
</header>

<!-- ===== Mobile: full navigation drawer ===== -->
<div class="drawer-backdrop no-print" id="drawerBackdrop" hidden></div>
<aside class="nav-drawer no-print" id="navDrawer" hidden aria-label="<?= e(t('nav_menu')) ?>">
  <div class="drawer-head">
    <span class="drawer-title"><?= e(t('nav_menu')) ?></span>
    <button type="button" class="icon-btn" id="drawerClose" aria-label="<?= e(t('common_cancel')) ?>"><?= icon('close') ?></button>
  </div>

  <nav class="drawer-list">
    <a class="drawer-item <?= nav_is('invoices/create.php') ? 'is-active' : '' ?>" href="<?= url('invoices/create.php') ?>">
      <?= icon('plus') ?><span><?= e(t('nav_new_invoice')) ?></span>
    </a>
    <?php foreach ($__navItems as $item): ?>
      <a class="drawer-item <?= nav_is($item['match']) ? 'is-active' : '' ?>" href="<?= url($item['url']) ?>">
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="drawer-divider"></div>

  <div class="drawer-item drawer-lang">
    <?= icon('globe') ?>
    <span class="lang-toggle">
      <a href="<?= e($__urlBn) ?>" class="<?= $__curLang === 'bn' ? 'is-active' : '' ?>">বাংলা</a>
      <a href="<?= e($__urlEn) ?>" class="<?= $__curLang === 'en' ? 'is-active' : '' ?>">English</a>
    </span>
  </div>

  <a class="drawer-item is-danger" href="<?= url('logout.php') ?>"><?= icon('logout') ?><span><?= e(t('nav_logout')) ?></span></a>

  <div class="drawer-user"><?= e($_SESSION['user_name'] ?? '') ?></div>
</aside>

<!-- ===== Mobile: bottom tab bar ===== -->
<nav class="bottom-nav no-print">
  <a class="tab <?= $__section === 'dashboard' ? 'is-active' : '' ?>" href="<?= url('index.php') ?>">
    <?= icon('home') ?><span><?= e(t('nav_dashboard')) ?></span>
  </a>
  <a class="tab <?= $__section === 'products' ? 'is-active' : '' ?>" href="<?= url('products/list.php') ?>">
    <?= icon('package') ?><span><?= e(t('nav_products')) ?></span>
  </a>
  <a class="tab tab-fab" href="<?= url('invoices/create.php') ?>" aria-label="<?= e(t('nav_new_invoice')) ?>">
    <span class="fab"><?= icon('plus') ?></span>
  </a>
  <a class="tab <?= $__section === 'invoices' ? 'is-active' : '' ?>" href="<?= url('invoices/list.php') ?>">
    <?= icon('receipt') ?><span><?= e(t('nav_invoices')) ?></span>
  </a>
  <button type="button" class="tab menu-open <?= $__section === 'more' ? 'is-active' : '' ?>">
    <?= icon('menu') ?><span><?= e(t('nav_menu')) ?></span>
  </button>
</nav>
<?php endif; ?>
<main class="app-main">
<?php $__flash = flash_get(); if ($__flash): ?>
  <div class="alert alert-<?= e($__flash['type']) ?>"><?= e($__flash['msg']) ?></div>
<?php endif; ?>
