<?php
declare(strict_types=1);
// Сваляне на качено лого – само за влязъл администратор.
// SVG и PDF винаги се свалят като файл (никога не се показват в браузъра); PNG/JPG могат да се покажат като картинка (?inline=1).
require __DIR__ . '/_init.php';
require_admin();

$u = db_one('SELECT u.*, i.public_code FROM uploads u JOIN inquiries i ON i.id = u.inquiry_id WHERE u.id = ?', [(int)($_GET['id'] ?? 0)]);
$path = $u ? upload_path($u['stored_name']) : null;
if (!$u || !$path) {
    http_response_code(404);
    exit('Файлът не е намерен.');
}
$inline = isset($_GET['inline']) && in_array($u['ext'], ['png', 'jpg'], true);
$downloadName = 'logo-' . preg_replace('/[^\w\-]/', '', $u['public_code']) . '.' . $u['ext'];

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
if ($inline) {
    header('Content-Type: ' . ($u['ext'] === 'png' ? 'image/png' : 'image/jpeg'));
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
} else {
    // octet-stream: браузърът не се опитва да рендерира SVG/PDF
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($u['original_name']));
}
readfile($path);
