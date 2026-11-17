<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$id = (int)($_GET['id'] ?? 0);
$inq = db_one('SELECT * FROM inquiries WHERE id = ?', [$id]);
if (!$inq) {
    http_response_code(404);
    admin_header('Няма такова запитване', 'inquiries');
    echo '<p class="card">Запитването не е намерено. <a href="inquiries.php">Към списъка</a></p>';
    admin_footer();
    exit;
}
$statuses = inquiry_statuses();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'status' && isset($statuses[$_POST['status'] ?? ''])) {
        db()->prepare('UPDATE inquiries SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        flash('Статусът е променен.');
    } elseif ($action === 'note') {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body !== '') {
            db()->prepare('INSERT INTO inquiry_notes (inquiry_id, admin_id, body) VALUES (?, ?, ?)')->execute([$id, admin_id(), mb_substr($body, 0, 5000)]);
            flash('Бележката е добавена.');
        }
    } elseif ($action === 'anonymize' && !$inq['anonymized_at']) {
        // Изтрива личните данни и снимката; остава анонимен ред за статистиката (модел, опции, цена, UTM, статус)
        $cfg = json_decode($inq['config_json'], true) ?: [];
        if (($cfg['text'] ?? '') !== '') {
            $cfg['text'] = '';
            $cfg['text_removed'] = true;
        }
        if (($cfg['kind'] ?? '') === 'project') {
            // обектът също се маха; остават брой, материал, срок и интерес за статистиката
            if (($cfg['object'] ?? '') !== '') { $cfg['object'] = ''; $cfg['object_removed'] = true; }
        }
        if (($cfg['logo'] ?? '') !== '') { $cfg['logo'] = '(изтрито)'; }
        db()->beginTransaction();
        db()->prepare("UPDATE inquiries SET name = '', phone = '', email = NULL, city = NULL, note = NULL, svg = NULL, ip_hash = NULL,
            user_agent = NULL, referrer = NULL, landing_url = NULL, config_json = ?, anonymized_at = NOW() WHERE id = ?")
            ->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), $id]);
        db()->prepare('DELETE FROM inquiry_notes WHERE inquiry_id = ?')->execute([$id]);
        upload_delete_for_inquiry($id);
        db()->prepare("UPDATE mail_log SET recipient = '(изтрит)', subject = '(изтрито)', error = NULL WHERE inquiry_id = ?")->execute([$id]);
        db()->commit();
        flash('Личните данни по запитване ' . $inq['public_code'] . ' са изтрити. Остава анонимен запис за статистиката.');
        redirect(url('admin/inquiries.php'));
    } elseif ($action === 'resend') {
        // повторно изпращане на известието до администратора
        $cfg = json_decode($inq['config_json'], true);
        $notify = setting('notify_email', '');
        if ($notify !== '') {
            $lines = config_lines($cfg, load_catalog(false));
            $t = "Запитване {$inq['public_code']} (повторно изпращане)\n\nИме: {$inq['name']}\nТелефон: {$inq['phone']}\nИмейл: " . ($inq['email'] ?: '—') . "\n\n";
            foreach ($lines as $k => $v) {
                $t .= "$k: $v\n";
            }
            $t .= "\nДетайли: " . abs_url('admin/inquiry.php?id=' . $id) . "\n";
            $ok = send_mail($notify, "Запитване {$inq['public_code']} – " . (($cfg['kind'] ?? '') === 'project' ? 'Проект: ' . ($cfg['object'] ?? '') : ($cfg['model']['name'] ?? '')) . " ({$inq['name']})", $t, null, [], $id);
            flash($ok ? 'Известието е изпратено.' : 'Изпращането не успя – вижте дневника по-долу.', $ok ? 'ok' : 'err');
        }
    }
    redirect(url('admin/inquiry.php?id=' . $id));
}

$cfg = json_decode($inq['config_json'], true) ?: [];
$lines = config_lines($cfg, load_catalog(false));
$notes = db_all('SELECT n.*, a.username FROM inquiry_notes n LEFT JOIN admins a ON a.id = n.admin_id WHERE n.inquiry_id = ? ORDER BY n.id DESC', [$id]);
$mails = db_all('SELECT * FROM mail_log WHERE inquiry_id = ? ORDER BY id', [$id]);
$tel = preg_replace('/[^\d+]/', '', $inq['phone']);
$isProj = $inq['kind'] === 'project';
// проект от конфигуратора: има модел и пълна конфигурация (преглед, размер, опции, надпис)
$projCfg = $isProj && !empty($cfg['model']);
$files = db_all('SELECT * FROM uploads WHERE inquiry_id = ? ORDER BY id', [$id]);

admin_header('Запитване ' . $inq['public_code'], 'inquiries');
?>
<p><a href="inquiries.php">← Всички запитвания</a></p>
<div class="a-title">
  <h1><?= e($inq['public_code']) ?> <?= $isProj ? '<span class="badge badge-project">Проект</span>' : '' ?> <?= !empty($cfg['custom']) ? '<span class="badge badge-custom">Размер по избор</span>' : '' ?> <span class="badge st-<?= e($inq['status']) ?>"><?= e($statuses[$inq['status']] ?? $inq['status']) ?></span></h1>
  <span class="muted"><?= e(date('d.m.Y H:i', strtotime($inq['created_at']))) ?></span>
</div>

<div class="grid2">
  <section class="card">
    <?php if ($isProj): ?>
      <h2>Запитване по проект<?= $projCfg ? ' <span class="muted small">(от конфигуратора)</span>' : '' ?></h2>
      <?php if ($projCfg): ?>
        <?php if ($inq['svg']): ?><div class="preview"><img src="svg.php?id=<?= (int)$id ?>" alt="Преглед"></div><?php else: ?><p class="muted">Няма запазен преглед.</p><?php endif; ?>
      <?php endif; ?>
      <?php foreach ($files as $fl): ?>
        <div class="logo-box">
          <?php if (in_array($fl['ext'], ['png', 'jpg'], true)): ?>
            <div class="logo-prev"><img src="upload.php?id=<?= (int)$fl['id'] ?>&amp;inline=1" alt="Лого"></div>
          <?php else: ?>
            <div class="logo-prev logo-file"><?= e(strtoupper($fl['ext'])) ?></div>
          <?php endif; ?>
          <div>
            <b><?= e($fl['original_name']) ?></b><br><span class="muted small"><?= e(strtoupper($fl['ext'])) ?> · <?= e(fmt_bytes((int)$fl['size'])) ?></span><br>
            <a class="a-btn a-btn-ghost" href="upload.php?id=<?= (int)$fl['id'] ?>">⇩ Свали логото</a>
            <?php if ($fl['ext'] === 'svg'): ?><p class="muted small">SVG файловете се свалят и не се отварят в браузъра (сигурност). Отворете го в Illustrator/Inkscape.</p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$files): ?><p class="muted small">Без качено лого.</p><?php endif; ?>
    <?php else: ?>
      <?php if ($inq['svg']): ?>
        <div class="preview"><img src="svg.php?id=<?= (int)$id ?>" alt="Преглед"></div>
      <?php else: ?>
        <p class="muted">Няма запазен преглед.</p>
      <?php endif; ?>
      <?php // лого при обикновено запитване (стъпка „Надпис“ – „Само лого“ / „Надпис и лого“)
      foreach ($files as $fl): ?>
        <div class="logo-box">
          <?php if (in_array($fl['ext'], ['png', 'jpg'], true)): ?>
            <div class="logo-prev"><img src="upload.php?id=<?= (int)$fl['id'] ?>&amp;inline=1" alt="Лого"></div>
          <?php else: ?>
            <div class="logo-prev logo-file"><?= e(strtoupper($fl['ext'])) ?></div>
          <?php endif; ?>
          <div>
            <b><?= e($fl['original_name']) ?></b><br><span class="muted small"><?= e(strtoupper($fl['ext'])) ?> · <?= e(fmt_bytes((int)$fl['size'])) ?><?= !empty($cfg['logo_rights']) ? ' · клиентът потвърди правата' : '' ?></span><br>
            <a class="a-btn a-btn-ghost" href="upload.php?id=<?= (int)$fl['id'] ?>">⇩ Свали логото</a>
            <?php if ($fl['ext'] === 'svg'): ?><p class="muted small">SVG файловете се свалят и не се отварят в браузъра (сигурност). Отворете го в Illustrator/Inkscape.</p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <table class="kv">
      <?php foreach ($lines as $k => $v): ?>
        <tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$isProj): ?><tr><th>Ориентировъчна цена</th><td><b><?= e(price_range_text((float)$inq['price_min'], (float)$inq['price_max'])) ?></b></td></tr><?php endif; ?>
      <?php if ($projCfg && $inq['price_min'] !== null): ?><tr><th>Ориентировъчна цена</th><td><b><?= e(price_range_text((float)$inq['price_min'], (float)$inq['price_max'])) ?></b> <span class="muted small">(не е показана на клиента – цена по оферта)</span></td></tr><?php endif; ?>
    </table>
    <details><summary>Конфигурация (JSON)</summary><pre><?= e(json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details>
  </section>

  <div>
    <section class="card">
      <h2><?= $isProj ? 'Лице за контакт' : 'Клиент' ?></h2>
      <table class="kv">
        <tr><th>Име</th><td><?= e($inq['name']) ?></td></tr>
        <tr><th>Телефон</th><td><a href="tel:<?= e($tel) ?>"><?= e($inq['phone']) ?></a></td></tr>
        <tr><th>Имейл</th><td><?= $inq['email'] ? '<a href="mailto:' . e($inq['email']) . '">' . e($inq['email']) . '</a>' : '—' ?></td></tr>
        <tr><th>Населено място</th><td><?= e($inq['city'] ?: '—') ?></td></tr>
        <tr><th>Бележка</th><td><?= nl2br(e($inq['note'] ?: '—')) ?></td></tr>
        <tr><th>Съгласие</th><td><?= e(date('d.m.Y H:i', strtotime($inq['consent_at']))) ?></td></tr>
      </table>
      <?php if ($inq['anonymized_at']): ?>
        <p class="flash flash-err">Личните данни са изтрити на <?= e(date('d.m.Y H:i', strtotime($inq['anonymized_at']))) ?>. Записът остава анонимно за статистиката.</p>
      <?php endif; ?>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <label>Статус
          <select name="status">
            <?php foreach ($statuses as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $inq['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="a-btn">Запази</button>
      </form>
    </section>

    <section class="card">
      <h2>Бележки</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="body" rows="3" placeholder="Напр. обадих се, иска доставка до Варна…" required></textarea>
        <button class="a-btn">Добави бележка</button>
      </form>
      <?php foreach ($notes as $n): ?>
        <div class="note"><div class="muted small"><?= e(date('d.m.Y H:i', strtotime($n['created_at']))) ?> · <?= e($n['username'] ?? '') ?></div><?= nl2br(e($n['body'])) ?></div>
      <?php endforeach; ?>
    </section>

    <section class="card">
      <h2>Източник</h2>
      <table class="kv">
        <tr><th>utm_source</th><td><?= e($inq['utm_source'] ?: '—') ?></td></tr>
        <tr><th>utm_medium</th><td><?= e($inq['utm_medium'] ?: '—') ?></td></tr>
        <tr><th>utm_campaign</th><td><?= e($inq['utm_campaign'] ?: '—') ?></td></tr>
        <tr><th>utm_term</th><td><?= e($inq['utm_term'] ?: '—') ?></td></tr>
        <tr><th>utm_content</th><td><?= e($inq['utm_content'] ?: '—') ?></td></tr>
        <tr><th>Referrer</th><td class="break"><?= e($inq['referrer'] ?: '—') ?></td></tr>
        <tr><th>Първа страница</th><td class="break"><?= e($inq['landing_url'] ?: '—') ?></td></tr>
        <tr><th>Браузър</th><td class="break small"><?= e($inq['user_agent'] ?: '—') ?></td></tr>
      </table>
    </section>

    <section class="card">
      <h2>Имейли</h2>
      <?php if (!$mails): ?><p class="muted">Няма опити за изпращане.</p><?php endif; ?>
      <?php foreach ($mails as $m): ?>
        <div class="mail <?= $m['ok'] ? 'ok' : 'fail' ?>">
          <b><?= $m['ok'] ? '✓ Изпратен' : '✗ Неуспешен' ?></b> · <?= e($m['recipient']) ?> · <?= e(strtoupper($m['transport'])) ?> · <span class="muted"><?= e(date('d.m.Y H:i:s', strtotime($m['created_at']))) ?></span>
          <?php if ($m['error']): ?><div class="small"><?= e($m['error']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$inq['anonymized_at']): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <button class="a-btn a-btn-ghost">Изпрати известието отново до мен</button>
      </form>
      <?php endif; ?>
    </section>

    <?php if (!$inq['anonymized_at']): ?>
    <form method="post" class="card" id="delForm">
      <?= csrf_field() ?><input type="hidden" name="action" value="anonymize">
      <h2>Изтриване</h2>
      <p class="muted small">Изтрива име, телефон, имейл, населено място, бележки, надписа/обекта, прегледа (SVG), каченото лого и техническите данни. Остава анонимен ред (модел, опции, цена, източник, статус) за статистиката. Не може да се отмени.</p>
      <button class="a-btn a-btn-danger">Изтрий запитването</button>
    </form>
    <script>
    document.getElementById('delForm').addEventListener('submit', function (e) {
      if (!confirm('Да изтрия ли личните данни по запитване <?= e($inq['public_code']) ?>?\n\nТова не може да се отмени. Остава само анонимен запис за статистиката.')) e.preventDefault();
    });
    </script>
    <?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
