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

    <?php /* вид „по проект“ (обекти): последната стъпка е проектната форма; запитването се записва като „Проект“ */ ?>
    <section class="step step-send" id="st-project" hidden>
      <h2><span class="num"></span>Проект и запитване</h2>
      <p class="cfg-price cfg-quote">Цена по оферта</p>
      <form id="projForm" novalidate enctype="multipart/form-data">
        <div class="field">
          <label for="p-object">Обект <span class="req">*</span></label>
          <input id="p-object" name="object" type="text" maxlength="150" placeholder="напр. Хотел „Елена“, къща за гости, ресторант" required>
          <p class="err" data-err="object" hidden></p>
        </div>
        <div class="field">
          <label for="p-city">Населено място</label>
          <input id="p-city" name="city" type="text" maxlength="100" autocomplete="address-level2">
        </div>
        <div class="two-col">
          <div class="field">
            <label for="p-qty">Брой <span class="req">*</span></label>
            <input id="p-qty" name="quantity" type="number" inputmode="numeric" min="1" max="500" value="1" required>
            <p class="err" data-err="quantity" hidden></p>
          </div>
          <div class="field">
            <label for="p-deadline">Срок</label>
            <select id="p-deadline" name="deadline">
              <?php foreach (project_deadlines() as $k => $label): ?><option value="<?= e($k) ?>" <?= $k === 'm3' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <h3 class="form-sub">Лице за контакт</h3>
        <div class="field">
          <label for="p-name">Име <span class="req">*</span></label>
          <input id="p-name" name="contact_name" type="text" maxlength="100" autocomplete="name" required>
          <p class="err" data-err="contact_name" hidden></p>
        </div>
        <div class="field">
          <label for="p-phone">Телефон <span class="req">*</span></label>
          <input id="p-phone" name="phone" type="tel" inputmode="tel" maxlength="32" autocomplete="tel" placeholder="0888 123 456" required>
          <p class="err" data-err="phone" hidden></p>
        </div>
        <div class="field">
          <label for="p-email">Имейл <span class="req">*</span></label>
          <input id="p-email" name="email" type="email" inputmode="email" maxlength="190" autocomplete="email" required>
          <p class="err" data-err="email" hidden></p>
        </div>
        <div class="field">
          <label for="p-note">Бележка</label>
          <textarea id="p-note" name="note" rows="3" maxlength="3000" placeholder="Размери на мястото, брой седящи, монтаж, бюджет…"></textarea>
        </div>
        <div class="field">
          <label for="p-logo">Лого <span class="muted">(по желание – PNG, JPG, SVG или PDF до 5 MB)</span></label>
          <input id="p-logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.svg,.pdf,image/png,image/jpeg,image/svg+xml,application/pdf">
          <p class="small muted" id="logoInfo"></p>
          <p class="err" data-err="logo" hidden></p>
        </div>
        <div class="hp" aria-hidden="true">
          <label for="p-website">Уебсайт</label>
          <input id="p-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div class="field check">
          <label><input type="checkbox" name="consent" value="1"> <span>Съгласен/на съм данните ми да бъдат обработени за отговор на запитването съгласно <a href="<?= e(url('privacy.php')) ?>" target="_blank">политиката за поверителност</a>. <span class="req">*</span></span></label>
          <p class="err" data-err="consent" hidden></p>
        </div>
        <p class="err" id="textBlockP" hidden>Поправете надписа по-горе, за да изпратите запитването.</p>
        <button type="submit" class="btn btn-block btn-lg" id="projBtn">Изпрати запитване по проект</button>
        <p class="err form-msg" id="projMsg" role="alert" hidden></p>
        <p class="small muted">Без плащане и без задължение. Ще ви се обадим до 1 работен ден с предложение и проект.</p>
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
    'projectUrl' => url('api/project.php'),
    'maxBytes' => UPLOAD_MAX_BYTES,
    'trackUrl' => url('api/track.php'),
    'tokenUrl' => url('api/token.php'),
    'fontsUrl' => url('assets/fonts/'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<?php page_footer(['scripts' => ['js/geometry.js', 'js/render.js', 'js/configurator.js']]); ?>
