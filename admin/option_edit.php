<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$id = (int)($_GET['id'] ?? 0);
$opt = $id ? db_one('SELECT * FROM options WHERE id = ?', [$id]) : null;
if ($id && !$opt) {
    redirect(url('admin/options.php'));
}
$groups = db_all('SELECT * FROM option_groups ORDER BY sort, id');
$groupById = array_column($groups, null, 'id');
$gid = $opt ? (int)$opt['group_id'] : (int)($_GET['group'] ?? ($_POST['group_id'] ?? 0));
if (!isset($groupById[$gid])) {
    redirect(url('admin/options.php'));
}
$group = $groupById[$gid];
$gcode = $group['code'];
$fonts = font_metrics()['fonts'];
$patterns = ['' => 'Без орнамент', 'flames' => 'Пламъци', 'mountains' => 'Планини', 'pines' => 'Борове', 'shevitsa' => 'Шевица'];
$extras = ['grate' => 'Скара (решетка отгоре)', 'arm' => 'Рамо с котле', 'side_table' => 'Страничен плот', 'lid' => 'Капак', 'bag' => 'Калъф', '' => 'Без изглед (само в текста)'];
$errors = [];

$render = $opt ? (json_decode((string)$opt['render'], true) ?: []) : [];
$v = $opt ?: ['code' => '', 'name' => '', 'hint' => '', 'price_type' => $gcode === 'material' ? 'factor' : 'add', 'price_min' => $gcode === 'material' ? 1 : 0,
    'price_max' => $gcode === 'material' ? 1 : 0, 'weight_kg' => 0, 'sort' => 10, 'active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete' && $opt) {
        db()->prepare('DELETE FROM options WHERE id = ?')->execute([$id]);
        flash('Опцията „' . $opt['name'] . '“ е изтрита (и премахната от всички модели).');
        redirect(url('admin/options.php'));
    }
    $v = [
        'code' => trim((string)($_POST['code'] ?? '')),
        'name' => trim((string)($_POST['name'] ?? '')),
        'hint' => trim((string)($_POST['hint'] ?? '')),
        'price_type' => ($_POST['price_type'] ?? 'add') === 'factor' ? 'factor' : 'add',
        'price_min' => post_num('price_min'),
        'price_max' => post_num('price_max'),
        'weight_kg' => post_num('weight_kg') ?? 0,
        'sort' => (int)($_POST['sort'] ?? 0),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if (!preg_match('/^[a-z0-9_]{1,32}$/', $v['code'])) $errors[] = 'Кодът: малки латински букви, цифри или _.';
    elseif (db_one('SELECT id FROM options WHERE group_id = ? AND code = ? AND id <> ?', [$gid, $v['code'], $id])) $errors[] = 'В тази група вече има опция с този код.';
    if ($v['name'] === '') $errors[] = 'Въведете име.';
    if ($v['price_min'] === null || $v['price_max'] === null || $v['price_min'] < 0 || $v['price_max'] < $v['price_min']) $errors[] = 'Цена: „от“ ≥ 0 и „до“ ≥ „от“.';
    if ($v['price_type'] === 'factor' && ($v['price_min'] <= 0 || $v['price_max'] > 10)) $errors[] = 'Коефициентът трябва да е между 0 и 10 (напр. 1,30).';
    if ($v['weight_kg'] < 0 || $v['weight_kg'] > 500) $errors[] = 'Невалидно тегло.';

    // параметри за изгледа и изчисленията – според групата
    $render = [];
    switch ($gcode) {
        case 'material':
            $render['finish'] = ($_POST['finish'] ?? 'inox') === 'black' ? 'black' : 'inox';
            $render['thickness_mm'] = post_num('thickness_mm');
            $render['density'] = post_num('density');
            if (!$render['thickness_mm'] || $render['thickness_mm'] < 0.5 || $render['thickness_mm'] > 20) $errors[] = 'Дебелина: 0,5–20 мм.';
            if (!$render['density'] || $render['density'] < 1 || $render['density'] > 20) $errors[] = 'Плътност: 1–20 кг/дм³ (инокс 7,93; стомана 7,85).';
            break;
        case 'legs':
            $render['leg_cm'] = post_num('leg_cm') ?? 0;
            if ($render['leg_cm'] < 0 || $render['leg_cm'] > 150) $errors[] = 'Височина на крачетата: 0–150 см.';
            break;
        case 'font':
            $render['font'] = (string)($_POST['font'] ?? '');
            if (!isset($fonts[$render['font']])) $errors[] = 'Изберете шрифт.';
            break;
        case 'sides':
            $s = (string)($_POST['sides'] ?? '1');
            $render['sides'] = $s === 'all' ? 'all' : max(1, min(6, (int)$s));
            break;
        case 'ornament':
            $p = (string)($_POST['pattern'] ?? '');
            $render['pattern'] = isset($patterns[$p]) && $p !== '' ? $p : null;
            if ($render['pattern'] && isset($_POST['per_side'])) $render['per_side'] = true;
            break;
        case 'extras':
            $x = (string)($_POST['extra'] ?? '');
            if (isset($extras[$x]) && $x !== '') $render['extra'] = $x;
            break;
    }

    if (!$errors) {
        $json = json_encode($render ?: new stdClass(), JSON_UNESCAPED_UNICODE);
        $params = [$v['code'], mb_substr($v['name'], 0, 100), $v['hint'] !== '' ? mb_substr($v['hint'], 0, 255) : null, $v['price_type'], $v['price_min'], $v['price_max'], $v['weight_kg'], $json, $v['sort'], $v['active']];
        if ($opt) {
            $params[] = $id;
            db()->prepare('UPDATE options SET code=?, name=?, hint=?, price_type=?, price_min=?, price_max=?, weight_kg=?, render=?, sort=?, active=? WHERE id=?')->execute($params);
            flash('Опцията е запазена.');
        } else {
            array_unshift($params, $gid);
            db()->prepare('INSERT INTO options (group_id, code, name, hint, price_type, price_min, price_max, weight_kg, render, sort, active) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($params);
            $id = (int)db()->lastInsertId();
            if (isset($_POST['add_all'])) {
                db()->prepare('INSERT INTO model_options (model_id, option_id) SELECT id, ? FROM models')->execute([$id]);
            }
            flash('Опцията е създадена.' . (isset($_POST['add_all']) ? ' Добавена е към всички модели.' : ' Добавете я към моделите в „Съвместимост“.'));
        }
        redirect(url('admin/option_edit.php?id=' . $id));
    }
}
$r = fn($k, $d = '') => e(isset($render[$k]) && $render[$k] !== null ? (is_float($render[$k]) || is_int($render[$k]) ? fmt_num($render[$k]) : $render[$k]) : $d);
$usedBy = $opt ? db_all('SELECT m.id, m.name FROM model_options mo JOIN models m ON m.id = mo.model_id WHERE mo.option_id = ? ORDER BY m.sort', [$id]) : [];

admin_header($opt ? 'Опция ' . $opt['name'] : 'Нова опция', 'options');
?>
<p><a href="options.php">← Опции</a></p>
<h1><?= $opt ? e($opt['name']) : 'Нова опция' ?> <span class="muted small">· <?= e($group['name']) ?></span></h1>
<?php foreach ($errors as $er): ?><div class="flash flash-err"><?= e($er) ?></div><?php endforeach; ?>

<div class="grid2">
<form method="post" class="card settings">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="group_id" value="<?= (int)$gid ?>">
  <div class="two">
    <label>Име<input name="name" value="<?= e($v['name']) ?>" maxlength="100" required></label>
    <label>Код (латиница)<input name="code" value="<?= e($v['code']) ?>" pattern="[a-z0-9_]{1,32}" required></label>
  </div>
  <label>Подсказка<input name="hint" value="<?= e($v['hint']) ?>" maxlength="255"></label>

  <fieldset>
    <legend>Цена (обща – за отделен модел се сменя в „Съвместимост“)</legend>
    <label>Вид
      <select name="price_type">
        <option value="add" <?= $v['price_type'] === 'add' ? 'selected' : '' ?>>Добавя сума (<?= e(setting('currency', '€')) ?>)</option>
        <option value="factor" <?= $v['price_type'] === 'factor' ? 'selected' : '' ?>>Коефициент към базовата цена на модела</option>
      </select>
    </label>
    <div class="two">
      <label>от<input name="price_min" inputmode="decimal" value="<?= e(fmt_num($v['price_min'] ?? 0)) ?>"></label>
      <label>до<input name="price_max" inputmode="decimal" value="<?= e(fmt_num($v['price_max'] ?? 0)) ?>"></label>
    </div>
  </fieldset>
  <label>Тегло, кг <span class="muted small">(добавя се към теглото на модела)</span><input name="weight_kg" inputmode="decimal" value="<?= e(fmt_num($v['weight_kg'] ?? 0)) ?>"></label>

  <fieldset>
    <legend>Изглед и изчисления</legend>
    <?php if ($gcode === 'material'): ?>
      <label>Повърхност в изгледа
        <select name="finish"><option value="inox" <?= ($render['finish'] ?? '') !== 'black' ? 'selected' : '' ?>>Шлайфан инокс</option><option value="black" <?= ($render['finish'] ?? '') === 'black' ? 'selected' : '' ?>>Черна стомана</option></select>
      </label>
      <div class="two">
        <label>Дебелина, мм<input name="thickness_mm" inputmode="decimal" value="<?= $r('thickness_mm', '2') ?>"></label>
        <label>Плътност, кг/дм³<input name="density" inputmode="decimal" value="<?= $r('density', '7,93') ?>"></label>
      </div>
    <?php elseif ($gcode === 'legs'): ?>
      <label>Височина на крачетата, см (0 = без)<input name="leg_cm" inputmode="decimal" value="<?= $r('leg_cm', '0') ?>"></label>
    <?php elseif ($gcode === 'font'): ?>
      <label>Шрифт
        <select name="font"><?php foreach ($fonts as $k => $f): ?><option value="<?= e($k) ?>" <?= ($render['font'] ?? '') === $k ? 'selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?></select>
      </label>
      <p class="muted small">Нов шрифт се добавя с <code>tools/fonts.py</code> (вижте README).</p>
    <?php elseif ($gcode === 'sides'): ?>
      <label>Брой декорирани страни
        <select name="sides"><?php foreach (['1' => '1', '2' => '2', '3' => '3', 'all' => 'Всички'] as $k => $label): ?><option value="<?= e($k) ?>" <?= (string)($render['sides'] ?? '1') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
    <?php elseif ($gcode === 'ornament'): ?>
      <label>Мотив
        <select name="pattern"><?php foreach ($patterns as $k => $label): ?><option value="<?= e($k) ?>" <?= (string)($render['pattern'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
      <label class="check"><input type="checkbox" name="per_side" value="1" <?= !empty($render['per_side']) || !$opt ? 'checked' : '' ?>> Цената е за всяка декорирана страна</label>
    <?php elseif ($gcode === 'extras'): ?>
      <label>Как се показва в изгледа
        <select name="extra"><?php foreach ($extras as $k => $label): ?><option value="<?= e($k) ?>" <?= (string)($render['extra'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </label>
    <?php endif; ?>
  </fieldset>

  <div class="two">
    <label>Ред<input name="sort" type="number" value="<?= (int)$v['sort'] ?>"></label>
    <label class="check"><input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?>> Активна</label>
  </div>
  <?php if (!$opt): ?><label class="check"><input type="checkbox" name="add_all" value="1"> Добави я към всички модели</label><?php endif; ?>
  <div class="actions"><button class="a-btn">Запази</button></div>
</form>

<?php if ($opt): ?>
<div>
  <section class="card">
    <h2>Важи за модели</h2>
    <?php if (!$usedBy): ?><p class="muted">Не е добавена към нито един модел – не се показва в конфигуратора.</p><?php endif; ?>
    <ul><?php foreach ($usedBy as $u): ?><li><a href="compat.php?model=<?= (int)$u['id'] ?>"><?= e($u['name']) ?></a></li><?php endforeach; ?></ul>
  </section>
  <form method="post" class="card" onsubmit="return confirm('Да изтрия ли опцията „<?= e(addslashes($opt['name'])) ?>“? Ще бъде премахната от всички модели.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <h2>Изтриване</h2>
    <p class="muted small">Запитванията пазят името на опцията в записаната си конфигурация.</p>
    <button class="a-btn a-btn-danger">Изтрий опцията</button>
  </form>
</div>
<?php endif; ?>
</div>
<?php admin_footer(); ?>
