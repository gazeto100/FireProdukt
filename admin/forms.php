<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$types = db_all('SELECT id, code, name FROM product_types ORDER BY sort, id');
$shapes = model_shapes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        flash('Въведете име на формата.', 'err');
    } elseif ($id) {
        db()->prepare('UPDATE forms SET name = ?, description = ?, sort = ?, active = ? WHERE id = ?')
            ->execute([mb_substr($name, 0, 100), trim((string)($_POST['description'] ?? '')) ?: null, (int)($_POST['sort'] ?? 0), isset($_POST['active']) ? 1 : 0, $id]);
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
            db()->prepare('INSERT INTO forms (code, type_id, name, shape, description, sort, active) VALUES (?, ?, ?, ?, ?, ?, 1)')
                ->execute([$code, $type, mb_substr($name, 0, 100), $shape, trim((string)($_POST['description'] ?? '')) ?: null, (int)($_POST['sort'] ?? 50)]);
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
