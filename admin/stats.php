<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();

$days = in_array((int)($_GET['days'] ?? 30), [7, 30, 90, 365], true) ? (int)$_GET['days'] : 30;
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

// По дни
$series = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $series[date('Y-m-d', strtotime("-$i days"))] = ['v' => 0, 'sel' => 0, 'txt' => 0, 'lead' => 0, 'rej' => 0];
}
foreach (db_all("SELECT day, type, COUNT(DISTINCT session_id) s, COUNT(*) c FROM events WHERE day >= ? GROUP BY day, type", [$from]) as $r) {
    if (!isset($series[$r['day']])) continue;
    if ($r['type'] === 'configurator_view') $series[$r['day']]['v'] = (int)$r['s'];
    if ($r['type'] === 'model_select') $series[$r['day']]['sel'] = (int)$r['c'];
    if ($r['type'] === 'text_entered') $series[$r['day']]['txt'] = (int)$r['s'];
}
foreach (db_all('SELECT DATE(created_at) d, COUNT(*) c FROM inquiries WHERE created_at >= ? GROUP BY d', [$from]) as $r) {
    if (isset($series[$r['d']])) $series[$r['d']]['lead'] = (int)$r['c'];
}
foreach (db_all('SELECT day, SUM(cnt) c FROM rejected_log WHERE day >= ? GROUP BY day', [$from]) as $r) {
    if (isset($series[$r['day']])) $series[$r['day']]['rej'] = (int)$r['c'];
}
$tot = ['v' => 0, 'txt' => 0, 'lead' => 0, 'rej' => 0];
foreach ($series as $s) {
    foreach ($tot as $k => $_) $tot[$k] += $s[$k];
}
$maxV = max(1, max(array_map(fn($s) => max($s['v'], $s['lead']), $series)));

// Модели: избори в конфигуратора и запитвания
$models = [];
foreach (db_all('SELECT id, name FROM models ORDER BY sort') as $m) {
    $models[(int)$m['id']] = ['name' => $m['name'], 'sel' => 0, 'lead' => 0];
}
foreach (db_all("SELECT model_id, COUNT(*) c FROM events WHERE type='model_select' AND day >= ? AND model_id IS NOT NULL GROUP BY model_id", [$from]) as $r) {
    if (isset($models[(int)$r['model_id']])) $models[(int)$r['model_id']]['sel'] = (int)$r['c'];
}
$inqs = db_all('SELECT model_id, config_json, utm_source FROM inquiries WHERE created_at >= ?', [$from]);
$groupNames = array_column(db_all('SELECT code, name FROM option_groups ORDER BY sort'), 'name', 'code');
$optCounts = [];
$sources = [];
$withText = 0;
$textLen = 0;
foreach ($inqs as $q) {
    if ($q['model_id'] && isset($models[(int)$q['model_id']])) $models[(int)$q['model_id']]['lead']++;
    $src = $q['utm_source'] ?: '(директно / без UTM)';
    $sources[$src] = ($sources[$src] ?? 0) + 1;
    $cfg = json_decode($q['config_json'], true) ?: [];
    foreach ($cfg['options'] ?? [] as $g => $val) {
        $list = isset($val['name']) ? [$val] : (array)$val;
        foreach ($list as $o) {
            if (!isset($o['name'])) continue;
            $optCounts[$g][$o['name']] = ($optCounts[$g][$o['name']] ?? 0) + 1;
        }
    }
    if (($cfg['text_length'] ?? 0) > 0) {
        $withText++;
        $textLen += (int)$cfg['text_length'];
    }
}
uasort($models, fn($a, $b) => [$b['lead'], $b['sel']] <=> [$a['lead'], $a['sel']]);
arsort($sources);
$n = count($inqs);

// Отхвърлени
$reasons = rejected_reasons();
$rej = db_all('SELECT reason, SUM(cnt) c FROM rejected_log WHERE day >= ? GROUP BY reason ORDER BY c DESC', [$from]);
$rejDays = db_all('SELECT day, reason, cnt FROM rejected_log WHERE day >= ? ORDER BY day DESC, reason LIMIT 200', [$from]);

$projects = (int)db_one("SELECT COUNT(*) c FROM inquiries WHERE kind = 'project' AND created_at >= ?", [$from])['c'];
// „По идея“: по дни и по бюджет
$ideaDays = [];
$ideaBudgets = [];
foreach (db_all("SELECT DATE(created_at) d, config_json FROM inquiries WHERE kind = 'idea' AND created_at >= ? ORDER BY created_at DESC", [$from]) as $r) {
    $ideaDays[$r['d']] = ($ideaDays[$r['d']] ?? 0) + 1;
    $b = (json_decode($r['config_json'], true) ?: [])['budget'] ?? 'Не знам';
    $ideaBudgets[$b] = ($ideaBudgets[$b] ?? 0) + 1;
}
$ideas = array_sum($ideaDays);
arsort($ideaBudgets);
$pct = fn($a, $b) => $b ? number_format($a / $b * 100, 1, ',', '') . '%' : '—';

admin_header('Статистика', 'stats');
?>
<div class="a-title">
  <h1>Статистика</h1>
  <nav class="tabs">
    <?php foreach ([7 => '7 дни', 30 => '30 дни', 90 => '90 дни', 365 => '1 година'] as $d => $label): ?>
      <a href="?days=<?= $d ?>" class="<?= $d === $days ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="kpis">
  <div class="kpi"><b><?= $tot['v'] ?></b><span>посещения на конфигуратора (уникални сесии)</span></div>
  <div class="kpi"><b><?= $tot['txt'] ?></b><span>въвели надпис (<?= $pct($tot['txt'], $tot['v']) ?>)</span></div>
  <div class="kpi"><b><?= $tot['lead'] ?></b><span>запитвания (<?= $pct($tot['lead'], $tot['v']) ?> от посещенията)<?= $projects ? ', от тях ' . $projects . ' по проект' : '' ?><?= $ideas ? ($projects ? ' и ' : ', от тях ') . $ideas . ' по идея' : '' ?></span></div>
  <div class="kpi"><b><?= $tot['rej'] ?></b><span>отхвърлени запитвания</span></div>
</div>

<section class="card">
  <h2>По дни</h2>
  <div class="legend-dots"><span><i style="background:#d9cfc4"></i>Посещения</span><span><i style="background:var(--ember)"></i>Запитвания</span></div>
  <div class="bars" role="img" aria-label="Посещения и запитвания по дни">
    <?php foreach ($series as $d => $s): ?>
      <div class="bar" title="<?= e(date('d.m', strtotime($d))) ?>: <?= $s['v'] ?> посещения, <?= $s['lead'] ?> запитвания">
        <i class="v" style="height:<?= $s['v'] ? max(2, round($s['v'] / $maxV * 100)) : 0 ?>%"></i>
        <i class="l" style="height:<?= $s['lead'] ? max(2, round($s['lead'] / $maxV * 100)) : 0 ?>%"></i>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="bars-axis"><span><?= e(date('d.m', strtotime(array_key_first($series)))) ?></span><span><?= e(date('d.m', strtotime(array_key_last($series)))) ?></span></div>
  <details>
    <summary>Таблица по дни</summary>
    <div class="table-wrap">
    <table class="grid">
      <thead><tr><th>Ден</th><th>Посещения</th><th>Избор на модел</th><th>Въвели надпис</th><th>Запитвания</th><th>Конверсия</th><th>Отхвърлени</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($series, true) as $d => $s): if (!$s['v'] && !$s['lead'] && !$s['rej'] && !$s['sel']) continue; ?>
        <tr><td><?= e(date('d.m.Y', strtotime($d))) ?></td><td><?= $s['v'] ?></td><td><?= $s['sel'] ?></td><td><?= $s['txt'] ?></td><td><b><?= $s['lead'] ?></b></td><td><?= $pct($s['lead'], $s['v']) ?></td><td><?= $s['rej'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </details>
</section>

<section class="card">
  <h2>По идея</h2>
  <?php if (!$ideas): ?>
    <p class="muted">Няма запитвания „По идея“ за периода.</p>
  <?php else: ?>
  <div class="grid2">
    <div>
      <h3>По дни</h3>
      <table class="grid"><thead><tr><th>Ден</th><th>Запитвания</th></tr></thead><tbody>
        <?php foreach ($ideaDays as $d => $c): ?><tr><td><?= e(date('d.m.Y', strtotime($d))) ?></td><td><b><?= $c ?></b></td></tr><?php endforeach; ?>
      </tbody></table>
    </div>
    <div>
      <h3>По бюджет</h3>
      <table class="grid"><thead><tr><th>Бюджет</th><th>Запитвания</th><th>Дял</th></tr></thead><tbody>
        <?php foreach ($ideaBudgets as $b => $c): ?><tr><td><?= e($b) ?></td><td><b><?= $c ?></b></td><td><?= $pct($c, $ideas) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
  <?php endif; ?>
</section>

<div class="grid2">
  <section class="card">
    <h2>Модели</h2>
    <table class="grid">
      <thead><tr><th>Модел</th><th>Избран в конфигуратора</th><th>Запитвания</th></tr></thead>
      <tbody>
      <?php $maxL = max(1, max(array_column($models, 'lead') ?: [0])); foreach ($models as $m): ?>
        <tr><td><?= e($m['name']) ?><div class="hbar"><i style="width:<?= round($m['lead'] / $maxL * 100) ?>%"></i></div></td><td><?= $m['sel'] ?></td><td><b><?= $m['lead'] ?></b></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted small">„Избран“ се брои при смяна на модела в конфигуратора; първоначално зареденият модел не се брои.</p>
  </section>

  <section class="card">
    <h2>Източници на запитванията</h2>
    <?php if (!$sources): ?><p class="muted">Няма запитвания за периода.</p><?php endif; ?>
    <table class="grid"><tbody>
      <?php foreach ($sources as $src => $c): ?><tr><td><?= e($src) ?></td><td><b><?= $c ?></b></td><td class="muted"><?= $pct($c, $n) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</div>

<section class="card">
  <h2>Най-избирани опции <span class="muted small">(в <?= $n ?> запитвания)</span></h2>
  <p class="small">С надпис: <b><?= $withText ?></b> (<?= $pct($withText, $n) ?>)<?= $withText ? ', средно ' . number_format($textLen / $withText, 1, ',', '') . ' знака' : '' ?>.</p>
  <div class="grid2">
  <?php foreach ($groupNames as $gcode => $gname): if (empty($optCounts[$gcode])) continue; arsort($optCounts[$gcode]); ?>
    <div>
      <h3 class="small"><?= e($gname) ?></h3>
      <table class="grid"><tbody>
        <?php foreach ($optCounts[$gcode] as $name => $c): ?>
          <tr><td><?= e($name) ?><div class="hbar"><i style="width:<?= round($c / max(1, $n) * 100) ?>%"></i></div></td><td><b><?= $c ?></b></td><td class="muted"><?= $pct($c, $n) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
  <?php endforeach; ?>
  </div>
</section>

<section class="card" id="rejected">
  <h2>Отхвърлени запитвания</h2>
  <p class="muted small">Записват се само дата, причина и брой – без лични данни. Ботовете и прекалено бързите получават „успех“, без да се записва нищо.</p>
  <?php if (!$rej): ?><p class="muted">Няма отхвърлени запитвания за периода.</p><?php else: ?>
  <table class="grid"><tbody>
    <?php foreach ($rej as $r): ?><tr><td><?= e($reasons[$r['reason']] ?? $r['reason']) ?></td><td><b><?= (int)$r['c'] ?></b></td></tr><?php endforeach; ?>
  </tbody></table>
  <details>
    <summary>По дни</summary>
    <table class="grid">
      <thead><tr><th>Дата</th><th>Причина</th><th>Брой</th></tr></thead>
      <tbody><?php foreach ($rejDays as $r): ?><tr><td><?= e(date('d.m.Y', strtotime($r['day']))) ?></td><td><?= e($reasons[$r['reason']] ?? $r['reason']) ?></td><td><?= (int)$r['cnt'] ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </details>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>
