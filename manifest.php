<?php
/**
 * Web App Manifest — generated at runtime because the app name and icon come
 * from the Settings page, not from a static file.
 * Deliberately not behind auth.php: the browser fetches this without cookies.
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$name = $config['shop_name'] ?? 'Inventory';
$short = $name;
if (function_exists('mb_substr')) {
    if (mb_strlen($name, 'UTF-8') > 12) {
        $short = mb_substr($name, 0, 12, 'UTF-8');
    }
} elseif (strlen($name) > 12) {
    $short = $name;   // leave intact rather than risk cutting a multi-byte char
}

echo json_encode([
    'name' => $name,
    'short_name' => $short,
    'description' => $name,
    'start_url' => url('index.php'),
    'scope' => url(''),
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f4f5f9',
    'theme_color' => '#4f46e5',
    'lang' => $_SESSION['lang'] ?? 'bn',
    'icons' => [
        ['src' => url('icon.php?s=192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icon.php?s=512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('icon.php?s=512&pad=1'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
