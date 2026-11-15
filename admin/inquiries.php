<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();

$statuses = inquiry_statuses();
$status = (string)($_GET['status'] ?? '');
$anon = ($_GET['anon'] ?? '') === '1';
$conds = [$anon ? 'i.anonymized_at IS NOT NULL' : 'i.anonymized_at IS NULL'];
$params = [];
if (!$anon && isset($statuses[$status])) {
    $conds[] = 'i.status = ?';
    $params[] = $status;
}
$where = 'WHERE ' . implode(' AND ', $conds);
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$total = (int)db_one("SELECT COUNT(*) c FROM inquiries i $where", $params)['c'];
$rows = db_all("SELECT i.id, i.public_code, i.created_at, i.status, i.name, i.phone, i.city, i.price_min, i.price_max, i.quantity,
    i.utm_source, m.name AS model_name, i.config_json,
    (SELECT COUNT(*) FROM mail_log ml WHERE ml.inquiry_id = i.id AND ml.ok = 0) AS mail_fail,
    (SELECT COUNT(*) FROM mail_log ml WHERE ml.inquiry_id = i.id AND ml.ok = 1) AS mail_ok
  FROM inquiries i LEFT JOIN models m ON m.id = i.model_id $where ORDER BY i.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) c FROM inquiries WHERE anonymized_at IS NULL GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['c'];
}

admin_header('Запитвания', 'inquiries');
?>
<div class="a-title">
  <h1>Запитвания</h1>
  <a class="a-btn a-btn-ghost" href="export.php?<?= e(http_build_query($anon ? ['anon' => 1] : ['status' => $status])) ?>">⇩ CSV за Excel</a>
</div>
<nav class="tabs">
  <a href="?" class="<?= $status === '' && !$anon ? 'on' : '' ?>">Всички <span><?= array_sum($counts) ?></span></a>
  <?php foreach ($statuses as $k => $label): ?>
    <a href="?status=<?= e($k) ?>" class="<?= !$anon && $status === $k ? 'on' : '' ?>"><?= e($label) ?> <span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
  <a href="?anon=1" class="<?= $anon ? 'on' : '' ?>">Изтрити (анонимни) <span><?= (int)db_one('SELECT COUNT(*) c FROM inquiries WHERE anonymized_at IS NOT NULL')['c'] ?></span></a>
</nav>

<?php if (!$rows): ?>
  <p class="card muted">Няма запитвания<?= $status || $anon ? ' в този филтър' : '' ?>.</p>
<?php else: ?>
<div class="list">
  <?php foreach ($rows as $r): $cfg = json_decode($r['config_json'], true) ?: []; ?>
  <a class="row card" href="inquiry.php?id=<?= (int)$r['id'] ?>">
    <div class="row-main">
      <span class="badge st-<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
      <b><?= $r['name'] !== '' ? e($r['name']) : '<span class="muted">(изтрити лични данни)</span>' ?></b><?= $r['phone'] !== '' ? ' · ' . e($r['phone']) : '' ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?>
    </div>
    <div class="row-sub">
      <?= e($r['model_name'] ?? ($cfg['model']['name'] ?? '—')) ?><?= ($cfg['text'] ?? '') !== '' ? ' · „' . e($cfg['text']) . '“' : '' ?>
      <?= $r['quantity'] > 1 ? ' · ' . (int)$r['quantity'] . ' бр.' : '' ?>
    </div>
    <div class="row-meta">
      <span><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></span>
      <span><?= e($r['public_code']) ?></span>
      <span><?= e(price_range_text((float)$r['price_min'], (float)$r['price_max'])) ?></span>
      <?php if ($r['utm_source']): ?><span>източник: <?= e($r['utm_source']) ?></span><?php endif; ?>
      <?php if ($r['mail_fail'] && !$r['mail_ok']): ?><span class="warn">⚠ имейлът не е изпратен</span><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): ?>
  <p class="pager">
    <?php for ($i = 1; $i <= ceil($total / $per); $i++): ?>
      <a href="?<?= e(http_build_query(['status' => $status, 'anon' => $anon ? 1 : null, 'p' => $i])) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </p>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer(); ?>
