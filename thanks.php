<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();

$code = (string)($_GET['c'] ?? '');
$inq = null;
if ($code !== '' && ($_SESSION['last_inquiry'] ?? '') === $code) {
    $inq = db_one('SELECT * FROM inquiries WHERE public_code = ?', [$code]);
}
$lines = [];
if ($inq) {
    $cfg = json_decode($inq['config_json'], true);
    $lines = config_lines($cfg, load_catalog(false));
}
$showPrices = setting('show_prices', '0') === '1';
// Събитието Lead към Pixel/GA4 се праща само веднъж за запитване (не при презареждане)
$fireLead = $inq && empty($_SESSION['lead_sent'][$code]);
if ($fireLead) {
    $_SESSION['lead_sent'][$code] = 1;
}

page_header('Благодарим ви', ['body_class' => 'page-thanks']);
?>
<main class="page-narrow">
  <div class="thanks-card">
    <h1>Благодарим ви!</h1>
    <?php if ($inq && $inq['kind'] === 'idea'): ?>
      <p>Получихме вашата идея. Ще се свържем с вас <strong>до <?= e(contact_deadline()) ?></strong>, за да уточним детайлите. Предложението с цена и проект изпращаме след това.</p>
    <?php elseif ($inq && $inq['kind'] === 'project'): ?>
      <p>Получихме запитването ви по проект. Ще се свържем с вас <strong>до <?= e(contact_deadline()) ?></strong>, за да уточним детайлите. Предложението с цена и проект изпращаме след това. Цената е по оферта.</p>
    <?php else: ?>
      <p>Получихме вашето запитване. Ще се свържем с вас <strong>до <?= e(contact_deadline()) ?></strong>, за да потвърдим детайлите и цената.</p>
    <?php endif; ?>
    <?php if ($inq): ?>
      <p>Номер на запитването: <span class="code"><?= e($inq['public_code']) ?></span></p>
      <?php if ($inq['svg']): ?>
        <div class="thanks-preview"><img src="data:image/svg+xml;base64,<?= base64_encode($inq['svg']) ?>" alt="Вашият модел"></div>
      <?php endif; ?>
      <table class="summary">
        <?php foreach ($lines as $k => $v): ?>
          <tr><th><?= e($k) ?></th><td><?= nl2br(e($v)) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($showPrices && $inq['kind'] === 'config'): /* при проект и „По идея“ – цена по оферта */ ?>
          <tr><th>Ориентировъчна цена</th><td><strong><?= e(price_range_text((float)$inq['price_min'], (float)$inq['price_max'])) ?></strong></td></tr>
        <?php endif; ?>
      </table>
      <?php if ($inq['email']): ?><p class="small muted">Изпратихме копие на <?= e($inq['email']) ?>.</p><?php endif; ?>
    <?php endif; ?>
    <?php if (!$inq || $inq['kind'] !== 'idea'): ?><p class="small muted">Изгледът е ориентировъчен. Преди изработка ще получите финален проект за одобрение.</p><?php endif; ?>
    <p><a class="btn btn-ghost" href="<?= e(url('')) ?>">Към началото</a></p>
  </div>
</main>
<?php if ($fireLead): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.fpEvent) window.fpEvent('Lead', <?= json_encode(['content_name' => json_decode($inq['config_json'], true)['model']['name']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>);
});
</script>
<?php endif; ?>
<?php page_footer(); ?>
