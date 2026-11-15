<?php
declare(strict_types=1);

/**
 * Качване на лого към запитване по проект.
 * - позволени: PNG, JPG, SVG, PDF до 5 MB;
 * - типът се проверява по съдържанието (магически байтове + finfo, ако го има), не само по разширението;
 * - файлът се записва с произволно име в папка ИЗВЪН публичната (config 'upload_dir' или автоматично);
 * - достъпен е само през admin/upload.php; SVG и PDF винаги се свалят като файл, не се показват в браузъра.
 */
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

function upload_types(): array
{
    return [
        'png' => ['mime' => 'image/png', 'label' => 'PNG'],
        'jpg' => ['mime' => 'image/jpeg', 'label' => 'JPG'],
        'svg' => ['mime' => 'image/svg+xml', 'label' => 'SVG'],
        'pdf' => ['mime' => 'application/pdf', 'label' => 'PDF'],
    ];
}

/** Папка за качените файлове: от config.php ('upload_dir') или автоматично извън публичната папка */
function upload_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }
    $cfg = (string)app_config('upload_dir', '');
    if ($cfg !== '') {
        return $dir = rtrim(str_replace('\\', '/', $cfg), '/');
    }
    // веднъж избрана от уеб сървъра, папката се пази в настройките (за да не зависи от средата)
    $saved = (string)setting('upload_dir', '');
    if ($saved !== '') {
        return $dir = $saved;
    }
    $dir = upload_dir_detect();
    if (PHP_SAPI !== 'cli') {
        save_setting('upload_dir', $dir);
    }
    return $dir;
}

function upload_dir_detect(): string
{
    $candidates = [];
    $home = getenv('HOME') ?: (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : '');
    if ($home) {
        $candidates[] = rtrim(str_replace('\\', '/', $home), '/') . '/fireprodukt_uploads';
    }
    $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    if ($docRoot !== '') {
        $candidates[] = dirname($docRoot) . '/fireprodukt_uploads';
    }
    foreach ($candidates as $c) {
        if (upload_dir_is_public($c)) {
            continue;
        }
        $parent = dirname($c);
        if ((is_dir($c) && is_writable($c)) || (!is_dir($c) && is_dir($parent) && is_writable($parent))) {
            return $c;
        }
    }
    // Резервно: вътре в сайта, но закрито с .htaccess (админът вижда предупреждение в Настройки)
    return str_replace('\\', '/', APP_ROOT) . '/storage/uploads';
}

/** Дали папката е в публично достъпна директория */
function upload_dir_is_public(string $path): bool
{
    $path = str_replace('\\', '/', $path) . '/';
    $docRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    if ($docRoot !== '' && stripos($path, $docRoot . '/') === 0) {
        return true;
    }
    if (stripos($path, str_replace('\\', '/', APP_ROOT) . '/') === 0) {
        return true;
    }
    return (bool)preg_match('#/(public_html|htdocs|www|httpdocs)/#i', $path);
}

function upload_dir_ready(): bool
{
    $d = upload_dir();
    if (!is_dir($d) && !@mkdir($d, 0750, true)) {
        return false;
    }
    // защита, ако папката все пак е в уеб пространството
    if (!is_file($d . '/.htaccess')) {
        @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file($d . '/index.html')) {
        @file_put_contents($d . '/index.html', '');
    }
    return is_writable($d);
}

/** Определя типа по съдържанието. Връща разширение (png|jpg|svg|pdf) или null. */
function upload_detect(string $path): ?string
{
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return null;
    }
    $head = (string)fread($fh, 1024);
    fclose($fh);

    if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return @getimagesize($path) ? 'png' : null;
    }
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return @getimagesize($path) ? 'jpg' : null;
    }
    if (strncmp($head, '%PDF-', 5) === 0) {
        return 'pdf';
    }
    // SVG: текстов XML с корен <svg>, без DOCTYPE/ENTITY
    $text = (string)file_get_contents($path, false, null, 0, UPLOAD_MAX_BYTES);
    $trim = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $text));
    if ($trim !== '' && $trim[0] === '<' && stripos($text, '<!ENTITY') === false && stripos($text, '<!DOCTYPE') === false) {
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($text, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($ok && $doc->documentElement && strtolower($doc->documentElement->localName) === 'svg') {
            return 'svg';
        }
    }
    return null;
}

/**
 * Проверява качения файл от $_FILES. Връща ['ok'=>true, 'ext'=>.., 'tmp'=>.., 'name'=>.., 'size'=>..]
 * или ['ok'=>false, 'error'=>съобщение]. Ако няма файл – ['ok'=>true, 'none'=>true].
 */
function upload_check(?array $f, bool $httpUpload = true): array
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'none' => true];
    }
    $err = (int)$f['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'Файлът е твърде голям. Максимумът е 5 MB.'];
    }
    // $httpUpload = false само за проверката от командния ред (tools/check_upload.php)
    if ($err !== UPLOAD_ERR_OK || ($httpUpload ? !is_uploaded_file((string)$f['tmp_name']) : !is_file((string)$f['tmp_name']))) {
        return ['ok' => false, 'error' => 'Файлът не се качи. Опитайте отново.'];
    }
    $size = (int)$f['size'];
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Файлът е твърде голям. Максимумът е 5 MB.'];
    }
    $name = (string)$f['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    $types = upload_types();
    if (!isset($types[$ext])) {
        return ['ok' => false, 'error' => 'Позволени са само PNG, JPG, SVG и PDF.'];
    }
    $detected = upload_detect((string)$f['tmp_name']);
    if ($detected === null || $detected !== $ext) {
        return ['ok' => false, 'error' => 'Съдържанието на файла не отговаря на ' . $types[$ext]['label'] . '. Позволени са само PNG, JPG, SVG и PDF.'];
    }
    // допълнителна проверка с finfo, ако е налична
    if (class_exists('finfo')) {
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name']);
        $okMime = [
            'png' => ['image/png'], 'jpg' => ['image/jpeg', 'image/pjpeg'], 'pdf' => ['application/pdf'],
            'svg' => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
        ];
        if (!in_array($mime, $okMime[$ext], true)) {
            return ['ok' => false, 'error' => 'Съдържанието на файла не отговаря на ' . $types[$ext]['label'] . '.'];
        }
    }
    $clean = preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', basename($name));
    return ['ok' => true, 'ext' => $ext, 'tmp' => (string)$f['tmp_name'], 'name' => mb_substr((string)$clean, 0, 190), 'size' => $size];
}

/** Мести проверения файл в папката. Връща името на диска или null. */
function upload_store(array $chk, bool $httpUpload = true): ?string
{
    if (!upload_dir_ready()) {
        return null;
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $chk['ext'];
    $dest = upload_dir() . '/' . $stored;
    if (!($httpUpload ? move_uploaded_file($chk['tmp'], $dest) : copy($chk['tmp'], $dest))) {
        return null;
    }
    @chmod($dest, 0640);
    return $stored;
}

function upload_path(string $stored): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|svg|pdf)$/', $stored)) {
        return null;
    }
    $p = upload_dir() . '/' . $stored;
    return is_file($p) ? $p : null;
}

/** Изтрива файловете и записите за запитване (при изтриване на лични данни) */
function upload_delete_for_inquiry(int $inquiryId): int
{
    $n = 0;
    foreach (db_all('SELECT id, stored_name FROM uploads WHERE inquiry_id = ?', [$inquiryId]) as $u) {
        $p = upload_path($u['stored_name']);
        if ($p && @unlink($p)) {
            $n++;
        }
    }
    db()->prepare('DELETE FROM uploads WHERE inquiry_id = ?')->execute([$inquiryId]);
    return $n;
}

function fmt_bytes(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '') . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}
