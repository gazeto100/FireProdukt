<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$models = db_all('SELECT id, code, name, active FROM models ORDER BY sort, id');
$groups = db_all('SELECT * FROM option_groups ORDER BY sort, id');
$options = db_all('SELECT * FROM options ORDER BY sort, id');
$optsByGroup = [];
foreach ($options as $o) {
    $optsByGroup[(int)$o['group_id']][] = $o;
}
$mid = (int)($_GET['model'] ?? ($models[0]['id'] ?? 0));
$model = null;
foreach ($models as $m) {
    if ((int)$m['id'] === $mid) $model = $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $model) {
    $on = array_map('intval', (array)($_POST['on'] ?? []));
    $def = (array)($_POST['def'] ?? []);
    $notes = [];
    $rows = [];
    $errors = [];
    foreach ($groups as $g) {
        $checked = [];
        foreach ($optsByGroup[(int)$g['id']] ?? [] as $o) {
            $oid = (int)$o['id'];
            if (!in_array($oid, $on, true)) continue;
            $pmin = post_num('pmin_' . $oid);
            $pmax = post_num('pmax_' . $oid);
            if (($pmin === null) !== ($pmax === null)) {
                $errors[] = '„' . $o['name'] . '“: попълнете и двете цени за модела или оставете и двете празни.';
            } elseif ($pmin !== null && ($pmin < 0 || $pmax < $pmin)) {
                $errors[] = '„' . $o['name'] . '“: цена „от“ ≥ 0 и „до“ ≥ „от“.';
            }
            $checked[] = $oid;
            $rows[$oid] = [$pmin, $pmax, 0];
        }
        if (!$g['multi'] && $checked) {
            $d = (int)($def[$g['id']] ?? 0);
            if (!in_array($d, $checked, true)) {
                $d = $checked[0];
                $notes[] = '„' . $g['name'] . '“: за подразбиране е избрана първата налична опция.';
            }
            $rows[$d][2] = 1;
        }
    }
    if ($errors) {
        flash(implode(' ', $errors), 'err');
    } else {
        db()->beginTransaction();
        db()->prepare('DELETE FROM model_options WHERE model_id = ?')->execute([$mid]);
        $ins = db()->prepare('INSERT INTO model_options (model_id, option_id, price_min, price_max, is_default) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $oid => $r) {
            $ins->execute([$mid, $oid, $r[0], $r[1], $r[2]]);
        }
        db()->commit();
        flash('Съвместимостите на „' . $model['name'] . '“ са запазени. ' . implode(' ', $notes));
    }
    redirect(url('admin/compat.php?model=' . $mid));
}

// всички връзки за обзора
$mo = [];
foreach (db_all('SELECT * FROM model_options') as $r) {
    $mo[(int)$r['option_id']][(int)$r['model_id']] = $r;
}
$cur = setting('currency', '€');
$priceTxt = function (array $o, ?array $r = null) use ($cur): string {
    $min = $r && $r['price_min'] !== null ? $r['price_min'] : $o['price_min'];
    $max = $r && $r['price_max'] !== null ? $r['price_max'] : $o['price_max'];
    if ($o['price_type'] === 'factor') return '×' . fmt_num($min) . '–' . fmt_num($max);
    return ((float)$max == 0 ? '0' : fmt_num($min) . '–' . fmt_num($max)) . ' ' . $cur;
};

admin_header('Съвместимост', 'compat');
?>
<h1>Съвместимост и цени по модел</h1>
<p class="muted small">Опция се показва в конфигуратора само за моделите, за които е отметната. Несъвместимите опции не се показват. Празна цена = общата цена на опцията.</p>

<details class="card" <?= isset($_GET['overview']) ? 'open' : '' ?>>
  <summary><b>Обзор: всички модели</b> <span class="muted small">(✓ = налична, ● = по подразбиране, * = своя цена)</span></summary>
  <div class="table-wrap">
  <table class="grid matrix">
    <thead><tr><th></th><?php foreach ($models as $m): ?><th><a href="?model=<?= (int)$m['id'] ?>"><?= e($m['name']) ?></a></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr class="grp"><td colspan="<?= count($models) + 1 ?>"><?= e($g['name']) ?></td></tr>
      <?php foreach ($optsByGroup[(int)$g['id']] ?? [] as $o): ?>
        <tr>
          <th><?= e($o['name']) ?></th>
          <?php foreach ($models as $m): $r = $mo[(int)$o['id']][(int)$m['id']] ?? null; ?>
            <td class="c"><?= $r ? ($r['is_default'] ? '●' : '✓') . ($r['price_min'] !== null ? '<sup>*</sup>' : '') : '<span class="muted">·</span>' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</details>

<nav class="tabs">
  <?php foreach ($models as $m): ?>
    <a href="?model=<?= (int)$m['id'] ?>" class="<?= (int)$m['id'] === $mid ? 'on' : '' ?>"><?= e($m['name']) ?><?= $m['active'] ? '' : ' (скрит)' ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($model): ?>
<form method="post" class="compat">
  <?= csrf_field() ?>
  <?php foreach ($groups as $g): $list = $optsByGroup[(int)$g['id']] ?? []; if (!$list) continue; ?>
  <section class="card">
    <h2><?= e($g['name']) ?> <span class="muted small"><?= $g['multi'] ? '· клиентът може да избере няколко' : '· един избор; ● = по подразбиране' ?></span></h2>
    <div class="table-wrap">
    <table class="grid">
      <thead><tr><th>Налична</th><?php if (!$g['multi']): ?><th>●</th><?php endif; ?><th>Опция</th><th>Обща цена</th><th>Цена за модела от – до</th></tr></thead>
      <tbody>
      <?php foreach ($list as $o): $r = $mo[(int)$o['id']][$mid] ?? null; $oid = (int)$o['id']; ?>
        <tr class="<?= $o['active'] ? '' : 'inactive' ?>">
          <td class="c"><input type="checkbox" name="on[]" value="<?= $oid ?>" <?= $r ? 'checked' : '' ?> aria-label="Налична: <?= e($o['name']) ?>"></td>
          <?php if (!$g['multi']): ?><td class="c"><input type="radio" name="def[<?= (int)$g['id'] ?>]" value="<?= $oid ?>" <?= $r && $r['is_default'] ? 'checked' : '' ?> aria-label="По подразбиране: <?= e($o['name']) ?>"></td><?php endif; ?>
          <td><?= e($o['name']) ?><?= $o['active'] ? '' : ' <span class="badge">скрита</span>' ?></td>
          <td class="nowrap muted small"><?= e($priceTxt($o)) ?></td>
          <td class="nowrap">
            <input class="num" name="pmin_<?= $oid ?>" inputmode="decimal" value="<?= $r && $r['price_min'] !== null ? e(fmt_num($r['price_min'])) : '' ?>" placeholder="<?= e(fmt_num($o['price_min'])) ?>" aria-label="Цена от">
            –
            <input class="num" name="pmax_<?= $oid ?>" inputmode="decimal" value="<?= $r && $r['price_max'] !== null ? e(fmt_num($r['price_max'])) : '' ?>" placeholder="<?= e(fmt_num($o['price_max'])) ?>" aria-label="Цена до">
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <?php endforeach; ?>
  <div class="sticky-actions">
    <button class="a-btn">Запази за „<?= e($model['name']) ?>“</button>
  </div>
  <div class="actions">
    <a class="a-btn a-btn-ghost" href="model_edit.php?id=<?= $mid ?>">Размери и базова цена</a>
    <a class="a-btn a-btn-ghost" href="<?= e(url('configurator.php?model=' . rawurlencode($model['code']))) ?>" target="_blank">Виж в конфигуратора ↗</a>
  </div>
</form>
<?php else: ?>
  <p class="card">Няма модели. <a href="model_edit.php">Създайте модел</a>.</p>
<?php endif; ?>
<?php admin_footer(); ?>
