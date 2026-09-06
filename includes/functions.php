<?php
function url($path) {
    global $config;
    $base = rtrim($config['base_path'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Translates a key using the current-language dictionary loaded in bootstrap.php.
// Extra args are applied with sprintf() for placeholders like %s.
function t($key, ...$args) {
    global $translations;
    $str = $translations[$key] ?? $key;
    return $args ? vsprintf($str, $args) : $str;
}

function money($amount) {
    global $config;
    $currency = $config['currency'] ?? '';
    return $currency . number_format((float)$amount, 2);
}

// First character of a string, UTF-8 safe. Does not require the mbstring
// extension, which isn't installed on every host.
function first_char($s) {
    $s = trim((string)$s);
    if ($s === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, 1, 'UTF-8');
    }
    if (preg_match('/^./u', $s, $m)) {
        return $m[0];
    }
    return substr($s, 0, 1);
}

// Maximum characters each text column accepts, mirroring sql/schema.sql.
// The forms check against these before MySQL does: on a strict-mode server an
// over-long value throws a PDOException, and on a lenient one it is silently
// truncated. Neither is acceptable, so nothing over-long reaches the database.
// Keep in step with the schema whenever a column width changes.
const FIELD_MAX = [
    'products.name'              => 150,
    'products.sku'               => 50,
    'products.unit'              => 20,
    'categories.name'            => 100,
    'customers.name'             => 150,
    'customers.phone'            => 30,
    'customers.address'          => 255,
    'employees.name'             => 150,
    'employees.phone'            => 30,
    'employees.designation'      => 100,
    'invoices.walkin_name'       => 150,
    'invoices.walkin_phone'      => 30,
    'payments.note'              => 255,
    'salary_payments.note'       => 255,
    'stock_adjustments.reason'   => 255,
    'transport_entries.car_number'   => 50,
    'transport_entries.driver_name'  => 150,
    'transport_entries.driver_phone' => 30,
    'transport_entries.description'  => 255,
    'transport_payments.note'    => 255,
    'users.name'                 => 100,
    'users.username'             => 50,
];

// Character count, UTF-8 aware. MySQL counts characters, not bytes, for
// utf8mb4 columns, so a Bangla name must be measured the same way — strlen()
// would reject valid input at roughly a third of the real limit.
function str_len($s) {
    $s = (string)$s;
    if (function_exists('mb_strlen')) {
        return mb_strlen($s, 'UTF-8');
    }
    return preg_match_all('/./us', $s);
}

// The character limit for a column, for both validation and the maxlength
// attribute on the matching input.
function field_max($column) {
    return FIELD_MAX[$column] ?? 255;
}

// Returns a translated message when $value is too long for $column, else null.
// $label is the field's own name, so the message says which box to shorten.
function too_long($column, $value, $label) {
    return str_len($value) > field_max($column)
        ? t('err_too_long', $label, field_max($column))
        : null;
}

// First non-null message from a list of checks — lets a form run several
// length checks and report the first problem without a ladder of ifs.
function first_error(...$errors) {
    foreach ($errors as $e) {
        if ($e !== null && $e !== '') {
            return $e;
        }
    }
    return null;
}

// Writes one branding setting (see the whitelist in bootstrap.php).
function setting_save(PDO $pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

// Absolute path of the uploaded-logo directory.
function logo_dir() {
    return __DIR__ . '/../assets/uploads';
}

// Absolute path of the uploaded product-image directory. Lives under
// assets/uploads so it inherits that folder's .htaccess no-execute rules.
function product_image_dir() {
    return __DIR__ . '/../assets/uploads/products';
}

// Validates one uploaded image and moves it into $dir under a random name.
// Returns null on success (with the new filename in $savedName), otherwise an
// error code the caller maps to its own translated message:
//   none   no file was submitted        size  larger than $maxBytes
//   upload the transfer itself failed   type  not a PNG/JPG/GIF/WEBP
//   dir    the folder is not writable
function store_uploaded_image($file, $dir, $prefix, &$savedName, $maxBytes = 2097152) {
    $savedName = null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'none';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'upload';
    }
    if ($file['size'] > $maxBytes) {
        return 'size';
    }

    // Trust the image itself, not the filename or the browser's content type.
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        return 'type';
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return 'dir';
    }

    $name = $prefix . bin2hex(random_bytes(8)) . '.' . $allowed[$info[2]];
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return 'dir';
    }

    $savedName = $name;
    return null;
}

// Deletes a previously stored upload. Safe to call with an empty name.
function delete_uploaded_image($dir, $file) {
    if ((string)$file !== '' && is_file($dir . '/' . $file)) {
        @unlink($dir . '/' . $file);
    }
}

// The shop's logo image if one is uploaded, otherwise the initial-letter mark.
function brand_logo_html() {
    global $config;
    $logo = $config['logo_file'] ?? '';
    if ($logo !== '' && is_file(logo_dir() . '/' . $logo)) {
        return '<img class="brand-logo" src="' . e(url('assets/uploads/' . $logo)) . '" alt="'
             . e($config['shop_name'] ?? '') . '">';
    }
    return '<span class="brand-mark">' . e(first_char($config['shop_name'] ?? 'S')) . '</span>';
}

// Inline SVG icons — kept local so the app needs no icon font or CDN.
function icon($name, $class = 'ico') {
    $paths = [
        'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
        'package'   => '<path d="M21 8.5 12 3 3 8.5v7L12 21l9-5.5v-7Z"/><path d="M3 8.5 12 14l9-5.5"/><path d="M12 14v7"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'receipt'   => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'dots'      => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
        'tag'       => '<path d="M20.6 13.4 12 22l-9-9V4h9l8.6 8.6a1.4 1.4 0 0 1 0 2Z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'layers'    => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'users'     => '<path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.9"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="m7 15 4-5 3 3 5-7"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'cash'      => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'trending'  => '<path d="m3 17 6-6 4 4 8-8"/><path d="M17 7h4v4"/>',
        'alert'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16.5h.01"/>',
        'warning'   => '<path d="M10.3 4.3 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'close'     => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'settings'  => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
        'truck'     => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/>',
        'wallet'    => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H17v3"/><path d="M3 7.5V17a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5.5A2.5 2.5 0 0 1 3 7.5Z"/><circle cx="16.5" cy="14" r="1.2"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

// Trims trailing zeros off a decimal quantity (e.g. "5.000" -> "5", "2.500" -> "2.5")
function qty($v) {
    $s = number_format((float)$v, 3, '.', '');
    $s = rtrim($s, '0');
    $s = rtrim($s, '.');
    return $s === '' ? '0' : $s;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die(t('error_csrf'));
    }
}

function generate_invoice_no($id) {
    return 'INV-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

function flash_set($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
