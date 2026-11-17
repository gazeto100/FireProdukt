<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Методът не е позволен.'], 405);
}
$raw = file_get_contents('php://input', false, null, 0, 600000);
$in = json_decode((string)$raw, true);
if (!is_array($in)) {
    json_out(['ok' => false, 'message' => 'Невалидна заявка.'], 400);
}
if (!csrf_check($in['csrf'] ?? null)) {
    log_rejected('csrf');
    // code=csrf: браузърът взима нов токен от api/token.php и изпраща повторно автоматично (веднъж)
    json_out(['ok' => false, 'code' => 'csrf', 'message' => 'Сесията изтече. Изборът ви е запазен – натиснете „Изпрати запитване“ отново.'], 403);
}

// Honeypot и твърде бързо попълване -> тих "успех", нищо не се записва
$started = (int)($_SESSION['form_t'] ?? 0);
if (trim((string)($in['website'] ?? '')) !== '' || ($started && time() - $started < 3)) {
    log_rejected(trim((string)($in['website'] ?? '')) !== '' ? 'honeypot' : 'too_fast');
    json_out(['ok' => true, 'redirect' => url('thanks.php')]);
}

// Ограничение по IP
$limit = max(1, (int)setting('inquiry_rate_limit', '30'));
$ipHash = ip_hash();
$cnt = (int)(db_one('SELECT COUNT(*) c FROM inquiries WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$ipHash])['c'] ?? 0);
if ($cnt >= $limit) {
    log_rejected('rate_limit');
    json_out(['ok' => false, 'message' => 'Изпратени са твърде много запитвания от този адрес. Опитайте отново след час или ни се обадете.'], 429);
}

// Контакти
$c = is_array($in['contact'] ?? null) ? $in['contact'] : [];
$name = str_limit((string)($c['name'] ?? ''), 100);
$phone = str_limit((string)($c['phone'] ?? ''), 32);
$email = str_limit((string)($c['email'] ?? ''), 190);
$city = str_limit((string)($c['city'] ?? ''), 100);
$note = str_limit((string)($c['note'] ?? ''), 2000);
$consent = !empty($c['consent']);

$errors = [];
if (mb_strlen($name) < 2) $errors['contact.name'] = 'Въведете име.';
$digits = preg_replace('/\D/', '', $phone);
if ($phone === '') {
    $errors['contact.phone'] = 'Въведете телефон.';
} elseif (!preg_match('/^[\d\s+()\-\/]+$/', $phone) || strlen($digits) < 8 || strlen($digits) > 15) {
    $errors['contact.phone'] = 'Проверете телефона – напр. 0888 123 456.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['contact.email'] = 'Невалиден имейл.';
if (!$consent) $errors['contact.consent'] = 'Нужно е съгласие, за да се свържем с вас.';

// Конфигурация – проверява се изцяло спрямо базата; цената се изчислява тук
$cat = load_catalog(true);
$v = validate_config(is_array($in['config'] ?? null) ? $in['config'] : [], $cat);
foreach ($v['errors'] as $k => $msg) {
    $errors[$k] = $msg;
}
// формите от вид „по проект“ се изпращат с проектната форма (api/project.php)
if (!empty($v['model']['project'])) {
    $errors['model'] = 'Тази форма се поръчва с проектната форма.';
}
if ($errors) {
    $first = reset($errors);
    json_out(['ok' => false, 'errors' => $errors, 'message' => $first], 422);
}
$config = $v['config'];
$price = $v['price'];
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

// Повторно запитване: същият телефон + същата конфигурация в последните 2 минути -> отказ.
// GET_LOCK сериализира едновременни заявки от един и същ телефон (двоен клик, две вкладки).
$phoneKey = substr($digits, -9);
db()->query("SELECT GET_LOCK(" . db()->quote('fp_inq_' . $phoneKey) . ", 5)");
foreach (db_all('SELECT public_code, phone, config_json FROM inquiries WHERE created_at > (NOW() - INTERVAL 2 MINUTE)') as $prev) {
    if (substr(preg_replace('/\D/', '', $prev['phone']), -9) === $phoneKey && $prev['config_json'] === $configJson) {
        db()->query("SELECT RELEASE_LOCK(" . db()->quote('fp_inq_' . $phoneKey) . ")");
        log_rejected('duplicate');
        json_out([
            'ok' => false,
            'duplicate' => $prev['public_code'],
            'message' => 'Това запитване вече е получено (№ ' . $prev['public_code'] . '). Няма нужда да го изпращате отново – ще се свържем с вас.',
        ], 409);
    }
}

$svg = svg_sanitize((string)($in['svg'] ?? ''));

// Източник на посещението
$src = is_array($in['src'] ?? null) ? $in['src'] : [];
$s = fn($k, $len = 190) => isset($src[$k]) && is_string($src[$k]) && $src[$k] !== '' ? mb_substr($src[$k], 0, $len) : null;

$code = 'FP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$st = db()->prepare('INSERT INTO inquiries
  (public_code, name, phone, email, city, note, consent_at, model_id, quantity, config_json, svg, price_min, price_max, weight_kg,
   utm_source, utm_medium, utm_campaign, utm_term, utm_content, referrer, landing_url, ip_hash, user_agent)
  VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$st->execute([
    $code, $name, $phone, $email ?: null, $city ?: null, $note ?: null,
    $v['model']['id'], $config['quantity'], $configJson, $svg,
    $price['min'], $price['max'], $config['weight_kg'],
    $s('utm_source'), $s('utm_medium'), $s('utm_campaign'), $s('utm_term'), $s('utm_content'),
    $s('referrer', 500), $s('landing', 500), $ipHash, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
]);
$id = (int)db()->lastInsertId();
db()->query("SELECT RELEASE_LOCK(" . db()->quote('fp_inq_' . $phoneKey) . ")");
record_event('lead', $v['model']['id']);

$_SESSION['last_inquiry'] = $code;
session_write_close();

// Първо отговаряме на клиента, после пращаме имейлите (ако сървърът го позволява)
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

// Имейли (неуспехите се записват в mail_log и се виждат в админа)
try {
    send_inquiry_mails($id, $code, $config, $cat, $price, compact('name', 'phone', 'email', 'city', 'note'), $svg, [
        'utm_source' => $s('utm_source'), 'utm_medium' => $s('utm_medium'), 'utm_campaign' => $s('utm_campaign'),
        'referrer' => $s('referrer', 500),
    ]);
} catch (Throwable $e) {
    mail_log($id, '-', 'Грешка при подготовка на имейлите', 'none', false, $e->getMessage());
}
exit;


function send_inquiry_mails(int $id, string $code, array $config, array $cat, array $price, array $ct, ?string $svg, array $src): void
{
    $site = setting('site_name', 'FireProdukt');
    $lines = config_lines($config, $cat);
    $showPrices = setting('show_prices', '0') === '1';
    $priceTxt = price_range_text((float)$price['min'], (float)$price['max']);

    // До администратора
    $notify = setting('notify_email', '');
    if ($notify !== '') {
        $t = "Ново запитване $code\n\n";
        $t .= "Име: {$ct['name']}\nТелефон: {$ct['phone']}\nИмейл: " . ($ct['email'] ?: '—') . "\nНаселено място: " . ($ct['city'] ?: '—') . "\n";
        $t .= 'Бележка: ' . ($ct['note'] ?: '—') . "\n\n";
        foreach ($lines as $k => $v) {
            $t .= "$k: $v\n";
        }
        $t .= "\nОриентировъчна цена: $priceTxt" . ($showPrices ? '' : ' (не се показва на клиента)') . "\n";
        $t .= "\nИзточник: " . ($src['utm_source'] ?: '—') . ' / ' . ($src['utm_medium'] ?: '—') . ' / ' . ($src['utm_campaign'] ?: '—');
        $t .= "\nReferrer: " . ($src['referrer'] ?: '—') . "\n";
        $t .= "\nДетайли: " . abs_url('admin/inquiry.php?id=' . $id) . "\n";

        $rows = '';
        foreach (array_merge([
            'Име' => $ct['name'], 'Телефон' => $ct['phone'], 'Имейл' => $ct['email'] ?: '—',
            'Населено място' => $ct['city'] ?: '—', 'Бележка' => $ct['note'] ?: '—',
        ], $lines, ['Ориентировъчна цена' => $priceTxt]) as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee"><b>' . nl2br(e($v)) . '</b></td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222"><h2 style="margin:0 0 12px">Ново запитване ' . e($code) . '</h2>'
            . '<table style="border-collapse:collapse">' . $rows . '</table>'
            . '<p style="margin-top:16px"><a href="' . e(abs_url('admin/inquiry.php?id=' . $id)) . '">Отвори в админ панела</a></p>'
            . '<p style="color:#888;font-size:13px">Прегледът е прикачен като preview.svg.</p></div>';
        $att = $svg ? [['name' => 'preview-' . $code . '.svg', 'type' => 'image/svg+xml', 'data' => $svg]] : [];
        send_mail($notify, "Ново запитване $code – " . (!empty($config['custom']) ? ($config['model']['form_name'] ?? $config['model']['name']) . ' – размер по избор' : $config['model']['name']) . " ({$ct['name']})", $t, $html, $att, $id);
    }

    // Потвърждение до клиента
    if ($ct['email'] !== '') {
        $t = "Здравейте, {$ct['name']}!\n\nПолучихме вашето запитване $code. Ще се свържем с вас до 1 работен ден с оферта и проект за одобрение.\n\n";
        foreach ($lines as $k => $v) {
            $t .= "$k: $v\n";
        }
        if ($showPrices) {
            $t .= "\nОриентировъчна цена: $priceTxt\n";
        }
        $t .= "\nИзгледът в конфигуратора е ориентировъчен. Преди изработка ще получите финален проект за одобрение.\n\nПоздрави,\n$site\n";
        $rows = '';
        foreach ($lines as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee">' . e($v) . '</td></tr>';
        }
        if ($showPrices) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666">Ориентировъчна цена</td><td style="padding:6px 10px"><b>' . e($priceTxt) . '</b></td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px">'
            . '<p>Здравейте, ' . e($ct['name']) . '!</p><p>Получихме вашето запитване <b>' . e($code) . '</b>. Ще се свържем с вас до 1 работен ден с оферта и проект за одобрение.</p>'
            . '<table style="border-collapse:collapse;width:100%">' . $rows . '</table>'
            . '<p style="color:#666;font-size:13px">Изгледът в конфигуратора е ориентировъчен. Преди изработка ще получите финален проект за одобрение.</p>'
            . '<p>Поздрави,<br>' . e($site) . '</p></div>';
        send_mail($ct['email'], "Получихме запитването ви $code", $t, $html, [], $id);
    }
}
