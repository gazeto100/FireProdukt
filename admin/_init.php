<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function admin_id(): ?int
{
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function require_admin(): void
{
    if (!admin_id()) {
        redirect(url('admin/index.php'));
    }
}

/** Проверка на CSRF за всички POST заявки в админа */
function admin_post_guard(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Сесията изтече. Върнете се назад и презаредете страницата.');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function admin_header(string $title, string $active = ''): void
{
    $nav = [
        'inquiries' => ['inquiries.php', 'Запитвания'],
        'stats' => ['stats.php', 'Статистика'],
        'forms' => ['forms.php', 'Форми'],
        'models' => ['models.php', 'Модели'],
        'options' => ['options.php', 'Опции'],
        'compat' => ['compat.php', 'Съвместимост'],
        'settings' => ['settings.php', 'Настройки'],
    ];
    $f = flash();
    ?><!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> – <?= e(site_name()) ?> админ</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<?php if (admin_id()): ?>
<header class="a-head">
  <a class="a-brand" href="<?= e(url('admin/inquiries.php')) ?>"><?= e(site_name()) ?> · админ</a>
  <nav>
    <?php foreach ($nav as $k => $n): ?>
      <a href="<?= e(url('admin/' . $n[0])) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= e($n[1]) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('')) ?>" target="_blank">Сайт ↗</a>
    <a href="<?= e(url('admin/logout.php')) ?>">Изход</a>
  </nav>
</header>
<?php endif; ?>
<main class="a-main">
<?php if ($f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endif; ?>
<?php
}

/** Форми на моделите с обяснение */
function model_shapes(): array
{
    return [
        'box' => 'Правоъгълно огнище (квадратно/правоъгълно)',
        'hex' => 'Шестоъгълно огнище (ширина = диаметър през ъглите)',
        'grill' => 'Мангал / скара (нисък съд, по желание на стойка)',
        'octagon' => 'Осмоъгълно огнище (ширина = диаметър през ъглите)',
        'trapezoid' => 'Трапецовидно огнище (стени, наклонени навън; надпис отпред)',
        'pedestal' => 'Огнище на постамент (кутия с отвор за дърва + плитка купа)',
        'cross' => 'Огнище на кръстата стойка (тава върху две плочи на прорез)',
        'ringbowl' => 'Кръгла купа с пръстен (надпис на пръстена)',
        'tabletop' => 'Настолен мангал (ниски крачета, надпис отпред)',
        'suitcase' => 'Мангал куфар (отворен, надпис на капака)',
        'bbq' => 'Барбекю с капак на стойка (надпис на капака или отпред)',
        'plancha' => 'Огнище с плоча за печене – кръгло (Ø = диаметър на плочата)',
        'plancha_sq' => 'Огнище с плоча за печене – квадратно',
        // за хотели и обекти (v10)
        'bowl' => 'Кръгла купа на крак (обект; надпис в пояса по ръба)',
        'firetable' => 'Огнище маса на дърва (обект; купа в плота, надпис отстрани)',
        'ringbench' => 'Огнище с пейка-пръстен (обект; надпис на огнището, седалка от дърво)',
        'column' => 'Висока колона (обект; надпис горе, орнамент под него)',
        'cheverme' => 'Стойка за чеверме (корито за жар, две стойки, шиш)',
        'insert' => 'Вложка за зидано барбекю (тава и скара, без крака)',
    ];
}

/** Число от формуляр (приема и десетична запетая) */
function post_num(string $key): ?float
{
    $v = str_replace([' ', ','], ['', '.'], trim((string)($_POST[$key] ?? '')));
    return $v === '' || !is_numeric($v) ? null : (float)$v;
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
