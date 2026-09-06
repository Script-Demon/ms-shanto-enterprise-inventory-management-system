<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';   // provides $pdo, $config
require_once __DIR__ . '/functions.php';

// Branding lives in the DB (editable from Settings) and overrides config.php.
// Only these keys may be overridden — never DB credentials or base_path.
$__settingKeys = ['shop_name', 'shop_address', 'shop_phone', 'currency', 'logo_file'];
try {
    foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $__row) {
        if (in_array($__row['setting_key'], $__settingKeys, true) && $__row['setting_value'] !== null && $__row['setting_value'] !== '') {
            $config[$__row['setting_key']] = $__row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // settings table not created yet — fall back to config.php values
}

// Language: বাংলা (bn) is the default; ?lang=bn|en switches and remembers the choice.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'], ['bn', 'en'], true)) {
    $_SESSION['lang'] = 'bn';
}
$translations = require __DIR__ . '/../lang/' . $_SESSION['lang'] . '.php';

/* ---------------- Error handling ----------------
   A raw PHP error must never reach the browser: the stack trace carries the
   server's absolute file paths. Details go to the host's error log instead,
   and the visitor gets a plain apology page. Set 'debug' => true in
   config/config.php while developing to see the real message on screen. */
$__debug = !empty($config['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $__debug ? '1' : '0');
ini_set('log_errors', '1');

// API endpoints answer in JSON, so an HTML error page would break the caller.
function __error_is_api() {
    return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
}

function __error_render($detail) {
    global $__debug;

    if (!headers_sent()) {
        http_response_code(500);
    }
    if (__error_is_api()) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => $__debug ? $detail : t('err_unexpected')]);
        return;
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e(t('err_unexpected_title')) . '</title>'
       . '<style>body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;'
       . 'display:grid;place-items:center;min-height:100vh;background:#f4f5f9;color:#131722}'
       . '.box{max-width:520px;padding:2rem;background:#fff;border:1px solid #e3e6ee;'
       . 'border-radius:12px;box-shadow:0 4px 12px rgba(19,23,34,.06)}'
       . 'h1{font-size:1.15rem;margin:0 0 .6rem}p{margin:0 0 1rem;color:#5b6379;line-height:1.5}'
       . 'a{color:#4f46e5}pre{white-space:pre-wrap;background:#f4f5f9;padding:.75rem;'
       . 'border-radius:8px;font-size:.8rem;overflow:auto}</style></head><body><div class="box">'
       . '<h1>' . e(t('err_unexpected_title')) . '</h1>'
       . '<p>' . e(t('err_unexpected')) . '</p>'
       . ($__debug ? '<pre>' . e($detail) . '</pre>' : '')
       . '<p><a href="' . e(url('index.php')) . '">' . e(t('err_back_home')) . '</a></p>'
       . '</div></body></html>';
}

set_exception_handler(function ($ex) {
    error_log('Uncaught ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    __error_render(get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
});

// Fatal errors bypass the exception handler, so catch them on the way out.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Fatal: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        __error_render($e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    }
});
