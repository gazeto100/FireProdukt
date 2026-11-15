<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();

$row = db_one('SELECT svg, public_code FROM inquiries WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$row || !$row['svg']) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/svg+xml; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; font-src data:; img-src data:");
header('X-Content-Type-Options: nosniff');
if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w\-]/', '', $row['public_code']) . '.svg"');
}
echo $row['svg'];
