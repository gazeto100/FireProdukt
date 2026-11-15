<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();
require_once APP_ROOT . '/inc/migrate_v6.php';

/** Граници за размер по избор от формата; връща [стойности, грешка] */
function custom_limits_from_post(): array
{
    $v = [];
    foreach (['w_min', 'w_max', 'd_min', 'd_max', 'h_min', 'h_max', 'step_cm', 'max_h_ratio', 'custom_fixed'] as $k) {
        $v[$k] = (float)str_replace(',', '.', (string)($_POST[$k] ?? '0'));
    }
    foreach (['w' => 'ширина', 'd' => 'дълбочина', 'h' => 'височина'] as $k => $l) {
        if ($v[$k . '_min'] < 5 || $v[$k . '_max'] > 300 || $v[$k . '_min'] > $v[$k . '_max']) {
            return [null, 'Размер по избор: ' . $l . ' – минимумът трябва да е поне 5 см, не повече от максимума, а максимумът до 300 см.'];
        }
    }
    if ($v['step_cm'] < 0.5 || $v['step_cm'] > 50) return [null, 'Стъпката трябва да е между 0,5 и 50 см.'];
    if ($v['max_h_ratio'] <= 0 || $v['max_h_ratio'] > 5) return [null, 'Отношението височина/ширина трябва да е между 0,1 и 5.'];
    if ($v['custom_fixed'] < 0) return [null, 'Фиксираната сума не може да е отрицателна.'];
    $v['custom_enabled'] = isset($_POST['custom_enabled']) ? 1 : 0;
    return [$v, null];
}

$types = db_all('SELECT id, code, name FROM product_types ORDER BY sort, id');
$shapes = model_shapes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        flash('Въведете име на формата.', 'err');
    } elseif ($id && ([$lim, $limErr] = custom_limits_from_post()) && $limErr) {
        flash($limErr, 'err');
    } elseif ($id) {
        db()->prepare('UPDATE forms SET name = ?, description = ?, sort = ?, active = ?, custom_enabled = ?, w_min = ?, w_max = ?, d_min = ?, d_max = ?, h_min = ?, h_max = ?, step_cm = ?, max_h_ratio = ?, custom_fixed = ? WHERE id = ?')
            ->execute([mb_substr($name, 0, 100), trim((string)($_POST['description'] ?? '')) ?: null, (int)($_POST['sort'] ?? 0), isset($_POST['active']) ? 1 : 0,
                $lim['custom_enabled'], $lim['w_min'], $lim['w_max'], $lim['d_min'], $lim['d_max'], $lim['h_min'], $lim['h_max'], $lim['step_cm'], $lim['max_h_ratio'], $lim['custom_fixed'], $id]);
        flash('Формата е запазена.' . (isset($_POST['active']) ? '' : ' Тя е скрита – размерите ѝ не се показват в конфигуратора и на началната страница.'));
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $type = (int)($_POST['type_id'] ?? 0);
        $shape = (string)($_POST['shape'] ?? 'box');
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $code) || db_one('SELECT id FROM forms WHERE code = ?', [$code])) {
            flash('Кодът трябва да е уникален: 2–32 малки латински букви, цифри или _.', 'err');
        } elseif (!isset($shapes[$shape]) || !in_array($type, array_map('intval', array_column($types, 'id')), true)) {
            flash('Изберете вид и форма за рисуване.', 'err');
        } else {
            $dd = form_custom_defaults()[$shape] ?? form_custom_defaults()['box'];
            db()->prepare('INSERT INTO forms (code, type_id, name, shape, description, sort, active, custom_enabled, w_min, w_max, d_min, d_max, h_min, h_max, max_h_ratio, custom_fixed)
                VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge([$code, $type, mb_substr($name, 0, 100), $shape, trim((string)($_POST['description'] ?? '')) ?: null, (int)($_POST['sort'] ?? 50)], $dd));
            flash('Формата е добавена. Създайте ѝ размери в „Модели → Нов модел“.');
        }
    }
    redirect(url('admin/forms.php'));
}

$forms = db_all('SELECT f.*, t.name AS type_name, (SELECT COUNT(*) FROM models m WHERE m.form_id = f.id) AS sizes,
    (SELECT GROUP_CONCAT(COALESCE(m.size_label, m.name) ORDER BY m.sort SEPARATOR ", ") FROM models m WHERE m.form_id = f.id) AS size_list
  FROM forms f JOIN product_types t ON t.id = f.type_id ORDER BY t.sort, f.sort, f.id');

admin_header('Форми', 'forms');
?>
<h1>Форми</h1>
<p class="muted small">Формата е картата в стъпка „Форма“ и на началната страница; размерите ѝ са моделите. Махнете отметката „Показва се“, за да скриете цяла форма (напр. още непотвърдена за производство).</p>
<?php foreach ($forms as $fm): ?>
  <form method="post" class="card type-row form-row <?= $fm['active'] ? '' : 'inactive-card' ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$fm['id'] ?>">
    <div class="form-head">
      <b><?= e($fm['type_name']) ?></b> · <code class="small"><?= e($fm['code']) ?></code> · <span class="muted small"><?= e(explode(' (', $shapes[$fm['shape']] ?? $fm['shape'])[0]) ?></span>
      <span class="muted small"> · размери: <?= $fm['sizes'] ? e($fm['size_list']) : '<span class="warn">няма</span>' ?></span>
    </div>
    <div class="inline">
      <label>Име<input name="name" value="<?= e($fm['name']) ?>" required></label>
      <label class="grow">Описание<input name="description" value="<?= e($fm['description']) ?>"></label>
      <label class="narrow">Ред<input name="sort" type="number" value="<?= (int)$fm['sort'] ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $fm['active'] ? 'checked' : '' ?>> Показва се</label>
      <button class="a-btn a-btn-ghost">Запази</button>
    </div>
    <details class="custom-lim">
      <summary class="small">Размер по избор: <?= $fm['custom_enabled'] ? e(sprintf('ширина %s–%s, дълбочина %s–%s, височина %s–%s см; стъпка %s см; височина ≤ %s × ширината; фиксирана сума %s', fmt_num($fm['w_min']), fmt_num($fm['w_max']), fmt_num($fm['d_min']), fmt_num($fm['d_max']), fmt_num($fm['h_min']), fmt_num($fm['h_max']), fmt_num($fm['step_cm']), fmt_num($fm['max_h_ratio']), fmt_num($fm['custom_fixed']) . ' ' . setting('currency', '€'))) : '<span class="warn">изключен</span>' ?></summary>
      <div class="inline">
        <label class="check"><input type="checkbox" name="custom_enabled" value="1" <?= $fm['custom_enabled'] ? 'checked' : '' ?>> Позволен</label>
        <?php foreach (['w' => 'Ширина/диаметър', 'd' => 'Дълбочина', 'h' => 'Височина'] as $k => $l): ?>
          <label class="lim"><?= e($l) ?>, см (от – до)<span class="pair"><input name="<?= $k ?>_min" type="number" step="0.5" min="5" max="300" value="<?= e($fm[$k . '_min'] ?? '') ?>" aria-label="<?= e($l) ?> от"> – <input name="<?= $k ?>_max" type="number" step="0.5" min="5" max="300" value="<?= e($fm[$k . '_max'] ?? '') ?>" aria-label="<?= e($l) ?> до"></span></label>
        <?php endforeach; ?>
        <label class="lim1">Стъпка, см<input name="step_cm" type="number" step="0.5" min="0.5" value="<?= e($fm['step_cm']) ?>"></label>
        <label class="lim1">Височина ≤ × ширината<input name="max_h_ratio" type="number" step="0.05" min="0.1" value="<?= e($fm['max_h_ratio']) ?>"></label>
        <label class="lim1">Фиксирана сума, <?= e(setting('currency', '€')) ?><input name="custom_fixed" type="number" step="1" min="0" value="<?= e($fm['custom_fixed']) ?>"></label>
      </div>
      <p class="muted small">Цена за размер по избор = (площ на листа m² × цена на m² на материала + фиксираната сума) × (1 + надценката от „Настройки“) + екстрите. При кръгли форми се задава само диаметър и височина; при квадратни – ширина (= дълбочина) и височина.</p>
    </details>
  </form>
<?php endforeach; ?>

<details class="card">
  <summary><b>+ Нова форма</b></summary>
  <form method="post" class="settings">
    <?= csrf_field() ?>
    <div class="two">
      <label>Код (латиница)<input name="code" pattern="[a-z0-9_]{2,32}" required></label>
      <label>Име<input name="name" required></label>
    </div>
    <div class="two">
      <label>Вид<select name="type_id"><?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
      <label>Как се рисува<select name="shape"><?php foreach ($shapes as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Описание<input name="description"></label>
    <label>Ред<input name="sort" type="number" value="50"></label>
    <button class="a-btn">Добави</button>
  </form>
</details>
<?php admin_footer(); ?>
