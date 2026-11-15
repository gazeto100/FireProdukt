<?php
declare(strict_types=1);

function page_header(string $title, array $o = []): void
{
    $site = setting('site_name', 'FireProdukt');
    $desc = $o['description'] ?? 'Огнища и скари от неръждаема стомана по поръчка, с ваш изрязан надпис. Изработено в България.';
    $bodyClass = $o['body_class'] ?? '';
    ?><!doctype html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title === '' ? $site : $title . ' – ' . $site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#1b1714">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 2c2 6 9 9 9 17a9 9 0 0 1-18 0c0-5 3-7 4-11 1 3 3 4 3 4s-1-6 2-10z" fill="#ff7a1a"/></svg>') ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php foreach ($o['preload_fonts'] ?? [] as $f): ?>
<link rel="preload" href="<?= e(url('assets/fonts/' . $f . '.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
  <div class="wrap header-inner">
    <a class="logo" href="<?= e(url('')) ?>">
      <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2c2 6 9 9 9 17a9 9 0 0 1-18 0c0-5 3-7 4-11 1 3 3 4 3 4s-1-6 2-10z"/></svg>
      <span><?= e($site) ?></span>
    </a>
    <?php if (empty($o['hide_cta'])): ?>
    <a class="btn btn-sm" href="<?= e(url('configurator.php')) ?>">Сглоби своя модел</a>
    <?php endif; ?>
  </div>
</header>
<?php
}

function page_footer(array $o = []): void
{
    $site = setting('site_name', 'FireProdukt');
    $phone = setting('contact_phone', '');
    $pixel = setting('meta_pixel_id', '');
    $ga4 = setting('ga4_id', '');
    $tracking = $pixel !== '' || $ga4 !== '';
    ?>
<footer class="site-footer">
  <div class="wrap">
    <p><strong><?= e($site) ?></strong> · Огнища и скари от неръждаема стомана, изработени в България.</p>
    <?php if ($phone !== ''): ?><p>Телефон: <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></p><?php endif; ?>
    <p class="footer-links"><a href="<?= e(url('za-hoteli.php')) ?>">За хотели и къщи за гости</a> · <a href="<?= e(url('terms.php')) ?>">Общи условия</a> · <a href="<?= e(url('privacy.php')) ?>">Поверителност</a><?php if ($tracking): ?> · <button type="button" class="link-btn" data-cookie-settings>Бисквитки</button><?php endif; ?></p>
  </div>
</footer>
<?php if ($tracking): ?>
<div class="cookie" id="cookieBanner" role="dialog" aria-live="polite" aria-label="Бисквитки" hidden>
  <p>Ползваме бисквитки за статистика и реклама (Google Analytics и Meta Pixel), само ако ги приемете. Необходимите за работата на сайта са винаги включени. <a href="<?= e(url('privacy.php#cookies')) ?>">Научете повече</a></p>
  <div class="cookie-btns">
    <button type="button" class="btn" data-consent="granted">Приемам</button>
    <button type="button" class="btn btn-ghost" data-consent="denied">Само необходимите</button>
  </div>
</div>
<script>window.FP_TRACK = <?= json_encode(['pixel' => $pixel, 'ga4' => $ga4], JSON_HEX_TAG) ?>;</script>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>"></script>
<?php foreach ($o['scripts'] ?? [] as $s): ?>
<script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
