<?php
declare(strict_types=1);

function app_config(string $key, $default = null)
{
    return $GLOBALS['APP_CONFIG'][$key] ?? $default;
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Абсолютен път от корена на сайта, напр. url('admin/') -> /fireprodukt/admin/ */
function url(string $path = ''): string
{
    return rtrim((string)app_config('base_url', ''), '/') . '/' . ltrim($path, '/');
}

/** Пълен URL (с протокол и домейн) – за имейли */
function abs_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string)filemtime($file) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('fpsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url(''),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function settings_all(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null; // след миграция – новите настройки да се видят още в същата заявка
    }
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
}

/** Името на сайта – навсякъде от настройката (заглавия, горна лента, футър, подпис и подател на имейлите) */
function site_name(): string
{
    $n = trim((string)setting('site_name', ''));
    return $n !== '' ? $n : 'FireProdukt';
}

/** Срок, до който се свързваме с клиента (текст от настройките), напр. „2 работни дни“ */
function contact_deadline(): string
{
    $d = trim((string)setting('contact_deadline', ''));
    return $d !== '' ? $d : '2 работни дни';
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute([$key, $value]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ip_hash(): string
{
    return hash('sha256', client_ip() . '|' . app_config('app_key', ''));
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function money($v): string
{
    return number_format((float)$v, 0, ',', ' ') . ' ' . setting('currency', '€');
}

function price_range_text(?float $min, ?float $max): string
{
    if ($min === null || $max === null) {
        return '—';
    }
    if (round($min) === round($max)) {
        return money($min);
    }
    return number_format($min, 0, ',', ' ') . ' – ' . money($max);
}

function fmt_kg(float $kg): string
{
    return '≈ ' . number_format($kg, $kg < 10 ? 1 : 0, ',', ' ') . ' кг';
}

function str_limit(string $s, int $len): string
{
    $s = trim($s);
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) : $s;
}

/** Статуси на запитванията */
function inquiry_statuses(): array
{
    return [
        'new' => 'Ново',
        'called' => 'Обадихме се',
        'offer' => 'Оферта',
        'order' => 'Поръчка',
        'rejected' => 'Отказ',
    ];
}

/** Причини за отхвърляне на запитване (за дневника в админа) */
function rejected_reasons(): array
{
    return [
        'honeypot' => 'Скрито поле попълнено (бот)',
        'too_fast' => 'Изпратено под 3 сек. след отваряне',
        'rate_limit' => 'Лимит запитвания от един IP',
        'duplicate' => 'Повторно (същия телефон и конфигурация до 2 мин.)',
        'csrf' => 'Изтекла сесия (браузърът опитва повторно автоматично)',
    ];
}

/** Записва отхвърлено запитване: само дата, причина и брой – без лични данни */
function log_rejected(string $reason): void
{
    if (!isset(rejected_reasons()[$reason])) {
        return;
    }
    try {
        db()->prepare('INSERT INTO rejected_log (day, reason, cnt) VALUES (CURDATE(), ?, 1) ON DUPLICATE KEY UPDATE cnt = cnt + 1')->execute([$reason]);
    } catch (Throwable $e) {
        error_log('rejected_log: ' . $e->getMessage());
    }
}

function record_event(string $type, ?int $modelId = null): void
{
    static $allowed = ['configurator_view', 'model_select', 'text_entered', 'lead'];
    if (!in_array($type, $allowed, true)) {
        return;
    }
    start_session();
    $sid = substr(hash('sha256', session_id() . app_config('app_key', '')), 0, 32);
    try {
        $st = db()->prepare('INSERT INTO events (day, type, session_id, model_id) VALUES (CURDATE(), ?, ?, ?)');
        $st->execute([$type, $sid, $modelId]);
    } catch (Throwable $e) {
        error_log('event: ' . $e->getMessage());
    }
}
