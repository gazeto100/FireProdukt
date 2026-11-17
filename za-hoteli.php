<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
$_SESSION['form_t'] = time();
// моделите за обекти са формите от вид „по проект“ (в базата, редактират се от админа)
$cat = public_catalog();
$pforms = project_forms($cat);
$byCode = array_column($cat['models'], null, 'code');

page_header('За хотели и къщи за гости', [
    'body_class' => 'page-projects',
    'preload_fonts' => ['russo'],
    'description' => 'Огнища по проект за хотели, ресторанти и къщи за гости: купи, огнища маси, пейки около огъня и колони с лого. Инокс, черна стомана или кортен.',
]);
?>
<main>
  <section class="hero hero-projects">
    <div class="wrap">
      <h1>Огнища за хотели, ресторанти и къщи за гости</h1>
      <p class="lead">Проектираме и изработваме огнища по размерите на вашия обект – с логото ви, изрязано в метала и осветено от огъня. Изработено в България.</p>
      <a class="btn btn-lg" href="#project-form">Изпратете запитване по проект</a>
    </div>
  </section>

  <section class="wrap benefits" aria-label="Предимства">
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 40h32M12 40V20h24v20M18 20v-8h12v8" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></svg>
      <h2>По вашия проект</h2>
      <p>Размери, форма и брой според пространството – тераса, двор, лаундж зона или вход.</p>
    </div>
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="15" fill="none" stroke="currentColor" stroke-width="3"/><path d="M17 27l7-12 7 12z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></svg>
      <h2>С логото на обекта</h2>
      <p>Изпратете логото си и ще го изрежем в стената на огнището – вечер свети от огъня.</p>
    </div>
    <div class="benefit">
      <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M10 14h28v20H10z" fill="none" stroke="currentColor" stroke-width="3"/><path d="M10 22h28M22 14v20" stroke="currentColor" stroke-width="3"/></svg>
      <h2>Инокс, черна стомана или кортен</h2>
      <p>Материали за постоянно стоене навън. Кортенът развива естествена патина и не се боядисва.</p>
    </div>
  </section>

  <section class="wrap gallery-sec">
    <h2 class="sec-title">Модели за обекти</h2>
    <p class="sec-sub">Сглобете модела в конфигуратора – размер, материал, името на обекта и екстри. Цената е по оферта.</p>
    <div class="gallery gallery-big">
      <?php foreach ($pforms as $fm):
          $ms = array_values(array_filter(array_map(fn($c) => $byCode[$c] ?? null, $fm['models'])));
          $ws = array_column($ms, 'w'); $pre = shape_is_round($fm['shape']) ? 'Ø' : '';
          $range = min($ws) == max($ws) ? $pre . fmt_num(min($ws)) . ' см' : 'от ' . $pre . fmt_num(min($ws)) . ' до ' . $pre . fmt_num(max($ws)) . ' см';
          if ($fm['custom']) $range .= ' или по мярка'; ?>
        <a class="g-card g-form g-proj" href="<?= e(url('configurator.php?form=' . rawurlencode($fm['code']))) ?>" data-model="<?= e($ms[0]['code']) ?>" data-text="ХОТЕЛ">
          <span class="g-img"></span>
          <span class="g-txt">
            <b><?= e($fm['name']) ?></b>
            <small><?= e($range) ?> · инокс, черна стомана или кортен</small>
            <?php if (!empty($fm['description'])): ?><span class="g-desc"><?= e($fm['description']) ?></span><?php endif; ?>
            <span class="btn btn-sm g-btn">Сглоби</span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="wrap project-form-sec" id="project-form">
    <h2 class="sec-title">Запитване по проект</h2>
    <p class="sec-sub">Искате нещо извън моделите? Опишете обекта – ще ви се обадим до 1 работен ден и ще подготвим предложение с проект.</p>
    <form id="projForm" class="card-form" novalidate enctype="multipart/form-data">
      <div class="two-col">
        <div class="field">
          <label for="p-object">Обект <span class="req">*</span></label>
          <input id="p-object" name="object" type="text" maxlength="150" placeholder="напр. Хотел „Елена“, къща за гости, ресторант" required>
          <p class="err" data-err="object" hidden></p>
        </div>
        <div class="field">
          <label for="p-city">Населено място</label>
          <input id="p-city" name="city" type="text" maxlength="100" autocomplete="address-level2">
        </div>
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

      <fieldset class="field">
        <legend>Желан материал <span class="req">*</span></legend>
        <div class="opts opts-3 mat-opts">
          <?php foreach (project_materials() as $k => $label): ?>
            <label class="opt opt-compact"><input type="radio" name="material" value="<?= e($k) ?>" <?= $k === 'inox' ? 'checked' : '' ?>><span class="opt-in"><span class="sw sw-<?= e($k) ?>"></span><b><?= e(project_materials_short()[$k]) ?></b></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="field">
        <legend>Интересуват ме <span class="muted">(по желание)</span></legend>
        <div class="chips">
          <?php foreach ($pforms as $fm): ?>
            <label class="chip"><input type="checkbox" name="interests[]" value="<?= e($fm['code']) ?>"><span><?= e($fm['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="field">
        <label for="p-logo">Лого <span class="muted">(по желание – PNG, JPG, SVG или PDF до 5 MB)</span></label>
        <input id="p-logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.svg,.pdf,image/png,image/jpeg,image/svg+xml,application/pdf">
        <p class="small muted" id="logoInfo"></p>
        <p class="err" data-err="logo" hidden></p>
      </div>

      <div class="field">
        <label for="p-note">Бележка</label>
        <textarea id="p-note" name="note" rows="4" maxlength="3000" placeholder="Размери на мястото, брой седящи, идеи, бюджет…"></textarea>
      </div>

      <h3 class="form-sub">Лице за контакт</h3>
      <div class="two-col">
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
      </div>
      <div class="field">
        <label for="p-email">Имейл</label>
        <input id="p-email" name="email" type="email" inputmode="email" maxlength="190" autocomplete="email">
        <p class="err" data-err="email" hidden></p>
      </div>
      <div class="hp" aria-hidden="true">
        <label for="p-website">Уебсайт</label>
        <input id="p-website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>
      <div class="field check">
        <label><input type="checkbox" name="consent" value="1"> <span>Съгласен/на съм данните ми да бъдат обработени за отговор на запитването съгласно <a href="<?= e(url('privacy.php')) ?>" target="_blank">политиката за поверителност</a>. <span class="req">*</span></span></label>
        <p class="err" data-err="consent" hidden></p>
      </div>
      <button type="submit" class="btn btn-lg btn-block" id="projBtn">Изпрати запитване по проект</button>
      <p class="err form-msg" id="projMsg" role="alert" hidden></p>
      <p class="small muted">Без плащане и без задължение.</p>
    </form>
  </section>
</main>
<script>
window.FP_CATALOG = <?= json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
window.FP_PCFG = <?= json_encode([
    'csrf' => csrf_token(),
    'url' => url('api/project.php'),
    'tokenUrl' => url('api/token.php'),
    'maxBytes' => UPLOAD_MAX_BYTES,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<?php page_footer(['scripts' => ['js/geometry.js', 'js/render.js', 'js/home.js', 'js/projects.js']]); ?>
