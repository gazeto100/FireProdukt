<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$id = (int)($_GET['id'] ?? 0);
$model = $id ? db_one('SELECT * FROM models WHERE id = ?', [$id]) : null;
if ($id && !$model) {
    redirect(url('admin/models.php'));
}
$types = db_all('SELECT id, name FROM product_types ORDER BY sort, id');
$allModels = db_all('SELECT id, name FROM models ORDER BY sort, id');
$formsList = db_all('SELECT f.id, f.name, f.shape, t.name AS type_name FROM forms f JOIN product_types t ON t.id = f.type_id ORDER BY t.sort, f.sort');
$shapes = model_shapes();
$errors = [];

$v = $model ?: [
    'type_id' => $types[0]['id'] ?? 0, 'form_id' => (int)($_GET['form'] ?? 0), 'size_label' => '', 'code' => '', 'name' => '', 'shape' => 'box', 'width_cm' => '', 'depth_cm' => '', 'height_cm' => '',
    'max_chars' => 12, 'price_min' => '', 'price_max' => '', 'sheet_area_m2' => '', 'description' => '', 'sort' => 10, 'active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete' && $model) {
        $used = (int)db_one('SELECT COUNT(*) c FROM inquiries WHERE model_id = ?', [$id])['c'];
        db()->prepare('DELETE FROM models WHERE id = ?')->execute([$id]);
        flash('Моделът „' . $model['name'] . '“ е изтрит.' . ($used ? " $used запитвания го пазят в записаната си конфигурация." : ''));
        redirect(url('admin/models.php'));
    }

    $v = [
        'type_id' => (int)($_POST['type_id'] ?? 0),
        'form_id' => (int)($_POST['form_id'] ?? 0) ?: null,
        'size_label' => mb_substr(trim((string)($_POST['size_label'] ?? '')), 0, 40),
        'code' => trim((string)($_POST['code'] ?? '')),
        'name' => trim((string)($_POST['name'] ?? '')),
        'shape' => (string)($_POST['shape'] ?? 'box'),
        'width_cm' => post_num('width_cm'),
        'depth_cm' => post_num('depth_cm'),
        'height_cm' => post_num('height_cm'),
        'max_chars' => TEXT_MAX_CHARS, // вече не е ограничение за клиента – само технически таван
        'price_min' => post_num('price_min'),
        'price_max' => post_num('price_max'),
        'sheet_area_m2' => post_num('sheet_area_m2'),
        'description' => trim((string)($_POST['description'] ?? '')),
        'sort' => (int)($_POST['sort'] ?? 0),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if (in_array($v['shape'], ['hex', 'octagon', 'cross', 'ringbowl'], true)) {
        $v['depth_cm'] = $v['width_cm']; // при кръглите форми дълбочината = диаметъра
    }
    if ($v['form_id'] && !in_array($v['form_id'], array_map('intval', array_column($formsList, 'id')), true)) $v['form_id'] = null;
    if (!in_array($v['type_id'], array_map('intval', array_column($types, 'id')), true)) $errors[] = 'Изберете вид.';
    if (!preg_match('/^[a-z0-9_]{2,32}$/', $v['code'])) $errors[] = 'Кодът: 2–32 малки латински букви, цифри или _ (напр. sq_xl).';
    elseif (db_one('SELECT id FROM models WHERE code = ? AND id <> ?', [$v['code'], $id])) $errors[] = 'Вече има модел с този код.';
    if ($v['name'] === '') $errors[] = 'Въведете име.';
    if (!isset($shapes[$v['shape']])) $errors[] = 'Невалидна форма.';
    foreach (['width_cm' => 'Ширина/диаметър', 'depth_cm' => 'Дълбочина', 'height_cm' => 'Височина'] as $k => $label) {
        if ($v[$k] === null || $v[$k] < 5 || $v[$k] > 300) $errors[] = "$label трябва да е между 5 и 300 см.";
    }
    if ($v['price_min'] === null || $v['price_max'] === null || $v['price_min'] < 0 || $v['price_max'] < $v['price_min']) $errors[] = 'Цена: „от“ ≥ 0 и „до“ ≥ „от“.';
    if ($v['sheet_area_m2'] !== null && ($v['sheet_area_m2'] <= 0 || $v['sheet_area_m2'] > 20)) $errors[] = 'Площта на ламарината трябва да е между 0 и 20 м² (или празно).';

    if (!$errors) {
        $params = [$v['type_id'], $v['form_id'], $v['size_label'] !== '' ? $v['size_label'] : null, $v['code'], mb_substr($v['name'], 0, 100), $v['shape'], $v['width_cm'], $v['depth_cm'], $v['height_cm'],
            $v['max_chars'], $v['price_min'], $v['price_max'], $v['sheet_area_m2'], $v['description'] !== '' ? mb_substr($v['description'], 0, 500) : null, $v['sort'], $v['active']];
        if ($model) {
            $params[] = $id;
            db()->prepare('UPDATE models SET type_id=?, form_id=?, size_label=?, code=?, name=?, shape=?, width_cm=?, depth_cm=?, height_cm=?, max_chars=?, price_min=?, price_max=?, sheet_area_m2=?, description=?, sort=?, active=? WHERE id=?')->execute($params);
            flash('Моделът е запазен.');
        } else {
            db()->prepare('INSERT INTO models (type_id, form_id, size_label, code, name, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, sheet_area_m2, description, sort, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($params);
            $id = (int)db()->lastInsertId();
            $copy = (int)($_POST['copy_from'] ?? 0);
            if ($copy) {
                db()->prepare('INSERT INTO model_options (model_id, option_id, price_min, price_max, is_default) SELECT ?, option_id, price_min, price_max, is_default FROM model_options WHERE model_id = ?')->execute([$id, $copy]);
            }
            flash('Моделът е създаден.' . ($copy ? ' Съвместимостите са копирани – прегледайте ги.' : ' Изберете опциите му в „Съвместимост“.'));
            redirect(url($copy ? 'admin/compat.php?model=' . $id : 'admin/compat.php?model=' . $id));
        }
        redirect(url('admin/model_edit.php?id=' . $id));
    }
}

// ориентир: колко главни букви се побират при минималната височина
$cap = [];
if ($model && !$errors) {
    $m = ['shape' => $model['shape'], 'w' => (float)$model['width_cm'], 'd' => (float)$model['depth_cm'], 'h' => (float)$model['height_cm'], 'sheet_area_m2' => $model['sheet_area_m2'] !== null ? (float)$model['sheet_area_m2'] : null];
    $minMm = min_letter_for_thickness(2);
    foreach (font_metrics()['fonts'] as $k => $f) {
        $cap[$f['label']] = [text_capacity_estimate(decorated_walls($m, 1), $k, $minMm), text_capacity_estimate(decorated_walls($m, 'all'), $k, $minMm)];
    }
    $area = sheet_area_m2(array_merge($m, ['sheet_area_m2' => null]));
}

$num = fn($x) => ($x === '' || $x === null) ? '' : fmt_num($x);
admin_header($model ? 'Модел ' . $model['name'] : 'Нов модел', 'models');
?>
<p><a href="models.php">← Модели</a></p>
<h1><?= $model ? e($model['name']) : 'Нов модел' ?></h1>
<?php foreach ($errors as $er): ?><div class="flash flash-err"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid2">
<form method="post" class="card settings" id="modelForm">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="two">
    <label>Вид
      <select name="type_id"><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$v['type_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Код (латиница)<input name="code" value="<?= e($v['code']) ?>" pattern="[a-z0-9_]{2,32}" required></label>
  </div>
  <label>Име<input name="name" value="<?= e($v['name']) ?>" maxlength="100" required></label>
  <div class="two">
    <label>Форма (група размери)
      <select name="form_id"><option value="0">— самостоятелен модел —</option><?php foreach ($formsList as $fl): ?><option value="<?= (int)$fl['id'] ?>" <?= (int)($v['form_id'] ?? 0) === (int)$fl['id'] ? 'selected' : '' ?>><?= e($fl['type_name'] . ' › ' . $fl['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label>Надпис на бутона за размер<input name="size_label" value="<?= e($v['size_label'] ?? '') ?>" maxlength="40" placeholder="напр. M · 50×50×35 или Ø60"></label>
  </div>
  <label>Форма (как се рисува)
    <select name="shape" id="shape"><?php foreach ($shapes as $k => $label): ?><option value="<?= e($k) ?>" <?= $v['shape'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
  </label>
  <div class="three">
    <label><span id="wLabel">Ширина, см</span><input name="width_cm" inputmode="decimal" value="<?= e($num($v['width_cm'])) ?>" required></label>
    <label id="dWrap">Дълбочина, см<input name="depth_cm" inputmode="decimal" value="<?= e($num($v['depth_cm'])) ?>"></label>
    <label>Височина, см<input name="height_cm" inputmode="decimal" value="<?= e($num($v['height_cm'])) ?>" required></label>
  </div>
  <p class="muted small" id="hexNote" hidden>При кръглите форми (шестоъгълно, осмоъгълно, кръстато, купа) ширината е диаметърът (през ъглите), а дълбочината се приравнява на него.</p>
  <div class="two">
    <label>Ред в списъка<input name="sort" type="number" value="<?= (int)$v['sort'] ?>"></label>
  </div>
  <div class="two">
    <label>Базова цена от (<?= e(setting('currency', '€')) ?>)<input name="price_min" inputmode="decimal" value="<?= e($v['price_min']) ?>" required></label>
    <label>до<input name="price_max" inputmode="decimal" value="<?= e($v['price_max']) ?>" required></label>
  </div>
  <label>Площ на ламарината, м² <span class="muted small">(празно = изчислява се от размерите<?= isset($area) ? ': ≈ ' . e(number_format($area, 3, ',', '')) . ' м²' : '' ?>)</span>
    <input name="sheet_area_m2" inputmode="decimal" value="<?= e($v['sheet_area_m2']) ?>"></label>
  <label>Описание<textarea name="description" rows="2" maxlength="500"><?= e($v['description']) ?></textarea></label>
  <?php if (!$model): ?>
    <label>Копирай съвместимостите (опции и цени) от
      <select name="copy_from"><option value="0">— без, ще ги избера —</option><?php foreach ($allModels as $am): ?><option value="<?= (int)$am['id'] ?>"><?= e($am['name']) ?></option><?php endforeach; ?></select>
    </label>
  <?php endif; ?>
  <label class="check"><input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?>> Показва се в конфигуратора</label>
  <div class="actions"><button class="a-btn">Запази</button></div>
</form>

<?php if ($model): ?>
<div>
  <section class="card">
    <h2>Колко знака се побират</h2>
    <p class="muted small">Ориентир на един ред, главни букви, при минималната буква за 2 мм (<?= e(fmt_num(min_letter_for_thickness(2))) ?> мм). Ограничение е само височината; на два реда се побират около двойно повече.</p>
    <table class="kv">
      <tr><th>Шрифт</th><td><b>1 страна</b></td><td><b>Всички страни</b></td></tr>
      <?php foreach ($cap as $label => $c): ?><tr><th><?= e($label) ?></th><td>≈ <?= (int)$c[0] ?></td><td>≈ <?= (int)$c[1] ?></td></tr><?php endforeach; ?>
    </table>
  </section>
  <section class="card">
    <h2>Опции на модела</h2>
    <p><a class="a-btn a-btn-ghost" href="compat.php?model=<?= (int)$id ?>">Съвместимост и цени →</a></p>
    <p><a href="<?= e(url('configurator.php?model=' . rawurlencode($model['code']))) ?>" target="_blank">Отвори в конфигуратора ↗</a></p>
  </section>
  <form method="post" class="card" onsubmit="return confirm('Да изтрия ли модела „<?= e(addslashes($model['name'])) ?>“? Съвместимостите му също се изтриват. Запитванията остават.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <h2>Изтриване</h2>
    <p class="muted small">Ако искате само да го скриете, махнете отметката „Показва се в конфигуратора“.</p>
    <button class="a-btn a-btn-danger">Изтрий модела</button>
  </form>
</div>
<?php endif; ?>
</div>
<script>
(function () {
  var s = document.getElementById('shape'), d = document.getElementById('dWrap'), wl = document.getElementById('wLabel'), n = document.getElementById('hexNote');
  function sync() { var hex = ['hex', 'octagon', 'cross', 'ringbowl'].indexOf(s.value) >= 0; d.hidden = hex; n.hidden = !hex; wl.textContent = hex ? 'Диаметър, см' : 'Ширина, см'; }
  s.addEventListener('change', sync); sync();
})();
</script>
<?php admin_footer(); ?>
