<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
$_SESSION['form_t'] = time();

page_header('По ваша идея', [
    'body_class' => 'page-idea',
    'description' => 'Изпратете снимка, скица или чертеж с размери – ще ви върнем проект и оферта за огнище, скара или друго изделие от метал по ваша идея.',
]);
$opt = fn(string $name, string $value, string $label, bool $checked, string $extra = '') =>
    '<label class="opt opt-compact"><input type="radio" name="' . e($name) . '" value="' . e($value) . '"' . ($checked ? ' checked' : '') . '><span class="opt-in">' . $extra . '<b>' . e($label) . '</b></span></label>';
?>
<main>
  <section class="hero hero-projects">
    <div class="wrap">
      <h1>По ваша идея</h1>
      <p class="lead">Имате идея за огнище, скара или друго изделие от метал, което не е сред моделите ни? Изпратете снимка или скица с размери – ще ви върнем проект и оферта.</p>
      <a class="btn btn-lg" href="#idea-form">Изпратете идеята си</a>
    </div>
  </section>

  <section class="wrap" aria-labelledby="how">
    <h2 class="sec-title" id="how">Как работи</h2>
    <div class="benefits benefits-steps">
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="8" y="12" width="32" height="24" rx="3" fill="none" stroke="currentColor" stroke-width="3"/><path d="M14 30l7-8 6 6 4-4 5 6" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/></svg>
        <h3>1. Изпращате идеята</h3>
        <p>Снимка, скица или чертеж и приблизителни размери – това е достатъчно.</p>
      </div>
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M10 38l4-12 18-18 8 8-18 18z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M28 12l8 8" stroke="currentColor" stroke-width="3"/></svg>
        <h3>2. Правим проект</h3>
        <p>Уточняваме детайлите с вас и предлагаме промени, ако са нужни за безопасност и изработка.</p>
      </div>
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M12 8h18l8 8v24H12z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M18 24h12M18 31h12" stroke="currentColor" stroke-width="3"/></svg>
        <h3>3. Получавате оферта</h3>
        <p>Проект и цена за одобрение. Изработваме след като ги одобрите.</p>
      </div>
    </div>

    <h2 class="sec-title">Какво може да изпратите</h2>
    <div class="benefits idea-examples">
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 16h8l3-5h10l3 5h8v22H8z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><circle cx="24" cy="26" r="7" fill="none" stroke="currentColor" stroke-width="3"/></svg>
        <h3>Снимка на мястото</h3>
        <p>Терасата, двора или ъгъла, където ще стои – с рулетка в кадъра или с размерите отделно.</p>
      </div>
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 34c6-10 10 2 16-8s10 4 16-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M8 40h32" stroke="currentColor" stroke-width="3"/></svg>
        <h3>Скица на ръка</h3>
        <p>Рисунка на лист със записани размери, снимана с телефона.</p>
      </div>
      <div class="benefit">
        <svg viewBox="0 0 48 48" aria-hidden="true"><rect x="8" y="8" width="32" height="32" fill="none" stroke="currentColor" stroke-width="3"/><path d="M14 30h20M14 30V16M14 16l20 14" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
        <h3>Чертеж</h3>
        <p>PDF, DXF или SVG от архитект или дизайнер.</p>
      </div>
    </div>
  </section>

  <section class="wrap project-form-sec" id="idea-form">
    <h2 class="sec-title">Изпратете идеята си</h2>
    <form id="ideaForm" class="card-form" novalidate enctype="multipart/form-data">
      <fieldset class="field">
        <legend>Какво искате <span class="req">*</span></legend>
        <div class="opts opts-3">
          <?php $i = 0; foreach (idea_whats() as $k => $label): ?><?= $opt('what', $k, $label, $i++ === 0) ?><?php endforeach; ?>
        </div>
        <p class="err" data-err="what" hidden></p>
      </fieldset>

      <div class="field">
        <label for="i-desc">Описание с ваши думи <span class="req">*</span></label>
        <textarea id="i-desc" name="description" rows="4" maxlength="3000" placeholder="напр. Кръгло огнище за двора, с място за скара отгоре и името на къщата отстрани…" required></textarea>
        <p class="small muted" id="descCount">поне 20 знака</p>
        <p class="err" data-err="description" hidden></p>
      </div>

      <fieldset class="field">
        <legend>Размери в см <span class="muted">(приблизителни са достатъчни)</span></legend>
        <div class="three-col">
          <div><label for="i-w">Ширина <span class="req">*</span></label><input id="i-w" name="w" type="number" inputmode="decimal" min="1" max="1000" step="any" required><p class="err" data-err="w" hidden></p></div>
          <div><label for="i-d">Дълбочина</label><input id="i-d" name="d" type="number" inputmode="decimal" min="1" max="1000" step="any"><p class="err" data-err="d" hidden></p></div>
          <div><label for="i-h">Височина <span class="req">*</span></label><input id="i-h" name="h" type="number" inputmode="decimal" min="1" max="1000" step="any" required><p class="err" data-err="h" hidden></p></div>
        </div>
      </fieldset>

      <fieldset class="field">
        <legend>Материал</legend>
        <div class="opts opts-2">
          <?php foreach (idea_materials() as $k => $label): ?><?= $opt('material', $k, $label, $k === 'unsure', $k !== 'unsure' ? '<span class="sw sw-' . e($k) . '"></span>' : '') ?><?php endforeach; ?>
        </div>
      </fieldset>

      <div class="field">
        <label for="i-ins">Надпис или лого <span class="muted">(по желание)</span></label>
        <input id="i-ins" name="inscription" type="text" maxlength="200" placeholder="напр. ВИЛА РОЗА или „логото на ресторанта – прилагам го“">
      </div>

      <div class="two-col">
        <div class="field">
          <label for="i-qty">Брой</label>
          <input id="i-qty" name="quantity" type="number" inputmode="numeric" min="1" max="500" value="1">
          <p class="err" data-err="quantity" hidden></p>
        </div>
        <div class="field">
          <label for="i-budget">Ориентировъчен бюджет</label>
          <select id="i-budget" name="budget">
            <?php foreach (idea_budgets() as $k => $label): ?><option value="<?= e($k) ?>" <?= $k === 'unknown' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>

      <fieldset class="field">
        <legend>Снимки, скица или чертеж <span class="req">*</span> <span class="muted">(1 до 5 файла)</span></legend>
        <div class="idea-pick">
          <label class="btn btn-ghost idea-btn"><input type="file" id="i-camera" accept="image/*" capture="environment" hidden>📷 Снимай</label>
          <label class="btn btn-ghost idea-btn"><input type="file" id="i-files" multiple accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf,.svg,.dxf,image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf,image/svg+xml" hidden>Избери файлове</label>
        </div>
        <ul class="idea-files" id="fileList" aria-live="polite"></ul>
        <p class="small muted" id="fileSum">JPG, PNG, WebP, HEIC, PDF, SVG или DXF · до 8 MB на файл, до 25 MB общо.</p>
        <p class="err err-pre" data-err="files" hidden></p>
      </fieldset>

      <h3 class="form-sub">Данни за връзка</h3>
      <div class="field">
        <label for="i-name">Име <span class="req">*</span></label>
        <input id="i-name" name="name" type="text" maxlength="100" autocomplete="name" required>
        <p class="err" data-err="name" hidden></p>
      </div>
      <div class="two-col">
        <div class="field">
          <label for="i-phone">Телефон <span class="req">*</span></label>
          <input id="i-phone" name="phone" type="tel" inputmode="tel" maxlength="32" autocomplete="tel" placeholder="0888 123 456" required>
          <p class="err" data-err="phone" hidden></p>
        </div>
        <div class="field">
          <label for="i-email">Имейл <span class="req">*</span></label>
          <input id="i-email" name="email" type="email" inputmode="email" maxlength="190" autocomplete="email" required>
          <p class="err" data-err="email" hidden></p>
        </div>
      </div>
      <div class="field">
        <label for="i-city">Населено място</label>
        <input id="i-city" name="city" type="text" maxlength="100" autocomplete="address-level2">
      </div>
      <div class="hp" aria-hidden="true">
        <label for="i-website">Уебсайт</label>
        <input id="i-website" name="website" type="text" tabindex="-1" autocomplete="off">
      </div>
      <div class="field check">
        <label><input type="checkbox" name="rights" value="1"> <span>Потвърждавам, че имам право да използвам изпратените изображения и идея. <span class="req">*</span></span></label>
        <p class="err" data-err="rights" hidden></p>
      </div>
      <div class="field check">
        <label><input type="checkbox" name="consent" value="1"> <span>Съгласен/на съм данните ми да бъдат обработени за отговор на запитването съгласно <a href="<?= e(url('privacy.php')) ?>" target="_blank">политиката за поверителност</a>. <span class="req">*</span></span></label>
        <p class="err" data-err="consent" hidden></p>
      </div>
      <button type="submit" class="btn btn-lg btn-block" id="ideaBtn">Изпрати идеята</button>
      <p class="err form-msg" id="ideaMsg" role="alert" hidden></p>
      <p class="idea-note"><?= e(idea_footer_text()) ?></p>
      <p class="small muted">Без плащане и без задължение.</p>
    </form>
  </section>
</main>
<script>
window.FP_ICFG = <?= json_encode([
    'csrf' => csrf_token(),
    'url' => url('api/idea.php'),
    'tokenUrl' => url('api/token.php'),
    'maxFile' => IDEA_FILE_MAX_BYTES,
    'maxTotal' => IDEA_TOTAL_MAX_BYTES,
    'maxFiles' => IDEA_MAX_FILES,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<?php page_footer(['scripts' => ['js/idea.js']]); ?>
