<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();

$statuses = inquiry_statuses();
$kinds = ['config' => 'От конфигуратора', 'project' => 'Проекти', 'idea' => 'По идея'];
$status = (string)($_GET['status'] ?? '');
$kind = isset($kinds[$_GET['kind'] ?? '']) ? (string)$_GET['kind'] : '';
$anon = ($_GET['anon'] ?? '') === '1';

$base = [$anon ? 'i.anonymized_at IS NOT NULL' : 'i.anonymized_at IS NULL'];
$baseParams = [];
if ($kind !== '') {
    $base[] = 'i.kind = ?';
    $baseParams[] = $kind;
}
$conds = $base;
$params = $baseParams;
if (!$anon && isset($statuses[$status])) {
    $conds[] = 'i.status = ?';
    $params[] = $status;
}
$where = 'WHERE ' . implode(' AND ', $conds);
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$total = (int)db_one("SELECT COUNT(*) c FROM inquiries i $where", $params)['c'];
$rows = db_all("SELECT i.id, i.public_code, i.kind, i.created_at, i.status, i.name, i.phone, i.city, i.price_min, i.price_max, i.quantity,
    i.utm_source, m.name AS model_name, i.config_json,
    (SELECT COUNT(*) FROM uploads u WHERE u.inquiry_id = i.id) AS files,
    (SELECT COUNT(*) FROM mail_log ml WHERE ml.inquiry_id = i.id AND ml.ok = 0) AS mail_fail,
    (SELECT COUNT(*) FROM mail_log ml WHERE ml.inquiry_id = i.id AND ml.ok = 1) AS mail_ok
  FROM inquiries i LEFT JOIN models m ON m.id = i.model_id $where ORDER BY i.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);

// броячи за статусите (в текущия вид) и за видовете
$counts = [];
$bw = 'WHERE ' . implode(' AND ', array_merge(['i.anonymized_at IS NULL'], $kind !== '' ? ['i.kind = ?'] : []));
foreach (db_all("SELECT status, COUNT(*) c FROM inquiries i $bw GROUP BY status", $kind !== '' ? [$kind] : []) as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$kindCounts = [];
foreach (db_all('SELECT kind, COUNT(*) c FROM inquiries WHERE anonymized_at IS NULL GROUP BY kind') as $r) {
    $kindCounts[$r['kind']] = (int)$r['c'];
}
$q = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['kind' => $kind, 'status' => $status, 'anon' => $anon ? 1 : null], $over), fn($v) => $v !== null && $v !== ''));
$materials = project_materials();

admin_header('Запитвания', 'inquiries');
?>
<div class="a-title">
  <h1>Запитвания</h1>
  <a class="a-btn a-btn-ghost" href="export.php<?= e($q(['p' => null])) ?>">⇩ CSV за Excel</a>
</div>
<nav class="tabs kind-tabs">
  <a href="<?= e($q(['kind' => null, 'p' => null])) ?>" class="<?= $kind === '' ? 'on' : '' ?>">Всички видове <span><?= array_sum($kindCounts) ?></span></a>
  <?php foreach ($kinds as $k => $label): ?>
    <a href="<?= e($q(['kind' => $k, 'p' => null])) ?>" class="<?= $kind === $k ? 'on' : '' ?>"><?= e($label) ?> <span><?= $kindCounts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>
<nav class="tabs">
  <a href="<?= e($q(['status' => null, 'anon' => null, 'p' => null])) ?>" class="<?= $status === '' && !$anon ? 'on' : '' ?>">Всички <span><?= array_sum($counts) ?></span></a>
  <?php foreach ($statuses as $k => $label): ?>
    <a href="<?= e($q(['status' => $k, 'anon' => null, 'p' => null])) ?>" class="<?= !$anon && $status === $k ? 'on' : '' ?>"><?= e($label) ?> <span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
  <a href="<?= e($q(['anon' => 1, 'status' => null, 'p' => null])) ?>" class="<?= $anon ? 'on' : '' ?>">Изтрити (анонимни) <span><?= (int)db_one('SELECT COUNT(*) c FROM inquiries i WHERE i.anonymized_at IS NOT NULL' . ($kind !== '' ? ' AND i.kind = ?' : ''), $kind !== '' ? [$kind] : [])['c'] ?></span></a>
</nav>

<?php if (!$rows): ?>
  <p class="card muted">Няма запитвания в този филтър.</p>
<?php else: ?>
<div class="list">
  <?php foreach ($rows as $r): $cfg = json_decode($r['config_json'], true) ?: []; $isProj = $r['kind'] === 'project'; $isIdea = $r['kind'] === 'idea'; ?>
  <a class="row card<?= $isProj ? ' row-project' : '' ?>" href="inquiry.php?id=<?= (int)$r['id'] ?>">
    <div class="row-main">
      <?php if ($isProj): ?><span class="badge badge-project">Проект</span><?php endif; ?>
      <?php if ($isIdea): ?><span class="badge badge-idea">По идея</span><?php endif; ?>
      <?php if (!empty($cfg['custom'])): ?><span class="badge badge-custom">Размер по избор: <?= e(shape_is_round((string)($cfg['model']['shape'] ?? '')) ? 'Ø' . fmt_num($cfg['model']['w']) . ' × ' . fmt_num($cfg['model']['h']) : fmt_num($cfg['model']['w']) . ' × ' . fmt_num($cfg['model']['d']) . ' × ' . fmt_num($cfg['model']['h'])) ?> см</span><?php endif; ?>
      <?php if (!empty($cfg['pallet'])): ?><span class="badge badge-flag">палет</span><?php endif; ?>
      <?php if (!empty($cfg['two_parts'])): ?><span class="badge badge-flag">2 части</span><?php endif; ?>
      <?php if (!$isProj && !$isIdea && $r['files']): ?><span class="badge badge-logo">лого</span><?php endif; ?>
      <span class="badge st-<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
      <b><?= $r['name'] !== '' ? e($r['name']) : '<span class="muted">(изтрити лични данни)</span>' ?></b><?= $r['phone'] !== '' ? ' · ' . e($r['phone']) : '' ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?>
    </div>
    <div class="row-sub">
      <?php if ($isIdea): ?>
        <?= e(idea_whats()[$cfg['what'] ?? ''] ?? 'Идея') ?> · <?= e(mb_strimwidth((string)($cfg['description'] ?? ''), 0, 70, '…')) ?> · бюджет: <?= e($cfg['budget'] ?? '—') ?> · <?= (int)$r['files'] ?> файла
      <?php elseif ($isProj && !empty($cfg['model'])): ?>
        <?= e(($cfg['object'] ?? '') !== '' ? $cfg['object'] : 'Обект') ?> · <?= e(!empty($cfg['custom']) ? ($cfg['model']['form_name'] ?? $r['model_name'] ?? '') : ($r['model_name'] ?? ($cfg['model']['name'] ?? '—'))) ?> · <?= e($cfg['options']['material']['name'] ?? '—') ?><?= ($cfg['text'] ?? '') !== '' ? ' · „' . e($cfg['text']) . '“' : '' ?> · <?= (int)$r['quantity'] ?> бр.<?= $r['files'] ? ' · 📎 лого' : '' ?>
      <?php elseif ($isProj): ?>
        <?= e(($cfg['object'] ?? '') !== '' ? $cfg['object'] : 'Обект') ?> · <?= (int)$r['quantity'] ?> бр. · <?= e(project_materials_short()[$cfg['material'] ?? ''] ?? '—') ?><?= $r['files'] ? ' · 📎 лого' : '' ?>
      <?php else: ?>
        <?= e(!empty($cfg['custom']) ? ($cfg['model']['form_name'] ?? $r['model_name'] ?? '') : ($r['model_name'] ?? ($cfg['model']['name'] ?? '—'))) ?><?= ($cfg['text'] ?? '') !== '' ? ' · „' . e($cfg['text']) . '“' : '' ?><?= $r['quantity'] > 1 ? ' · ' . (int)$r['quantity'] . ' бр.' : '' ?>
      <?php endif; ?>
    </div>
    <div class="row-meta">
      <span><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></span>
      <span><?= e($r['public_code']) ?></span>
      <?php if (!$isProj && !$isIdea): ?><span><?= e(price_range_text((float)$r['price_min'], (float)$r['price_max'])) ?></span><?php endif; ?>
      <?php if ($r['utm_source']): ?><span>източник: <?= e($r['utm_source']) ?></span><?php endif; ?>
      <?php if ($r['mail_fail'] && !$r['mail_ok']): ?><span class="warn">⚠ имейлът не е изпратен</span><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): ?>
  <p class="pager">
    <?php for ($i = 1; $i <= ceil($total / $per); $i++): ?>
      <a href="<?= e($q(['p' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </p>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer(); ?>
