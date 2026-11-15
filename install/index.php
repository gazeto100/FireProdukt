<?php
declare(strict_types=1);
/**
 * Уеб инсталатор: създава таблиците, примерното съдържание, администратора и config/config.php.
 * След успешна инсталация се заключва (config/installed.lock). Препоръчително е папката /install да се изтрие.
 */
mb_internal_encoding('UTF-8');
$root = dirname(__DIR__);
$lock = $root . '/config/installed.lock';
$cfgFile = $root . '/config/config.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$baseUrl = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$checks = [
    'PHP 8.1 или по-нова (имате ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Разширение PDO MySQL' => extension_loaded('pdo_mysql'),
    'Разширение mbstring' => extension_loaded('mbstring'),
    'Разширение OpenSSL' => extension_loaded('openssl'),
    'Разширение DOM (за почистване на SVG)' => extension_loaded('dom'),
    'Папката config/ позволява запис' => is_writable($root . '/config'),
];
$allOk = !in_array(false, $checks, true);

$errors = [];
$done = false;
$v = [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'admin_user' => 'admin', 'admin_pass' => '', 'admin_pass2' => '', 'notify_email' => '', 'site_name' => 'FireProdukt',
];

if (is_file($lock)) {
    $locked = true;
} else {
    $locked = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
        foreach ($v as $k => $_) {
            $v[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $v['db_pass'] = (string)($_POST['db_pass'] ?? '');
        $v['admin_pass'] = (string)($_POST['admin_pass'] ?? '');
        $v['admin_pass2'] = (string)($_POST['admin_pass2'] ?? '');

        if ($v['db_host'] === '' || $v['db_name'] === '' || $v['db_user'] === '') $errors[] = 'Попълнете данните за базата.';
        if (!preg_match('/^[A-Za-z0-9_]{3,64}$/', $v['admin_user'])) $errors[] = 'Потребителското име: 3–64 латински букви, цифри или _.';
        if (mb_strlen($v['admin_pass']) < 10) $errors[] = 'Паролата трябва да е поне 10 знака.';
        if ($v['admin_pass'] !== $v['admin_pass2']) $errors[] = 'Паролите не съвпадат.';
        if (!filter_var($v['notify_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Въведете валиден имейл за известия.';

        if (!$errors) {
            try {
                $host = $v['db_host'];
                $port = null;
                if (preg_match('/^(.+):(\d+)$/', $host, $mm)) { $host = $mm[1]; $port = (int)$mm[2]; }
                $dsnBase = 'mysql:host=' . $host . ($port ? ';port=' . $port : '') . ';charset=utf8mb4';
                $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
                $pdo = new PDO($dsnBase, $v['db_user'], $v['db_pass'], $opt);
                try {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $v['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                } catch (Throwable $e) {
                    // На споделен хостинг базата се създава от контролния панел – продължаваме.
                }
                $pdo->exec('USE `' . str_replace('`', '', $v['db_name']) . '`');
                $exists = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
                if ($exists) {
                    throw new RuntimeException('Базата вече съдържа таблици на сайта. Изтрийте ги или изберете друга база.');
                }
                foreach (['schema.sql', 'seed.sql'] as $f) {
                    $sql = (string)file_get_contents(__DIR__ . '/' . $f);
                    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
                        if (trim($stmt) !== '') {
                            $pdo->exec($stmt);
                        }
                    }
                }
                $st = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
                $st->execute([$v['admin_user'], password_hash($v['admin_pass'], PASSWORD_DEFAULT)]);
                $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
                $st->execute(['notify_email', $v['notify_email']]);
                $st->execute(['site_name', $v['site_name'] !== '' ? $v['site_name'] : 'FireProdukt']);
                $st->execute(['mail_from_name', $v['site_name'] !== '' ? $v['site_name'] : 'FireProdukt']);

                $config = [
                    'db' => ['host' => $host, 'port' => $port, 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $v['db_pass']],
                    'app_key' => bin2hex(random_bytes(32)),
                    'base_url' => $baseUrl,
                    'debug' => false,
                ];
                $php = "<?php\n// Създаден от инсталатора на " . date('Y-m-d H:i') . "\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents($cfgFile, $php) === false) {
                    throw new RuntimeException('Неуспешен запис на config/config.php.');
                }
                file_put_contents($lock, date('c'));
                $done = true;
            } catch (Throwable $e) {
                $errors[] = 'Грешка: ' . $e->getMessage();
            }
        }
    }
}
?><!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Инсталация</title>
<style>
  :root { color-scheme: light; }
  body { font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f1ec; color: #221d19; margin: 0; padding: 16px; }
  main { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
  h1 { font-size: 1.4rem; margin: 0 0 12px; }
  h2 { font-size: 1.05rem; margin: 22px 0 8px; }
  label { display: block; font-weight: 600; margin: 12px 0 4px; font-size: .95rem; }
  input { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #cfc6bb; border-radius: 8px; font-size: 16px; }
  button { margin-top: 20px; width: 100%; padding: 14px; border: 0; border-radius: 10px; background: #e2620c; color: #fff; font-size: 1.05rem; font-weight: 700; cursor: pointer; }
  .ok { color: #1d7a3a; } .bad { color: #b3261e; }
  ul.checks { list-style: none; padding: 0; margin: 0; } ul.checks li::before { content: attr(data-mark) " "; }
  .err { background: #fdecea; color: #8a1c14; padding: 10px 12px; border-radius: 8px; margin: 10px 0; }
  .success { background: #e7f5ec; padding: 12px; border-radius: 8px; }
  small { color: #6b625a; }
</style>
</head>
<body>
<main>
<h1>Инсталация на сайта</h1>
<?php if ($locked): ?>
  <p class="success">Сайтът вече е инсталиран. За повторна инсталация изтрийте <code>config/installed.lock</code> и <code>config/config.php</code>.</p>
  <p><strong>Препоръка:</strong> изтрийте папката <code>/install</code> от сървъра.</p>
<?php elseif ($done): ?>
  <div class="success">
    <p><strong>Готово!</strong> Базата, примерното съдържание и администраторът са създадени.</p>
    <p><a href="<?= h($baseUrl) ?>/">Към сайта</a> · <a href="<?= h($baseUrl) ?>/admin/">Към админ панела</a></p>
  </div>
  <p><strong>Важно:</strong> изтрийте папката <code>/install</code> от сървъра. Настройте SMTP за имейлите от <em>Админ → Настройки</em>.</p>
<?php else: ?>
  <h2>Проверка на сървъра</h2>
  <ul class="checks">
  <?php foreach ($checks as $label => $ok): ?>
    <li class="<?= $ok ? 'ok' : 'bad' ?>" data-mark="<?= $ok ? '✓' : '✗' ?>"><?= h($label) ?></li>
  <?php endforeach; ?>
  </ul>
  <?php foreach ($errors as $er): ?><div class="err"><?= h($er) ?></div><?php endforeach; ?>
  <?php if ($allOk): ?>
  <form method="post" autocomplete="off">
    <h2>База данни</h2>
    <small>На SuperHosting: създайте базата и потребителя от cPanel → MySQL бази данни, после ги въведете тук.</small>
    <label for="db_host">Хост</label><input id="db_host" name="db_host" value="<?= h($v['db_host']) ?>" required>
    <label for="db_name">Име на базата</label><input id="db_name" name="db_name" value="<?= h($v['db_name']) ?>" required>
    <label for="db_user">Потребител</label><input id="db_user" name="db_user" value="<?= h($v['db_user']) ?>" required>
    <label for="db_pass">Парола</label><input id="db_pass" name="db_pass" type="password">

    <h2>Администратор</h2>
    <label for="admin_user">Потребителско име</label><input id="admin_user" name="admin_user" value="<?= h($v['admin_user']) ?>" required>
    <label for="admin_pass">Парола (поне 10 знака)</label><input id="admin_pass" name="admin_pass" type="password" minlength="10" required>
    <label for="admin_pass2">Повторете паролата</label><input id="admin_pass2" name="admin_pass2" type="password" minlength="10" required>
    <label for="notify_email">Имейл за известия за нови запитвания</label><input id="notify_email" name="notify_email" type="email" value="<?= h($v['notify_email']) ?>" required>
    <label for="site_name">Име на сайта</label><input id="site_name" name="site_name" value="<?= h($v['site_name']) ?>">

    <button type="submit">Инсталирай</button>
  </form>
  <?php else: ?>
    <p class="err">Отстранете проблемите по-горе и презаредете страницата.</p>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
