<?php
// Проверка на правилата за качване на лого от командния ред (без HTTP):
//   php tools/check_upload.php ПАПКА_С_ТЕСТОВИ_ФАЙЛОВЕ [--store]
// --store записва валидните файлове в папката за качване и създава тестово запитване „Проект“ (за проверка в админа).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? '';
require dirname(__DIR__) . '/inc/bootstrap.php';

$dir = rtrim($argv[1] ?? '', '/\\');
$store = in_array('--store', $argv, true);
if (!is_dir($dir)) {
    exit("Използване: php tools/check_upload.php ПАПКА [--store]\n");
}
echo 'Папка за качване: ' . upload_dir() . (upload_dir_is_public(upload_dir()) ? '  ⚠ публична' : '  ✓ извън публичната') . "\n\n";
foreach (glob($dir . '/*') as $path) {
    if (!is_file($path) || preg_match('/\.(txt|html)$/', $path)) continue;
    $f = ['name' => basename($path), 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK];
    $r = upload_check($f, false);
    printf("%-12s %8s  %s\n", basename($path), fmt_bytes((int)filesize($path)), $r['ok'] ? '✓ приет като ' . strtoupper($r['ext']) : '✗ ' . $r['error']);
    if ($store && $r['ok'] && empty($r['none'])) {
        $stored = upload_store($r, false);
        if (!$stored) { echo "   ! не можа да се запише\n"; continue; }
        $code = 'FP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $cfg = ['kind' => 'project', 'object' => 'Тест ' . basename($path), 'quantity' => 2, 'material' => 'corten', 'deadline' => 'm3',
            'interests' => [['code' => 'bowl120', 'name' => 'Кръгла купа Ø120']], 'logo' => $r['name'] . ' (' . strtoupper($r['ext']) . ', ' . fmt_bytes($r['size']) . ')'];
        db()->prepare("INSERT INTO inquiries (public_code, kind, name, phone, email, city, note, consent_at, quantity, config_json, utm_source)
            VALUES (?, 'project', 'Тест Качване', '0888 000 111', NULL, 'Банско', 'тестово запитване от tools/check_upload.php', NOW(), 2, ?, 'test')")
            ->execute([$code, json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
        $id = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO uploads (inquiry_id, stored_name, original_name, mime, ext, size) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $stored, $r['name'], upload_types()[$r['ext']]['mime'], $r['ext'], $r['size']]);
        echo "   → записан като $stored, запитване $code (id $id)\n";
    }
}
