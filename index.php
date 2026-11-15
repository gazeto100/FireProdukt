<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$cat = public_catalog();

page_header('', [
    'body_class' => 'page-home',
    'preload_fonts' => ['russo'],
    'description' => 'Огнища и мангали от неръждаема стомана с ваш изрязан надпис. Изработено в България. Сглобете модела онлайн и получете оферта до 1 работен ден.',
]);
?>
<main>
  <section class="hero">
    <div class="wrap hero-inner">
      <div class="hero-visual" id="heroVisual" aria-hidden="true"></div>
      <div class="hero-copy">
        <h1>Огнище с вашето име, изрязано в стомана</h1>
        <p class="lead">Неръждаема стомана, изработка в България. Сглобете своя модел онлайн за 2 минути и получете оферта до 1 работен ден.</p>
        <a class="btn btn-lg" href="<?= e(url('configurator.php')) ?>">Сглоби своя модел</a>
        <p class="hero-note">Без плащане онлайн · Финален проект за одобрение преди изработка</p>
      </div>
    </div>
  </section>

  <section class="wrap benefits" aria-label="Предимства">
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 40V18l16-10 16 10v22H30V28H18v12z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></svg>
      <h2>Изработено в България</h2>
      <p>Режем, огъваме и заваряваме в собствен цех. Всяко огнище се прави по поръчка, не идва от склад.</p>
    </div>
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 4 40 12v12c0 10-7 17-16 20C15 41 8 34 8 24V12z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="m17 24 5 5 9-10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <h2>Неръждаема стомана</h2>
      <p>Инокс 2 или 3 мм – не ръждясва, издържа на жар, дъжд и сняг. По желание и черна стомана с прахово боядисване.</p>
    </div>
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M10 38h28M14 32 24 8l10 24M18 23h12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <h2>Вашият надпис</h2>
      <p>Име, фамилия или послание – изрязани с лазер и осветени отвътре от огъня. С орнамент по избор.</p>
    </div>
  </section>

  <section class="wrap gallery-sec">
    <h2 class="sec-title">Модели</h2>
    <p class="sec-sub">Изберете основа, а размера на надписа, материала и екстрите добавяте в конфигуратора.</p>
    <div class="gallery" id="gallery">
      <?php foreach ($cat['models'] as $m): ?>
        <a class="g-card" href="<?= e(url('configurator.php?model=' . rawurlencode($m['code']))) ?>" data-model="<?= e($m['code']) ?>">
          <span class="g-img"></span>
          <span class="g-txt">
            <b><?= e($m['name']) ?></b>
            <small><?= $m['shape'] === 'hex' ? 'Ø' . e(fmt_num($m['w'])) . ' × ' . e(fmt_num($m['h'])) : e(fmt_num($m['w'])) . ' × ' . e(fmt_num($m['d'])) . ' × ' . e(fmt_num($m['h'])) ?> см</small>
            <span class="g-link">Сглоби този модел →</span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="wrap how">
    <h2 class="sec-title">Как става</h2>
    <ol class="steps-how">
      <li><b>Сглобявате модела</b><span>Избирате форма, материал, надпис и екстри и веднага виждате как ще изглежда.</span></li>
      <li><b>Изпращате запитване</b><span>Само име и телефон. Без регистрация и без плащане.</span></li>
      <li><b>Получавате проект и оферта</b><span>До 1 работен ден ви се обаждаме. Изработваме след като одобрите финалния проект.</span></li>
    </ol>
    <p class="center"><a class="btn btn-lg" href="<?= e(url('configurator.php')) ?>">Сглоби своя модел</a></p>
  </section>
</main>
<script>window.FP_CATALOG = <?= json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;</script>
<?php page_footer(['scripts' => ['js/geometry.js', 'js/render.js', 'js/home.js']]); ?>
