<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
record_event('configurator_view');
$cat = public_catalog();
$_SESSION['form_t'] = time();

page_header('Сглоби своя модел', ['body_class' => 'page-config', 'hide_cta' => true, 'preload_fonts' => ['russo']]);
?>
<main class="cfg">
  <div class="cfg-stage">
    <div class="cfg-preview" id="preview" aria-label="Преглед на модела"></div>
    <div class="cfg-specs" id="specs"></div>
  </div>

  <div class="cfg-panel">
    <p class="cfg-note">Изгледът е ориентировъчен. Преди изработка ще получите финален проект за одобрение.</p>
    <p class="cfg-flags" id="cfgFlags" role="status" hidden></p>
    <p class="cfg-price" data-price hidden></p>
    <noscript><p class="err">Конфигураторът изисква JavaScript. Обадете ни се или пишете – ще го сглобим заедно.</p></noscript>

    <?php if (!$cat['models']): ?>
      <p class="err">В момента няма активни модели.</p>
    <?php endif; ?>

    <div id="steps"></div>

    <section class="step step-send" id="st-send">
      <h2><span class="num"></span>Количество и запитване</h2>
      <form id="inqForm" novalidate>
        <div class="field qty-field">
          <label for="qty">Брой</label>
          <div class="qty">
            <button type="button" data-qty="-1" aria-label="Намали">−</button>
            <input id="qty" name="qty" type="number" inputmode="numeric" min="1" max="50" value="1">
            <button type="button" data-qty="1" aria-label="Увеличи">+</button>
          </div>
        </div>
        <p class="cfg-price" data-price hidden></p>

        <div class="field">
          <label for="f-name">Име <span class="req">*</span></label>
          <input id="f-name" name="name" type="text" autocomplete="name" maxlength="100" required>
          <p class="err" data-err="name" hidden></p>
        </div>
        <div class="field">
          <label for="f-phone">Телефон <span class="req">*</span></label>
          <input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="32" placeholder="0888 123 456" required>
          <p class="err" data-err="phone" hidden></p>
        </div>
        <div class="field">
          <label for="f-email">Имейл <span class="muted">(за копие на запитването)</span></label>
          <input id="f-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="190">
          <p class="err" data-err="email" hidden></p>
        </div>
        <div class="field">
          <label for="f-city">Населено място</label>
          <input id="f-city" name="city" type="text" autocomplete="address-level2" maxlength="100">
        </div>
        <div class="field">
          <label for="f-note">Бележка</label>
          <textarea id="f-note" name="note" rows="3" maxlength="2000" placeholder="Срок, доставка, специални желания…"></textarea>
        </div>
        <div class="hp" aria-hidden="true">
          <label for="f-website">Уебсайт</label>
          <input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div class="field check">
          <label><input type="checkbox" name="consent" value="1"> <span>Съгласен/на съм данните ми да бъдат обработени за отговор на запитването съгласно <a href="<?= e(url('privacy.php')) ?>" target="_blank">политиката за поверителност</a>. <span class="req">*</span></span></label>
          <p class="err" data-err="consent" hidden></p>
        </div>
        <p class="err" id="textBlock" hidden>Поправете надписа по-горе, за да изпратите запитването.</p>
        <button type="submit" class="btn btn-block btn-lg" id="sendBtn">Изпрати запитване</button>
        <p class="err form-msg" id="formMsg" role="alert" hidden></p>
        <p class="small muted">Без плащане и без задължение. Ще ви се обадим до 1 работен ден с оферта и проект.</p>
      </form>
    </section>
  </div>
</main>

<div class="cfg-bar" id="bar">
  <div class="bar-txt"><b id="barModel"></b><span id="barInfo"></span></div>
  <button type="button" class="btn" id="barBtn">Към запитването</button>
</div>

<script>
window.FP_CATALOG = <?= json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
window.FP_CFG = <?= json_encode([
    'csrf' => csrf_token(),
    't' => $_SESSION['form_t'],
    'inquiryUrl' => url('api/inquiry.php'),
    'trackUrl' => url('api/track.php'),
    'tokenUrl' => url('api/token.php'),
    'fontsUrl' => url('assets/fonts/'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<?php page_footer(['scripts' => ['js/geometry.js', 'js/render.js', 'js/configurator.js']]); ?>
