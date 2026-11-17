<?php
declare(strict_types=1);

/**
 * Качване на файлове към запитванията (профили в upload_profiles()):
 * - лого (проект, обикновен модел): PNG, JPG, SVG, PDF до 5 MB;
 * - „По ваша идея“: JPG, PNG, WebP, HEIC, PDF, SVG, DXF до 8 MB на файл (до 5 файла, общо до 25 MB – api/idea.php);
 * - JPG: при записване се прилага EXIF ориентацията и се махат всички EXIF данни (вкл. местоположение); HEIC се пази както е;
 * - типът се проверява по съдържанието (магически байтове + finfo, ако го има), не само по разширението;
 * - файлът се записва с произволно име в папка ИЗВЪН публичната (config 'upload_dir' или автоматично);
 * - достъпен е само през admin/upload.php; SVG и PDF винаги се свалят като файл, не се показват в браузъра.
 */
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const IDEA_FILE_MAX_BYTES = 8 * 1024 * 1024;
const IDEA_TOTAL_MAX_BYTES = 25 * 1024 * 1024;
const IDEA_MAX_FILES = 5;
const IDEA_MAIL_ATTACH_MAX = 15 * 1024 * 1024;

function upload_types(): array
{
    return [
        'png' => ['mime' => 'image/png', 'label' => 'PNG'],
        'jpg' => ['mime' => 'image/jpeg', 'label' => 'JPG'],
        'svg' => ['mime' => 'image/svg+xml', 'label' => 'SVG'],
        'pdf' => ['mime' => 'application/pdf', 'label' => 'PDF'],
        'webp' => ['mime' => 'image/webp', 'label' => 'WebP'],
        'heic' => ['mime' => 'image/heic', 'label' => 'HEIC'],
        'dxf' => ['mime' => 'image/vnd.dxf', 'label' => 'DXF'],
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

/**
 * Профили за качване: позволени типове и максимален размер на файл.
 * logo – лого към запитване (проект, обикновен модел); idea – снимки/скици/чертежи към „По ваша идея“.
 */
function upload_profiles(): array
{
    return [
        'logo' => ['types' => ['png', 'jpg', 'svg', 'pdf'], 'max' => UPLOAD_MAX_BYTES],
        'idea' => ['types' => ['jpg', 'png', 'webp', 'heic', 'pdf', 'svg', 'dxf'], 'max' => IDEA_FILE_MAX_BYTES],
    ];
}

/** Списък на позволените типове за съобщенията, напр. „JPG, PNG, WebP, HEIC, PDF, SVG или DXF“ */
function upload_types_text(string $profile): string
{
    $l = array_map(fn($e) => upload_types()[$e]['label'], upload_profiles()[$profile]['types']);
    $last = array_pop($l);
    return $l ? implode(', ', $l) . ' или ' . $last : $last;
}

/** Определя типа по съдържанието. Връща разширение (png|jpg|webp|heic|svg|pdf|dxf) или null. */
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
    if (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') {
        return 'webp';
    }
    // HEIC/HEIF: ISO BMFF – „ftyp“ на 4-ти байт и марка за HEIF изображение
    if (substr($head, 4, 4) === 'ftyp' && in_array(substr($head, 8, 4), ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1', 'heif'], true)) {
        return 'heic';
    }
    if (strncmp($head, '%PDF-', 5) === 0) {
        return 'pdf';
    }
    $text = (string)file_get_contents($path, false, null, 0, IDEA_FILE_MAX_BYTES);
    // текстовите формати (SVG, DXF) не може да съдържат код
    if (stripos($text, '<?php') !== false || stripos($text, '<?=') !== false || stripos($text, '<script') !== false) {
        return null;
    }
    // DXF (ASCII): групови кодове на редове – започва с „0“ / „SECTION“ (след евентуален коментар „999“)
    if (preg_match('/^\s*(?:999\r?\n[^\r\n]*\r?\n\s*)?0\r?\n\s*SECTION\r?\n/', $text) && stripos($text, 'EOF') !== false) {
        return 'dxf';
    }
    // SVG: текстов XML с корен <svg>, без DOCTYPE/ENTITY
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
 * Проверява качения файл от $_FILES за профила (logo | idea). Връща ['ok'=>true, 'ext', 'tmp', 'name', 'size']
 * или ['ok'=>false, 'error'=>съобщение]; ако няма файл – ['ok'=>true, 'none'=>true].
 * Всяко съобщение казва кой файл, защо е отхвърлен и какво да се направи.
 */
function upload_check(?array $f, bool $httpUpload = true, string $profile = 'logo'): array
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'none' => true];
    }
    $prof = upload_profiles()[$profile];
    $maxMb = (int)round($prof['max'] / 1048576);
    $name = (string)$f['name'];
    $clean = mb_substr((string)preg_replace('/[^\p{L}\p{N}._\- ]/u', '_', basename($name)), 0, 190);
    $q = '„' . $clean . '“';
    $err = (int)$f['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => "$q е твърде голям – максимумът е $maxMb MB на файл. Намалете го (напр. снимка в по-малък размер) и опитайте отново."];
    }
    // $httpUpload = false само за проверката от командния ред (tools/check_upload.php)
    if ($err !== UPLOAD_ERR_OK || ($httpUpload ? !is_uploaded_file((string)$f['tmp_name']) : !is_file((string)$f['tmp_name']))) {
        return ['ok' => false, 'error' => "$q не се качи докрай. Опитайте отново."];
    }
    $size = (int)$f['size'];
    if ($size <= 0) {
        return ['ok' => false, 'error' => "$q е празен. Изберете файла отново."];
    }
    if ($size > $prof['max']) {
        return ['ok' => false, 'error' => "$q е " . fmt_bytes($size) . " – максимумът е $maxMb MB на файл. Намалете го (напр. снимка в по-малък размер) и опитайте отново."];
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $ext = ['jpeg' => 'jpg', 'heif' => 'heic'][$ext] ?? $ext;
    $types = upload_types();
    if (!in_array($ext, $prof['types'], true)) {
        return ['ok' => false, 'error' => "$q: този тип файл не се приема. Изпратете " . upload_types_text($profile) . '.'];
    }
    $detected = upload_detect((string)$f['tmp_name']);
    // finfo – допълнителна проверка, когато я има (HEIC и DXF често не се разпознават от finfo – там решава upload_detect)
    if ($detected === $ext && class_exists('finfo') && !in_array($ext, ['heic', 'dxf'], true)) {
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name']);
        $okMime = [
            'png' => ['image/png'], 'jpg' => ['image/jpeg', 'image/pjpeg'], 'pdf' => ['application/pdf'], 'webp' => ['image/webp', 'application/octet-stream'],
            'svg' => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'],
        ];
        if (!in_array($mime, $okMime[$ext], true)) {
            $detected = null;
        }
    }
    if ($detected === null || $detected !== $ext) {
        if ($ext === 'dxf') {
            return ['ok' => false, 'error' => "$q: Този DXF не може да бъде приет. Изпратете чертежа като PDF."];
        }
        if ($ext === 'svg') {
            return ['ok' => false, 'error' => "$q: този SVG не може да бъде приет (повреден или съдържа код). Изпратете го като PDF или PNG."];
        }
        if ($ext === 'pdf') {
            return ['ok' => false, 'error' => "$q: съдържанието не е истински PDF файл. Запишете документа отново като PDF (Файл → Запиши като PDF) и опитайте пак."];
        }
        return ['ok' => false, 'error' => "$q: съдържанието не е истински " . $types[$ext]['label'] . ' файл. Отворете го и го запишете отново като ' . $types[$ext]['label'] . ' или изпратете PDF.'];
    }
    return ['ok' => true, 'ext' => $ext, 'tmp' => (string)$f['tmp_name'], 'name' => $clean, 'size' => $size];
}

/**
 * Мести проверения файл в папката. Връща името на диска или null.
 * JPG: прилага ориентацията от EXIF и маха всички EXIF данни (местоположение, апарат, дата) – jpeg_clean().
 * $chk['size'] се обновява с размера след почистването.
 */
function upload_store(array &$chk, bool $httpUpload = true): ?string
{
    if (!upload_dir_ready()) {
        return null;
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $chk['ext'];
    $dest = upload_dir() . '/' . $stored;
    if (!($httpUpload ? move_uploaded_file($chk['tmp'], $dest) : copy($chk['tmp'], $dest))) {
        return null;
    }
    if ($chk['ext'] === 'jpg') {
        jpeg_clean($dest);
        clearstatcache(true, $dest);
        $chk['size'] = (int)filesize($dest);
    }
    @chmod($dest, 0640);
    return $stored;
}

function upload_path(string $stored): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|svg|pdf|webp|heic|dxf)$/', $stored)) {
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

/* ---------- JPG: ориентация и почистване на EXIF ---------- */

/** EXIF ориентацията (1–8) от JPEG байтове; 1, ако няма */
function jpeg_orientation(string $bin): int
{
    $len = strlen($bin);
    $p = 2;
    while ($p + 4 <= $len && $bin[$p] === "\xFF") {
        $marker = ord($bin[$p + 1]);
        if ($marker === 0xDA || $marker === 0xD9) break;          // начало на данните / край
        $segLen = unpack('n', substr($bin, $p + 2, 2))[1];
        if ($marker === 0xE1 && substr($bin, $p + 4, 6) === "Exif\0\0") {
            $t = substr($bin, $p + 10, $segLen - 8);
            $le = substr($t, 0, 2) === 'II';
            $u16 = fn($o) => strlen($t) >= $o + 2 ? unpack($le ? 'v' : 'n', substr($t, $o, 2))[1] : 0;
            $u32 = fn($o) => strlen($t) >= $o + 4 ? unpack($le ? 'V' : 'N', substr($t, $o, 4))[1] : 0;
            $ifd = $u32(4);
            $n = $u16($ifd);
            for ($i = 0; $i < $n; $i++) {
                $e = $ifd + 2 + $i * 12;
                if ($u16($e) === 0x0112) {
                    $o = $u16($e + 8);
                    return $o >= 1 && $o <= 8 ? $o : 1;
                }
            }
            return 1;
        }
        $p += 2 + $segLen;
    }
    return 1;
}

/** JPEG без метаданни: маха APP1 (EXIF/XMP), APP13 (IPTC), останалите APPn и коментарите; пази APP0 (JFIF), APP2 (ICC) и APP14 (Adobe) */
function jpeg_strip_meta(string $bin): string
{
    $out = "\xFF\xD8";
    $len = strlen($bin);
    $p = 2;
    while ($p + 4 <= $len && $bin[$p] === "\xFF") {
        $marker = ord($bin[$p + 1]);
        if ($marker === 0xDA) {
            return $out . substr($bin, $p);                       // оттук нататък са данните на изображението
        }
        $segLen = unpack('n', substr($bin, $p + 2, 2))[1];
        $keep = !(($marker >= 0xE1 && $marker <= 0xEF && $marker !== 0xE2 && $marker !== 0xEE) || $marker === 0xFE);
        if ($keep) $out .= substr($bin, $p, 2 + $segLen);
        $p += 2 + $segLen;
    }
    return $bin; // непознат формат – не се пипа
}

/** Минимален EXIF само с ориентацията (резервно, ако изображението не може да се завърти) */
function jpeg_orientation_app1(int $o): string
{
    $tiff = 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1) . pack('nnN', 0x0112, 3, 1) . pack('n', $o) . "\0\0" . pack('N', 0);
    $data = "Exif\0\0" . $tiff;
    return "\xFF\xE1" . pack('n', strlen($data) + 2) . $data;
}

/**
 * Прилага EXIF ориентацията (завърта пикселите) и маха всички EXIF данни, вкл. местоположението.
 * Ако снимката е изправена – без прекодиране (без загуба). Ако не може да се завърти (няма GD / малко памет) –
 * махат се метаданните и остава само ориентацията, за да се показва правилно.
 */
function jpeg_clean(string $path): bool
{
    $bin = (string)@file_get_contents($path);
    if ($bin === '' || strncmp($bin, "\xFF\xD8", 2) !== 0) return false;
    $o = jpeg_orientation($bin);
    if ($o !== 1 && function_exists('imagecreatefromstring')) {
        $old = ini_get('memory_limit');
        if ((int)$old > 0 && (int)$old < 256) @ini_set('memory_limit', '256M');
        $im = @imagecreatefromstring($bin);
        if ($im) {
            $im = image_apply_orientation($im, $o);
            $ok = @imagejpeg($im, $path, 90);   // GD записва JPEG без EXIF
            imagedestroy($im);
            if ($ok) return true;
        }
    }
    $clean = jpeg_strip_meta($bin);
    if ($o !== 1) {
        $clean = "\xFF\xD8" . jpeg_orientation_app1($o) . substr($clean, 2);
    }
    return @file_put_contents($path, $clean) !== false;
}

/** Завърта/обръща GD изображение според EXIF ориентацията */
function image_apply_orientation($im, int $o)
{
    if (in_array($o, [2, 4, 5, 7], true)) imageflip($im, $o === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
    $deg = [3 => 180, 4 => 0, 5 => 90, 6 => 270, 7 => 270, 8 => 90][$o] ?? 0;   // imagerotate върти обратно на часовника
    if ($deg) {
        $r = imagerotate($im, $deg, 0);
        imagedestroy($im);
        $im = $r;
    }
    return $im;
}
