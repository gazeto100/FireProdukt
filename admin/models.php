<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

// Типове (Огнище / Скара-мангал): редакция на ред и добавяне
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'type_save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        if ($name === '') {
            flash('Въведете име на вида.', 'err');
        } elseif ($id) {
            db()->prepare('UPDATE product_types SET name = ?, hint = ?, sort = ?, active = ? WHERE id = ?')
                ->execute([mb_substr($name, 0, 100), trim((string)($_POST['hint'] ?? '')) ?: null, (int)($_POST['sort'] ?? 0), isset($_POST['active']) ? 1 : 0, $id]);
            flash('Видът е запазен.');
        } elseif (!preg_match('/^[a-z0-9_]{2,32}$/', $code)) {
            flash('Кодът на вида: 2–32 малки латински букви, цифри или _.', 'err');
        } elseif (db_one('SELECT id FROM product_types WHERE code = ?', [$code])) {
            flash('Вече има вид с такъв код.', 'err');
        } else {
            db()->prepare('INSERT INTO product_types (code, name, hint, sort, active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$code, mb_substr($name, 0, 100), trim((string)($_POST['hint'] ?? '')) ?: null, (int)($_POST['sort'] ?? 0)]);
            flash('Видът е добавен.');
        }
    }
    redirect(url('admin/models.php'));
}

$types = db_all('SELECT * FROM product_types ORDER BY sort, id');
$models = db_all('SELECT m.*, t.name AS type_name, f.name AS form_name, f.active AS form_active, (SELECT COUNT(*) FROM model_options mo WHERE mo.model_id = m.id) AS opts
  FROM models m JOIN product_types t ON t.id = m.type_id LEFT JOIN forms f ON f.id = m.form_id ORDER BY t.sort, f.sort, m.sort, m.id');
$shapes = model_shapes();

admin_header('Модели', 'models');
?>
<div class="a-title">
  <h1>Модели</h1>
  <a class="a-btn" href="model_edit.php">+ Нов модел</a>
</div>
<p class="muted small">Размерите и базовата цена се редактират тук. Кои опции важат за модела и на каква цена – в „Съвместимост“.</p>

<div class="table-wrap card">
<table class="grid">
  <thead><tr><th>Модел</th><th>Форма › размер</th><th>Форма</th><th>Размери, см</th><th>Цена</th><th>Опции</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($models as $m): ?>
    <tr class="<?= $m['active'] ? '' : 'inactive' ?>">
      <td><a href="model_edit.php?id=<?= (int)$m['id'] ?>"><b><?= e($m['name']) ?></b></a><br><code class="small"><?= e($m['code']) ?></code><?= $m['active'] ? '' : ' <span class="badge">скрит</span>' ?></td>
      <td><?= e($m['form_name'] ?? '—') ?> › <b><?= e($m['size_label'] ?? '') ?></b><?= $m['form_name'] !== null && !$m['form_active'] ? ' <span class="badge">формата е скрита</span>' : '' ?></td>
      <td class="small"><?= e(explode(' (', $shapes[$m['shape']] ?? $m['shape'])[0]) ?></td>
      <td><?= $m['shape'] === 'hex' ? 'Ø' . e(fmt_num($m['width_cm'])) . ' × ' . e(fmt_num($m['height_cm'])) : e(fmt_num($m['width_cm'])) . ' × ' . e(fmt_num($m['depth_cm'])) . ' × ' . e(fmt_num($m['height_cm'])) ?></td>
      <td class="nowrap"><?= e(price_range_text((float)$m['price_min'], (float)$m['price_max'])) ?></td>
      <td><a href="compat.php?model=<?= (int)$m['id'] ?>"><?= (int)$m['opts'] ?></a><?= $m['opts'] ? '' : ' <span class="warn">⚠</span>' ?></td>
      <td><a href="model_edit.php?id=<?= (int)$m['id'] ?>">Редактирай</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>Видове</h2>
<p class="muted small">Стъпка 1 в конфигуратора. Вид без активни модели не се показва.</p>
<div class="card">
  <?php foreach ($types as $t): ?>
    <form method="post" class="inline type-row">
      <?= csrf_field() ?><input type="hidden" name="action" value="type_save"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <label>Име<input name="name" value="<?= e($t['name']) ?>" required></label>
      <label class="grow">Подсказка<input name="hint" value="<?= e($t['hint']) ?>"></label>
      <label class="narrow">Ред<input name="sort" type="number" value="<?= (int)$t['sort'] ?>"></label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $t['active'] ? 'checked' : '' ?>> Активен</label>
      <button class="a-btn a-btn-ghost">Запази</button>
      <code class="small muted"><?= e($t['code']) ?></code>
    </form>
  <?php endforeach; ?>
  <details>
    <summary>+ Добави вид</summary>
    <form method="post" class="inline type-row">
      <?= csrf_field() ?><input type="hidden" name="action" value="type_save">
      <label>Код<input name="code" pattern="[a-z0-9_]{2,32}" placeholder="напр. bbq" required></label>
      <label>Име<input name="name" required></label>
      <label class="grow">Подсказка<input name="hint"></label>
      <label class="narrow">Ред<input name="sort" type="number" value="10"></label>
      <button class="a-btn">Добави</button>
    </form>
  </details>
</div>
<?php admin_footer(); ?>
