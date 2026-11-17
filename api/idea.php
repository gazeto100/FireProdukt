<?php
declare(strict_types=1);
// Запитване „По ваша идея“: multipart/form-data с 1–5 файла (снимки, скица, чертеж) – вид 'idea'.
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Методът не е позволен.'], 405);
}
// Над post_max_size PHP изпразва $_POST и $_FILES – казваме го ясно, вместо „изтекла сесия“
$postMax = (static function (string $v): int {
    $n = (int)$v;
    $u = strtolower(substr(trim($v), -1));
    return $u === 'g' ? $n << 30 : ($u === 'm' ? $n << 20 : ($u === 'k' ? $n << 10 : $n));
})((string)ini_get('post_max_size'));
if ($postMax > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMax) {
    $msg = 'Файловете са общо над ' . (int)round($postMax / 1048576) . ' MB и не могат да се изпратят наведнъж. Изпратете по-малки снимки или по-малко файлове (до 25 MB общо).';
    json_out(['ok' => false, 'errors' => ['files' => $msg], 'message' => $msg], 413);
}
if (!csrf_check($_POST['csrf'] ?? null)) {
    log_rejected('csrf');
    json_out(['ok' => false, 'code' => 'csrf', 'message' => 'Сесията изтече. Данните ви са запазени – натиснете „Изпрати“ отново.'], 403);
}
$started = (int)($_SESSION['form_t'] ?? 0);
if (trim((string)($_POST['website'] ?? '')) !== '' || ($started && time() - $started < 3)) {
    log_rejected(trim((string)($_POST['website'] ?? '')) !== '' ? 'honeypot' : 'too_fast');
    json_out(['ok' => true, 'redirect' => url('thanks.php')]);
}
$limit = max(1, (int)setting('inquiry_rate_limit', '30'));
$ipHash = ip_hash();
if ((int)db_one('SELECT COUNT(*) c FROM inquiries WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$ipHash])['c'] >= $limit) {
    log_rejected('rate_limit');
    json_out(['ok' => false, 'message' => 'Изпратени са твърде много запитвания от този адрес. Опитайте отново след час или ни се обадете.'], 429);
}

$p = fn($k, $len) => str_limit((string)($_POST[$k] ?? ''), $len);
$what = (string)($_POST['what'] ?? '');
$description = trim(str_replace(["\r\n", "\r"], "\n", (string)($_POST['description'] ?? '')));
$description = mb_substr($description, 0, 3000);
$inscription = $p('inscription', 200);
$material = (string)($_POST['material'] ?? '');
$budgetKey = (string)($_POST['budget'] ?? '');
$name = $p('name', 100);
$phone = $p('phone', 32);
$email = $p('email', 190);
$city = $p('city', 100);
$qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
$num = function (string $k): ?float {
    $v = str_replace(',', '.', trim((string)($_POST[$k] ?? '')));
    return $v === '' ? null : (is_numeric($v) ? round((float)$v, 1) : -1.0);
};
$w = $num('w');
$d = $num('d');
$h = $num('h');

$errors = [];
if (!isset(idea_whats()[$what])) $errors['what'] = 'Изберете какво искате.';
if (mb_strlen($description) < 20) $errors['description'] = 'Опишете идеята с поне 20 знака.';
foreach (['w' => [$w, 'Ширината', true], 'd' => [$d, 'Дълбочината', false], 'h' => [$h, 'Височината', true]] as $k => [$v, $lab, $req]) {
    if ($v === null) {
        if ($req) $errors[$k] = 'Въведете ' . mb_strtolower($lab) . ' в см (приблизително).';
    } elseif ($v <= 0 || $v > 1000) {
        $errors[$k] = $lab . ' трябва да е число между 1 и 1000 см.';
    }
}
if (!isset(idea_materials()[$material])) $material = 'unsure';
$budgets = idea_budgets();
if (!isset($budgets[$budgetKey])) $budgetKey = 'unknown';
if ($qty === false) $errors['quantity'] = 'Брой между 1 и 500.';
if (mb_strlen($name) < 2) $errors['name'] = 'Въведете име.';
$digits = preg_replace('/\D/', '', $phone);
if ($phone === '') $errors['phone'] = 'Въведете телефон.';
elseif (!preg_match('/^[\d\s+()\-\/]+$/', $phone) || strlen($digits) < 8 || strlen($digits) > 15) $errors['phone'] = 'Проверете телефона – напр. 0888 123 456.';
if ($email === '') $errors['email'] = 'Въведете имейл.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Невалиден имейл.';
if (empty($_POST['rights'])) $errors['rights'] = 'Потвърдете, че имате право да използвате изображенията и идеята.';
if (empty($_POST['consent'])) $errors['consent'] = 'Нужно е съгласие, за да се свържем с вас.';

// Файлове: files[] – от 1 до 5, всеки до 8 MB, общо до 25 MB; всеки се проверява по съдържание
$raw = $_FILES['files'] ?? null;
$list = [];
if (is_array($raw) && isset($raw['name']) && is_array($raw['name'])) {
    foreach ($raw['name'] as $i => $n) {
        if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $list[] = ['name' => $n, 'type' => $raw['type'][$i] ?? '', 'tmp_name' => $raw['tmp_name'][$i] ?? '', 'error' => $raw['error'][$i], 'size' => $raw['size'][$i] ?? 0];
    }
}
$fileErr = [];
$checked = [];
if (!$list) {
    $fileErr[] = 'Прикачете поне една снимка, скица или чертеж.';
} elseif (count($list) > IDEA_MAX_FILES) {
    $fileErr[] = 'Изпратихте ' . count($list) . ' файла – най-много са ' . IDEA_MAX_FILES . '. Махнете излишните и опитайте отново.';
} else {
    $total = 0;
    foreach ($list as $f) {
        $r = upload_check($f, true, 'idea');
        if (!$r['ok']) {
            $fileErr[] = $r['error'];
        } elseif (empty($r['none'])) {
            $checked[] = $r;
            $total += $r['size'];
        }
    }
    if (!$fileErr && $total > IDEA_TOTAL_MAX_BYTES) {
        $fileErr[] = 'Файловете са общо ' . fmt_bytes($total) . ' – най-много са 25 MB. Намалете снимките или изпратете по-малко файлове.';
    }
}
if ($fileErr) $errors['files'] = implode("\n", $fileErr);

if ($errors) {
    json_out(['ok' => false, 'errors' => $errors, 'message' => reset($errors)], 422);
}

$config = [
    'kind' => 'idea',
    'what' => $what,
    'description' => $description,
    'dims' => ['w' => $w, 'd' => $d, 'h' => $h],
    'material' => $material,
    'inscription' => $inscription,
    'quantity' => (int)$qty,
    'budget' => $budgets[$budgetKey],
    'budget_key' => $budgetKey,
    'rights' => true,
    'files' => array_map(fn($r) => ['name' => $r['name'], 'ext' => $r['ext'], 'size' => $r['size']], $checked),
];
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

// повторно изпращане: същият телефон + същото съдържание до 2 минути
$phoneKey = substr($digits, -9);
db()->query('SELECT GET_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ', 5)');
foreach (db_all("SELECT public_code, phone, config_json FROM inquiries WHERE kind = 'idea' AND created_at > (NOW() - INTERVAL 2 MINUTE)") as $prev) {
    if (substr(preg_replace('/\D/', '', $prev['phone']), -9) === $phoneKey && $prev['config_json'] === $configJson) {
        db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
        log_rejected('duplicate');
        json_out(['ok' => false, 'duplicate' => $prev['public_code'], 'message' => 'Това запитване вече е получено (№ ' . $prev['public_code'] . '). Ще се свържем с вас.'], 409);
    }
}

// файловете се местят извън публичната папка (JPG – с приложена ориентация и без EXIF), преди да се запише запитването
$stored = [];
foreach ($checked as $i => $r) {
    $sn = upload_store($checked[$i]);
    if ($sn === null) {
        foreach ($stored as $x) if ($pth = upload_path($x[0])) @unlink($pth);
        db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
        error_log('upload: cannot store in ' . upload_dir());
        json_out(['ok' => false, 'errors' => ['files' => '„' . $r['name'] . '“ не можа да се запише. Опитайте отново след малко.'], 'message' => 'Файловете не можаха да се запишат.'], 500);
    }
    $stored[] = [$sn, $checked[$i]];
}
// размерите след почистването на JPG
$config['files'] = array_map(fn($x) => ['name' => $x[1]['name'], 'ext' => $x[1]['ext'], 'size' => $x[1]['size']], $stored);
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
$filesBytes = array_sum(array_map(fn($x) => $x[1]['size'], $stored));

$src = json_decode((string)($_POST['src'] ?? '{}'), true);
$src = is_array($src) ? $src : [];
$s = fn($k, $len = 190) => isset($src[$k]) && is_string($src[$k]) && $src[$k] !== '' ? mb_substr($src[$k], 0, $len) : null;
$code = 'FP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
try {
    db()->prepare("INSERT INTO inquiries
      (public_code, kind, name, phone, email, city, note, consent_at, model_id, quantity, config_json, svg, price_min, price_max, weight_kg,
       utm_source, utm_medium, utm_campaign, utm_term, utm_content, referrer, landing_url, ip_hash, user_agent)
      VALUES (?, 'idea', ?, ?, ?, ?, NULL, NOW(), NULL, ?, ?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$code, $name, $phone, $email, $city ?: null, (int)$qty, $configJson,
            $s('utm_source'), $s('utm_medium'), $s('utm_campaign'), $s('utm_term'), $s('utm_content'), $s('referrer', 500), $s('landing', 500),
            $ipHash, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $id = (int)db()->lastInsertId();
    $ins = db()->prepare('INSERT INTO uploads (inquiry_id, stored_name, original_name, mime, ext, size) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($stored as [$sn, $r]) {
        $ins->execute([$id, $sn, $r['name'], upload_types()[$r['ext']]['mime'], $r['ext'], $r['size']]);
    }
} catch (Throwable $e) {
    foreach ($stored as $x) if ($pth = upload_path($x[0])) @unlink($pth);
    db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
    throw $e;
}
db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
record_event('lead', null);
$_SESSION['last_inquiry'] = $code;
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok' => true, 'redirect' => url('thanks.php?c=' . rawurlencode($code))], JSON_UNESCAPED_UNICODE);
ignore_user_abort(true);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    @ob_flush();
    flush();
}

// Имейли: до администратора – с файловете (ако са общо до 15 MB); до клиента – потвърждение без цена
try {
    $mails = idea_mails([
        'id' => $id, 'code' => $code, 'name' => $name, 'phone' => $phone, 'email' => $email, 'city' => $city,
        'utm_source' => $s('utm_source'), 'utm_medium' => $s('utm_medium'), 'utm_campaign' => $s('utm_campaign'),
    ], $config, $filesBytes);
    if ($mails['admin']) {
        $att = [];
        if ($mails['admin']['attach']) {
            foreach ($stored as $k => [$sn, $r]) {
                if ($pth = upload_path($sn)) {
                    $att[] = ['name' => 'idea-' . $code . '-' . ($k + 1) . '.' . $r['ext'], 'type' => 'application/octet-stream', 'data' => (string)file_get_contents($pth)];
                }
            }
        }
        send_mail($mails['admin']['to'], $mails['admin']['subject'], $mails['admin']['text'], $mails['admin']['html'], $att, $id);
    }
    if ($mails['client']) {
        send_mail($mails['client']['to'], $mails['client']['subject'], $mails['client']['text'], $mails['client']['html'], [], $id);
    }
} catch (Throwable $e) {
    mail_log($id, '-', 'Грешка при подготовка на имейлите', 'none', false, $e->getMessage());
}
exit;
