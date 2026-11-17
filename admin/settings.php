<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();
admin_post_guard();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'test') {
        $to = setting('notify_email', '');
        if ($to === '') {
            flash('Първо въведете имейл за известия.', 'err');
        } else {
            $ok = send_mail($to, 'Тестов имейл от ' . site_name(), "Ако четете това, изпращането на имейли работи.\n\n" . date('d.m.Y H:i:s'));
            $last = db_all('SELECT transport, ok, error FROM mail_log WHERE inquiry_id IS NULL ORDER BY id DESC LIMIT 2');
            $detail = implode(' | ', array_map(fn($r) => strtoupper($r['transport']) . ': ' . ($r['ok'] ? 'успех' : ($r['error'] ?? 'грешка')), array_reverse($last)));
            flash(($ok ? 'Тестовият имейл е изпратен до ' . $to . '. ' : 'Тестовият имейл НЕ е изпратен. ') . $detail, $ok ? 'ok' : 'err');
        }
        redirect(url('admin/settings.php'));
    }

    $in = fn($k) => trim((string)($_POST[$k] ?? ''));
    $vals = [
        'site_name' => $in('site_name') ?: 'FireProdukt',
        'notify_email' => $in('notify_email'),
        'contact_deadline' => $in('contact_deadline') ?: '2 работни дни',
        'contact_phone' => $in('contact_phone'),
        'currency' => $in('currency') ?: '€',
        'show_prices' => isset($_POST['show_prices']) ? '1' : '0',
        'min_letter_2mm' => str_replace(',', '.', $in('min_letter_2mm')),
        'min_letter_3mm' => str_replace(',', '.', $in('min_letter_3mm')),
        'text_edge_mm' => str_replace(',', '.', $in('text_edge_mm')),
        'bridge_mm' => str_replace(',', '.', $in('bridge_mm')),
        'inquiry_rate_limit' => $in('inquiry_rate_limit'),
        'sheet_w_mm' => str_replace(',', '.', $in('sheet_w_mm')),
        'sheet_h_mm' => str_replace(',', '.', $in('sheet_h_mm')),
        'pallet_kg' => str_replace(',', '.', $in('pallet_kg')),
        'custom_surcharge_pct' => str_replace(',', '.', $in('custom_surcharge_pct')),
        'mail_from' => $in('mail_from'),
        'smtp_host' => $in('smtp_host'),
        'smtp_port' => $in('smtp_port') ?: '587',
        'smtp_secure' => in_array($in('smtp_secure'), ['tls', 'ssl', 'none'], true) ? $in('smtp_secure') : 'tls',
        'smtp_user' => $in('smtp_user'),
        'meta_pixel_id' => $in('meta_pixel_id'),
        'ga4_id' => $in('ga4_id'),
    ];
    if ($vals['notify_email'] !== '' && !filter_var($vals['notify_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Невалиден имейл за известия.';
    if ($vals['mail_from'] !== '' && !filter_var($vals['mail_from'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Невалиден имейл в полето „От (From)“.';
    foreach (['min_letter_2mm' => 'Минималната буква за 2 мм', 'min_letter_3mm' => 'Минималната буква за 3 мм'] as $kk => $lab) {
        if (!is_numeric($vals[$kk]) || (float)$vals[$kk] < 5 || (float)$vals[$kk] > 200) $errors[] = "$lab трябва да е между 5 и 200 мм.";
    }
    if (!is_numeric($vals['text_edge_mm']) || (float)$vals['text_edge_mm'] < 5 || (float)$vals['text_edge_mm'] > 150) $errors[] = 'Полето от ръба трябва да е между 5 и 150 мм.';
    if (!is_numeric($vals['bridge_mm']) || (float)$vals['bridge_mm'] < 1 || (float)$vals['bridge_mm'] > 20) $errors[] = 'Ширината на мостчето трябва да е между 1 и 20 мм.';
    foreach (['sheet_w_mm' => 'Дължината на листа', 'sheet_h_mm' => 'Ширината на листа'] as $kk => $lab) {
        if (!is_numeric($vals[$kk]) || (float)$vals[$kk] < 300 || (float)$vals[$kk] > 10000) $errors[] = "$lab трябва да е между 300 и 10000 мм.";
    }
    if (!is_numeric($vals['pallet_kg']) || (float)$vals['pallet_kg'] < 1 || (float)$vals['pallet_kg'] > 1000) $errors[] = 'Прагът за палет трябва да е между 1 и 1000 кг.';
    if (!is_numeric($vals['custom_surcharge_pct']) || (float)$vals['custom_surcharge_pct'] < 0 || (float)$vals['custom_surcharge_pct'] > 300) $errors[] = 'Надценката за размер по избор трябва да е между 0 и 300 %.';
    if (!ctype_digit($vals['inquiry_rate_limit']) || (int)$vals['inquiry_rate_limit'] < 1) $errors[] = 'Лимитът за запитвания трябва да е цяло число ≥ 1.';
    if (!ctype_digit($vals['smtp_port'])) $errors[] = 'Невалиден SMTP порт.';
    if ($vals['meta_pixel_id'] !== '' && !preg_match('/^\d{6,20}$/', $vals['meta_pixel_id'])) $errors[] = 'Meta Pixel ID съдържа само цифри.';
    if ($vals['ga4_id'] !== '' && !preg_match('/^G-[A-Z0-9]{4,16}$/i', $vals['ga4_id'])) $errors[] = 'GA4 ID трябва да е във вида G-XXXXXXX.';

    if (!$errors) {
        foreach ($vals as $k => $v) {
            save_setting($k, $v);
        }
        $pass = (string)($_POST['smtp_pass'] ?? '');
        if (isset($_POST['smtp_pass_clear'])) {
            save_setting('smtp_pass', '');
        } elseif ($pass !== '') {
            save_setting('smtp_pass', secret_encrypt($pass));
        }
        flash('Настройките са запазени.');
        redirect(url('admin/settings.php'));
    }
}
$s = settings_all();
if ($errors) {
    foreach ($_POST as $k => $v) {
        if (is_string($v) && $k !== 'smtp_pass') $s[$k] = $v;
    }
    $s['show_prices'] = isset($_POST['show_prices']) ? '1' : '0';
}
$v = fn($k, $d = '') => e($s[$k] ?? $d);

admin_header('Настройки', 'settings');
?>
<h1>Настройки</h1>
<?php foreach ($errors as $er): ?><div class="flash flash-err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="settings">
  <?= csrf_field() ?>
  <section class="card">
    <h2>Общи</h2>
    <label>Име на сайта<input name="site_name" value="<?= $v('site_name') ?>"></label>
    <label>Имейл за известия за нови запитвания<input name="notify_email" type="email" value="<?= $v('notify_email') ?>"></label>
    <label>Срок за обратна връзка (след „Ще се свържем с вас до …“ – в имейла до клиента и на страницата след изпращане)<input name="contact_deadline" maxlength="60" value="<?= e(contact_deadline()) ?>" placeholder="2 работни дни"></label>
    <label>Телефон за контакт (показва се във футъра)<input name="contact_phone" value="<?= $v('contact_phone') ?>"></label>
  </section>

  <section class="card">
    <h2>Конфигуратор</h2>
    <label class="check"><input type="checkbox" name="show_prices" value="1" <?= ($s['show_prices'] ?? '0') === '1' ? 'checked' : '' ?>> Показвай ориентировъчна цена „от–до“ на клиентите</label>
    <label>Валута<input name="currency" value="<?= $v('currency', '€') ?>" maxlength="8"></label>
    <p class="small"><b>Правила за надписа.</b> Единственото, което спира надписа, е минималната височина на буквата (зависи от дебелината на материала).</p>
    <div class="two">
      <label>Мин. височина на буквата – 2 мм (мм)<input name="min_letter_2mm" type="number" step="0.5" min="5" max="200" value="<?= $v('min_letter_2mm', '30') ?>"></label>
      <label>Мин. височина на буквата – 3 мм (мм)<input name="min_letter_3mm" type="number" step="0.5" min="5" max="200" value="<?= $v('min_letter_3mm', '35') ?>"></label>
    </div>
    <div class="two">
      <label>Поле от ръба на панела (мм)<input name="text_edge_mm" type="number" step="1" min="5" max="150" value="<?= $v('text_edge_mm', '25') ?>"></label>
      <label>Мин. ширина на мостче (мм)<input name="bridge_mm" type="number" step="0.5" min="1" max="20" value="<?= $v('bridge_mm', '3') ?>"></label>
    </div>
    <p class="muted small">Материал до 2,5 мм ползва стойността за 2 мм, по-дебел – тази за 3 мм. Технически таван: 40 знака (не се показва на клиента).</p>
    <p class="small"><b>Производство и доставка.</b> Примерни стойности – потвърдете ги с производителя.</p>
    <div class="two">
      <label>Формат на листа – дължина (мм)<input name="sheet_w_mm" type="number" step="1" min="300" max="10000" value="<?= $v('sheet_w_mm', '2970') ?>"></label>
      <label>Формат на листа – ширина (мм)<input name="sheet_h_mm" type="number" step="1" min="300" max="10000" value="<?= $v('sheet_h_mm', '1470') ?>"></label>
    </div>
    <div class="two">
      <label>Праг за доставка с палет (кг)<input name="pallet_kg" type="number" step="0.5" min="1" max="1000" value="<?= $v('pallet_kg', '30') ?>"></label>
      <label>Надценка за размер по избор (%)<input name="custom_surcharge_pct" type="number" step="1" min="0" max="300" value="<?= $v('custom_surcharge_pct', '20') ?>"></label>
    </div>
    <p class="muted small">Над прага за тегло клиентът вижда „Доставката е с палет, цената се уточнява в офертата“ (за всеки модел). Детайл, който не се побира в листа, дава бележка „изработва се от две части“. Границите и фиксираната сума за размер по избор са във „Форми“, цената на m² – в материалите („Опции“).</p>
    <label>Максимум запитвания на час от един IP<input name="inquiry_rate_limit" type="number" min="1" value="<?= $v('inquiry_rate_limit', '30') ?>"></label>
  </section>

  <section class="card">
    <h2>Имейли (SMTP)</h2>
    <p class="muted small">Ако SMTP не е попълнен или не успее, се ползва PHP mail() като резервен вариант. На SuperHosting: хост <code>mail.вашдомейн.bg</code>, порт 465 (SSL) или 587 (TLS), потребител = пълният имейл адрес.</p>
    <label>SMTP хост<input name="smtp_host" value="<?= $v('smtp_host') ?>" placeholder="mail.example.bg"></label>
    <div class="two">
      <label>Порт<input name="smtp_port" type="number" value="<?= $v('smtp_port', '587') ?>"></label>
      <label>Криптиране
        <select name="smtp_secure">
          <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Без (не се препоръчва)'] as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= ($s['smtp_secure'] ?? 'tls') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Потребител<input name="smtp_user" value="<?= $v('smtp_user') ?>" autocomplete="off"></label>
    <label>Парола <span class="muted small"><?= ($s['smtp_pass'] ?? '') !== '' ? '(запазена – оставете празно, за да не се променя)' : '(не е зададена)' ?></span>
      <input name="smtp_pass" type="password" autocomplete="new-password"></label>
    <?php if (($s['smtp_pass'] ?? '') !== ''): ?><label class="check"><input type="checkbox" name="smtp_pass_clear" value="1"> Изтрий запазената парола</label><?php endif; ?>
    <label>От (From) – имейл<input name="mail_from" type="email" value="<?= $v('mail_from') ?>" placeholder="поръчки@вашдомейн.bg"></label>
  </section>

  <section class="card">
    <h2>Качени лога</h2>
    <?php $ud = upload_dir(); $pub = upload_dir_is_public($ud); $ready = upload_dir_ready(); ?>
    <p class="small">Папка: <code><?= e($ud) ?></code></p>
    <p class="small"><?= $ready ? '✓ Има права за запис.' : '<span class="warn">✗ Няма права за запис – логата няма да се записват.</span>' ?>
      <?= $pub ? '<br><span class="warn">⚠ Папката е в публичната директория (защитена само с .htaccess). Задайте upload_dir извън public_html в config/config.php.</span>' : '<br>✓ Извън публичната папка.' ?></p>
    <p class="muted small">Лимит на сървъра за качване: <?= e(ini_get('upload_max_filesize')) ?> (нужни са поне 5 MB).</p>
  </section>

  <section class="card">
    <h2>Проследяване</h2>
    <p class="muted small">Зареждат се само ако са попълнени и посетителят е приел бисквитките.</p>
    <label>Meta Pixel ID<input name="meta_pixel_id" value="<?= $v('meta_pixel_id') ?>" inputmode="numeric"></label>
    <label>GA4 Measurement ID<input name="ga4_id" value="<?= $v('ga4_id') ?>" placeholder="G-XXXXXXXXXX"></label>
  </section>

  <div class="actions">
    <button class="a-btn" name="action" value="save">Запази настройките</button>
    <button class="a-btn a-btn-ghost" name="action" value="test" formnovalidate>Изпрати тестов имейл</button>
  </div>
  <p class="muted small">„Изпрати тестов имейл“ ползва вече запазените настройки – първо запазете промените.</p>
</form>
<?php admin_footer(); ?>
