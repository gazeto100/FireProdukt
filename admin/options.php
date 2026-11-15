<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'group_save') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name !== '') {
        db()->prepare('UPDATE option_groups SET name = ?, hint = ? WHERE id = ?')
            ->execute([mb_substr($name, 0, 100), trim((string)($_POST['hint'] ?? '')) ?: null, (int)($_POST['id'] ?? 0)]);
        flash('Групата е запазена.');
    }
    redirect(url('admin/options.php'));
}

$groups = db_all('SELECT * FROM option_groups ORDER BY sort, id');
$opts = db_all('SELECT o.*, (SELECT COUNT(*) FROM model_options mo WHERE mo.option_id = o.id) AS models FROM options o ORDER BY o.sort, o.id');
$byGroup = [];
foreach ($opts as $o) {
    $byGroup[(int)$o['group_id']][] = $o;
}
$cur = setting('currency', '€');

admin_header('Опции', 'options');
?>
<h1>Опции</h1>
<p class="muted small">Общите цени и тегла на опциите. За отделен модел цената може да се промени в „Съвместимост“. Материалите умножават базовата цена на модела (коефициент), останалите опции добавят сума.</p>

<?php foreach ($groups as $g): $list = $byGroup[(int)$g['id']] ?? []; ?>
<section class="card">
  <form method="post" class="inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="group_save"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
    <label>Група<input name="name" value="<?= e($g['name']) ?>" required></label>
    <label class="grow">Подсказка под заглавието<input name="hint" value="<?= e($g['hint']) ?>"></label>
    <button class="a-btn a-btn-ghost">Запази</button>
    <span class="muted small"><?= $g['multi'] ? 'много избора' : 'един избор' ?> · <code><?= e($g['code']) ?></code></span>
  </form>
  <div class="table-wrap">
  <table class="grid">
    <thead><tr><th>Опция</th><th>Цена</th><th>Тегло</th><th>Модели</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $o): ?>
      <tr class="<?= $o['active'] ? '' : 'inactive' ?>">
        <td><a href="option_edit.php?id=<?= (int)$o['id'] ?>"><b><?= e($o['name']) ?></b></a> <code class="small muted"><?= e($o['code']) ?></code><?= $o['active'] ? '' : ' <span class="badge">скрита</span>' ?>
          <?php if ($o['hint']): ?><br><span class="small muted"><?= e($o['hint']) ?></span><?php endif; ?></td>
        <td class="nowrap"><?php if ($o['price_type'] === 'factor'): ?>× <?= e(fmt_num($o['price_min'])) ?>–<?= e(fmt_num($o['price_max'])) ?><?php else: ?>+<?= e(fmt_num($o['price_min'])) ?>–<?= e(fmt_num($o['price_max'])) ?> <?= e($cur) ?><?= str_contains((string)$o['render'], 'per_side') ? ' / страна' : '' ?><?php endif; ?></td>
        <td class="nowrap"><?= (float)$o['weight_kg'] ? e(fmt_num($o['weight_kg'])) . ' кг' : '—' ?></td>
        <td><?= (int)$o['models'] ?></td>
        <td><a href="option_edit.php?id=<?= (int)$o['id'] ?>">Редактирай</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p><a href="option_edit.php?group=<?= (int)$g['id'] ?>">+ Нова опция в „<?= e($g['name']) ?>“</a></p>
</section>
<?php endforeach; ?>
<?php admin_footer(); ?>
