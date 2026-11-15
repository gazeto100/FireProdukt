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

  <?php
  // Една карта за форма (не за всеки размер), групирани по вид; нова форма от админа се появява автоматично
  $groupTitles = ['firepit' => 'Огнища', 'grill' => 'Мангали и скари'];
  $byCode = array_column($cat['models'], null, 'code');
  $roundShapes = ['hex', 'octagon', 'cross', 'ringbowl'];
  ?>
  <section class="wrap gallery-sec" id="models">
    <h2 class="sec-title">Модели</h2>
    <p class="sec-sub">Изберете форма – размера, материала, надписа и екстрите добавяте в конфигуратора.</p>
    <?php foreach ($cat['types'] as $t): $forms = array_values(array_filter($cat['forms'], fn($f) => $f['type'] === $t['code'])); if (!$forms) continue; ?>
      <h3 class="group-title"><?= e($groupTitles[$t['code']] ?? $t['name']) ?></h3>
      <div class="gallery gallery-forms">
        <?php foreach ($forms as $fm):
            $ms = array_values(array_filter(array_map(fn($c) => $byCode[$c] ?? null, $fm['models'])));
            $ws = array_column($ms, 'w'); $pre = in_array($fm['shape'], $roundShapes, true) ? 'Ø' : '';
            $range = min($ws) == max($ws) ? $pre . fmt_num(min($ws)) . ' см' : 'от ' . $pre . fmt_num(min($ws)) . ' до ' . $pre . fmt_num(max($ws)) . ' см';
            $rep = $ms[intdiv(count($ms) - 1, 2)]['code']; ?>
          <a class="g-card g-form" href="<?= e(url('configurator.php?form=' . rawurlencode($fm['code']))) ?>" data-model="<?= e($rep) ?>">
            <span class="g-img"></span>
            <span class="g-txt">
              <b><?= e($fm['name']) ?></b>
              <small><?= e($range) ?></small>
              <span class="btn btn-sm g-btn">Сглоби</span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="promo-projects">
      <h2>За хотели, ресторанти и къщи за гости</h2>
      <p>Големи купи, огнища маси, пейки около огъня и колони с вашето лого – по проект.</p>
      <a class="btn" href="<?= e(url('za-hoteli.php')) ?>">Вижте моделите за обекти</a>
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
