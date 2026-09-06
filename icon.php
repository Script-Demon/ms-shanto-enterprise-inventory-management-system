<?php
/**
 * App icon for the installed (PWA) app, built from the logo uploaded in Settings.
 *
 * Sizes are requested by the manifest (192 / 512). Where GD is available the
 * logo is fitted onto a square canvas at the exact size the launcher wants;
 * where it isn't, the uploaded file is served as-is so the icon still works.
 * Not behind auth.php — the browser fetches icons without cookies.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$size = (int)($_GET['s'] ?? 192);
if ($size < 32 || $size > 1024) {
    $size = 192;
}
$maskable = !empty($_GET['pad']);          // maskable icons need a bigger safe margin

$logo = $config['logo_file'] ?? '';
$logoPath = ($logo !== '' && is_file(logo_dir() . '/' . $logo)) ? logo_dir() . '/' . $logo : null;

// Cache, but bust it whenever the logo (or its mtime) changes.
$stamp = $logoPath ? (string)filemtime($logoPath) . '-' . $logo : 'default';
$etag = '"' . md5($stamp . '|' . $size . '|' . (int)$maskable) . '"';
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

// ---- No GD: serve what we can without resizing -------------------------------
if (!function_exists('imagecreatetruecolor')) {
    if ($logoPath) {
        $info = @getimagesize($logoPath);
        header('Content-Type: ' . ($info['mime'] ?? 'image/png'));
        readfile($logoPath);
        exit;
    }
    $fallback = __DIR__ . '/assets/app-icon.png';
    if (is_file($fallback)) {
        header('Content-Type: image/png');
        readfile($fallback);
        exit;
    }
    http_response_code(404);
    exit;
}

// ---- With GD: render a proper square icon ------------------------------------
$canvas = imagecreatetruecolor($size, $size);
imagealphablending($canvas, false);
imagesavealpha($canvas, true);

if ($maskable) {
    // Maskable icons get cropped to a circle/squircle by the launcher, so the
    // background must be opaque and the artwork kept inside the safe zone.
    $bg = imagecolorallocate($canvas, 0x4f, 0x46, 0xe5);
    $pad = (int)round($size * 0.20);
} else {
    $bg = imagecolorallocate($canvas, 0xff, 0xff, 0xff);
    $pad = (int)round($size * 0.08);
}
imagefilledrectangle($canvas, 0, 0, $size, $size, $bg);
imagealphablending($canvas, true);

$drawn = false;
if ($logoPath) {
    $src = @imagecreatefromstring((string)file_get_contents($logoPath));
    if ($src !== false) {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $box = $size - ($pad * 2);
        $scale = min($box / $sw, $box / $sh);          // contain, never crop
        $dw = max(1, (int)round($sw * $scale));
        $dh = max(1, (int)round($sh * $scale));
        $dx = (int)round(($size - $dw) / 2);
        $dy = (int)round(($size - $dh) / 2);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        $drawn = true;
    }
}

if (!$drawn) {
    // No logo yet — draw a simple package glyph on the brand colour so the
    // installed app still has a real icon.
    imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 0x4f, 0x46, 0xe5));
    $white = imagecolorallocate($canvas, 0xff, 0xff, 0xff);
    $c = $size / 2;
    $r = $size * 0.26;
    $top = [$c, $c - $r, $c + $r, $c - $r / 2, $c, $c, $c - $r, $c - $r / 2];
    $left = [$c - $r, $c - $r / 2, $c, $c, $c, $c + $r, $c - $r, $c + $r / 2];
    $right = [$c + $r, $c - $r / 2, $c + $r, $c + $r / 2, $c, $c + $r, $c, $c];
    imagefilledpolygon($canvas, array_map('intval', $top), $white);
    imagefilledpolygon($canvas, array_map('intval', $left), imagecolorallocate($canvas, 0xe4, 0xe2, 0xff));
    imagefilledpolygon($canvas, array_map('intval', $right), imagecolorallocate($canvas, 0xc9, 0xc5, 0xff));
}

header('Content-Type: image/png');
imagepng($canvas);
imagedestroy($canvas);
