<?php
declare(strict_types=1);
// Запитване по проект, multipart/form-data с незадължително лого:
// - кратката форма на страницата „За хотели и къщи за гости“ (без конфигурация);
// - последната стъпка на конфигуратора при вид „по проект“ (поле config + преглед svg; имейлът е задължителен).
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Методът не е позволен.'], 405);
}
// Ако заявката надхвърля post_max_size, PHP изпразва $_POST и $_FILES – казваме го ясно, вместо "изтекла сесия"
$postMax = (static function (string $v): int {
    $n = (int)$v;
    $u = strtolower(substr(trim($v), -1));
    return $u === 'g' ? $n << 30 : ($u === 'm' ? $n << 20 : ($u === 'k' ? $n << 10 : $n));
})((string)ini_get('post_max_size'));
if ($postMax > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMax) {
    json_out(['ok' => false, 'errors' => ['logo' => 'Файлът е твърде голям. Максимумът е 5 MB.'], 'message' => 'Файлът е твърде голям. Максимумът е 5 MB.'], 413);
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
$object = $p('object', 150);
$city = $p('city', 100);
$note = $p('note', 3000);
$name = $p('contact_name', 100);
$phone = $p('phone', 32);
$email = $p('email', 190);
$qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
$material = (string)($_POST['material'] ?? '');
$deadline = (string)($_POST['deadline'] ?? '');
$consent = !empty($_POST['consent']);

// конфигурация от конфигуратора (вид „по проект“) – проверява се изцяло спрямо базата
$cfgIn = json_decode((string)($_POST['config'] ?? ''), true);
$configured = is_array($cfgIn);
$cat = load_catalog(true);
$v = null;
$errors = [];
if ($configured) {
    $v = validate_config(array_merge($cfgIn, ['quantity' => $qty === false ? 1 : (int)$qty]), $cat);
    foreach ($v['errors'] as $k => $msg) {
        $errors[$k === 'quantity' ? 'quantity' : $k] = $msg;
    }
    if ($v['config'] && empty($v['model']['project'])) {
        $errors['model'] = 'Тази форма се поръчва с обикновеното запитване.';
    }
    if ($email === '') $errors['email'] = 'Въведете имейл.';
}
if (mb_strlen($object) < 2) $errors['object'] = 'Напишете кой е обектът.';
if ($qty === false) $errors['quantity'] = 'Брой между 1 и 500.';
if (!$configured && !isset(project_materials()[$material])) $errors['material'] = 'Изберете материал.';
if (!isset(project_deadlines()[$deadline])) $deadline = 'flex';
if (mb_strlen($name) < 2) $errors['contact_name'] = 'Въведете име.';
$digits = preg_replace('/\D/', '', $phone);
if ($phone === '') $errors['phone'] = 'Въведете телефон.';
elseif (!preg_match('/^[\d\s+()\-\/]+$/', $phone) || strlen($digits) < 8 || strlen($digits) > 15) $errors['phone'] = 'Проверете телефона – напр. 0888 123 456.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Невалиден имейл.';
if (!$consent) $errors['consent'] = 'Нужно е съгласие, за да се свържем с вас.';

// „Интересуват ме“ (кратката форма): формите от вид „по проект“
$interests = [];
$byCode = array_column(project_forms($cat), null, 'code');
foreach ((array)($_POST['interests'] ?? []) as $c) {
    if (is_string($c) && isset($byCode[$c])) $interests[$c] = ['code' => $c, 'name' => $byCode[$c]['name']];
}

$logo = upload_check($_FILES['logo'] ?? null);
if (!$logo['ok']) $errors['logo'] = $logo['error'];

if ($errors) {
    json_out(['ok' => false, 'errors' => $errors, 'message' => reset($errors)], 422);
}

$logoTxt = empty($logo['none']) ? $logo['name'] . ' (' . strtoupper($logo['ext']) . ', ' . fmt_bytes($logo['size']) . ')' : '';
if ($configured) {
    // пълната конфигурация + полетата от проектната форма
    $config = array_merge($v['config'], ['kind' => 'project', 'object' => $object, 'deadline' => $deadline, 'logo' => $logoTxt]);
} else {
    $config = [
        'kind' => 'project',
        'object' => $object,
        'quantity' => (int)$qty,
        'material' => $material,
        'deadline' => $deadline,
        'interests' => array_values($interests),
        'logo' => $logoTxt,
    ];
}
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

// повторно изпращане: същият телефон + същото описание до 2 минути
$phoneKey = substr($digits, -9);
db()->query('SELECT GET_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ', 5)');
foreach (db_all("SELECT public_code, phone, config_json FROM inquiries WHERE kind = 'project' AND created_at > (NOW() - INTERVAL 2 MINUTE)") as $prev) {
    if (substr(preg_replace('/\D/', '', $prev['phone']), -9) === $phoneKey && $prev['config_json'] === $configJson) {
        db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
        log_rejected('duplicate');
        json_out(['ok' => false, 'duplicate' => $prev['public_code'], 'message' => 'Това запитване вече е получено (№ ' . $prev['public_code'] . '). Ще се свържем с вас.'], 409);
    }
}

// файлът се мести извън публичната папка, преди да се запише запитването
$stored = null;
if (empty($logo['none'])) {
    $stored = upload_store($logo);
    if ($stored === null) {
        db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
        error_log('upload: cannot store in ' . upload_dir());
        json_out(['ok' => false, 'errors' => ['logo' => 'Логото не можа да се запише. Изпратете запитването без него – ще го поискаме по имейл.'], 'message' => 'Логото не можа да се запише. Изпратете запитването без него – ще го поискаме по имейл.'], 500);
    }
}

$svg = $configured ? svg_sanitize(mb_substr((string)($_POST['svg'] ?? ''), 0, 600000)) : null;
$modelId = $configured ? $v['model']['id'] : null;
// ориентировъчната цена се пази само за админа (при вид „по проект“ не се показва на клиента)
$pMin = $configured && $v['price'] ? $v['price']['min'] : null;
$pMax = $configured && $v['price'] ? $v['price']['max'] : null;
$wKg = $configured ? $config['weight_kg'] : null;
$src = json_decode((string)($_POST['src'] ?? '{}'), true);
$src = is_array($src) ? $src : [];
$s = fn($k, $len = 190) => isset($src[$k]) && is_string($src[$k]) && $src[$k] !== '' ? mb_substr($src[$k], 0, $len) : null;
$code = 'FP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
try {
    db()->prepare("INSERT INTO inquiries
      (public_code, kind, name, phone, email, city, note, consent_at, model_id, quantity, config_json, svg, price_min, price_max, weight_kg,
       utm_source, utm_medium, utm_campaign, utm_term, utm_content, referrer, landing_url, ip_hash, user_agent)
      VALUES (?, 'project', ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$code, $name, $phone, $email ?: null, $city ?: null, $note ?: null, $modelId, (int)$qty, $configJson, $svg, $pMin, $pMax, $wKg,
            $s('utm_source'), $s('utm_medium'), $s('utm_campaign'), $s('utm_term'), $s('utm_content'), $s('referrer', 500), $s('landing', 500),
            $ipHash, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $id = (int)db()->lastInsertId();
    if ($stored) {
        db()->prepare('INSERT INTO uploads (inquiry_id, stored_name, original_name, mime, ext, size) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$id, $stored, $logo['name'], upload_types()[$logo['ext']]['mime'], $logo['ext'], $logo['size']]);
    }
} catch (Throwable $e) {
    if ($stored && ($pth = upload_path($stored))) @unlink($pth);
    db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
    throw $e;
}
db()->query('SELECT RELEASE_LOCK(' . db()->quote('fp_inq_' . $phoneKey) . ')');
record_event('lead', $modelId);
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

// Имейли
try {
    $site = setting('site_name', 'FireProdukt');
    $lines = config_lines($config, $cat);
    $contact = ['Лице за контакт' => $name, 'Телефон' => $phone, 'Имейл' => $email ?: '—', 'Населено място' => $city ?: '—', 'Бележка' => $note ?: '—'];
    $notify = setting('notify_email', '');
    if ($notify !== '') {
        $t = "Ново запитване по ПРОЕКТ $code\n\n";
        foreach (array_merge($contact, $lines) as $k => $v) $t .= "$k: $v\n";
        if ($pMin !== null) $t .= "\nОриентировъчна цена: " . price_range_text((float)$pMin, (float)$pMax) . " (не се показва на клиента)\n";
        $t .= "\nИзточник: " . ($s('utm_source') ?: '—') . ' / ' . ($s('utm_medium') ?: '—') . ' / ' . ($s('utm_campaign') ?: '—') . "\n";
        $t .= "\nДетайли: " . abs_url('admin/inquiry.php?id=' . $id) . "\n";
        $rows = '';
        foreach (array_merge($contact, $lines) as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee"><b>' . nl2br(e($v)) . '</b></td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222"><h2 style="margin:0 0 12px">Запитване по проект ' . e($code) . '</h2>'
            . '<table style="border-collapse:collapse">' . $rows . '</table><p><a href="' . e(abs_url('admin/inquiry.php?id=' . $id)) . '">Отвори в админ панела</a></p></div>';
        $att = [];
        if ($svg) $att[] = ['name' => 'preview-' . $code . '.svg', 'type' => 'image/svg+xml', 'data' => $svg];
        if ($stored && ($pth = upload_path($stored))) {
            $att[] = ['name' => 'logo-' . $code . '.' . $logo['ext'], 'type' => 'application/octet-stream', 'data' => (string)file_get_contents($pth)];
        }
        send_mail($notify, "Запитване по проект $code – $object ($name)", $t, $html, $att, $id);
    }
    if ($email !== '') {
        $t = "Здравейте, $name!\n\nПолучихме запитването ви по проект ($code). Ще се свържем с вас до 1 работен ден.\n\n";
        foreach ($lines as $k => $v) $t .= "$k: $v\n";
        $t .= "\nПоздрави,\n$site\n";
        $rows = '';
        foreach ($lines as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee">' . e($v) . '</td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px"><p>Здравейте, ' . e($name) . '!</p>'
            . '<p>Получихме запитването ви по проект <b>' . e($code) . '</b>. Ще се свържем с вас до 1 работен ден.</p>'
            . '<table style="border-collapse:collapse;width:100%">' . $rows . '</table><p>Поздрави,<br>' . e($site) . '</p></div>';
        send_mail($email, "Получихме запитването ви $code", $t, $html, [], $id);
    }
} catch (Throwable $e) {
    mail_log($id, '-', 'Грешка при подготовка на имейлите', 'none', false, $e->getMessage());
}
exit;
