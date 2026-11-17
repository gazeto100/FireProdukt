<?php
declare(strict_types=1);
// Сваляне на качен файл (лого, файлове „По идея“) – само за влязъл администратор.
// SVG, PDF, DXF и HEIC винаги се свалят като файл (никога не се показват в браузъра);
// PNG/JPG/WebP могат да се покажат като картинка (?inline=1) или като малък преглед (?thumb=1, умален с GD).
require __DIR__ . '/_init.php';
require_admin();

$u = db_one('SELECT u.*, i.public_code, i.kind FROM uploads u JOIN inquiries i ON i.id = u.inquiry_id WHERE u.id = ?', [(int)($_GET['id'] ?? 0)]);
$path = $u ? upload_path($u['stored_name']) : null;
if (!$u || !$path) {
    http_response_code(404);
    exit('Файлът не е намерен.');
}
$img = in_array($u['ext'], ['png', 'jpg', 'webp'], true);
// малък преглед (до 320 px) – за да не се зареждат снимки по 8 MB в списъка
if (isset($_GET['thumb']) && $img && function_exists('imagecreatefromstring')) {
    @ini_set('memory_limit', '256M');
    $src = @imagecreatefromstring((string)file_get_contents($path));
    if ($src) {
        $o = $u['ext'] === 'jpg' ? jpeg_orientation((string)file_get_contents($path, false, null, 0, 131072)) : 1;
        if ($o !== 1) $src = image_apply_orientation($src, $o);   // само ако е останала ориентация (резервния вариант)
        $w = imagesx($src); $h = imagesy($src); $k = min(1, 320 / max($w, $h));
        $t = imagecreatetruecolor(max(1, (int)round($w * $k)), max(1, (int)round($h * $k)));
        imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
        imagecopyresampled($t, $src, 0, 0, 0, 0, imagesx($t), imagesy($t), $w, $h);
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        imagejpeg($t, null, 80);
        exit;
    }
}
$inline = (isset($_GET['inline']) || isset($_GET['thumb'])) && $img;
$prefix = $u['kind'] === 'idea' ? 'idea-' : 'logo-';
$downloadName = $prefix . preg_replace('/[^\w\-]/', '', $u['public_code']) . '-' . (int)$u['id'] . '.' . $u['ext'];

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
if ($inline) {
    header('Content-Type: ' . ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][$u['ext']]);
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
} else {
    // octet-stream: браузърът не се опитва да рендерира SVG/PDF
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($u['original_name']));
}
readfile($path);
