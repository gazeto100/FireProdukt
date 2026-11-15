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
            $ok = send_mail($to, 'Тестов имейл от ' . setting('site_name', 'сайта'), "Ако четете това, изпращането на имейли работи.\n\n" . date('d.m.Y H:i:s'));
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
        'contact_phone' => $in('contact_phone'),
        'currency' => $in('currency') ?: '€',
        'show_prices' => isset($_POST['show_prices']) ? '1' : '0',
        'min_letter_mm' => $in('min_letter_mm'),
        'inquiry_rate_limit' => $in('inquiry_rate_limit'),
        'mail_from' => $in('mail_from'),
        'mail_from_name' => $in('mail_from_name'),
        'smtp_host' => $in('smtp_host'),
        'smtp_port' => $in('smtp_port') ?: '587',
        'smtp_secure' => in_array($in('smtp_secure'), ['tls', 'ssl', 'none'], true) ? $in('smtp_secure') : 'tls',
        'smtp_user' => $in('smtp_user'),
        'meta_pixel_id' => $in('meta_pixel_id'),
        'ga4_id' => $in('ga4_id'),
    ];
    if ($vals['notify_email'] !== '' && !filter_var($vals['notify_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Невалиден имейл за известия.';
    if ($vals['mail_from'] !== '' && !filter_var($vals['mail_from'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Невалиден имейл в полето „От (From)“.';
    if (!is_numeric($vals['min_letter_mm']) || (float)$vals['min_letter_mm'] < 5 || (float)$vals['min_letter_mm'] > 200) $errors[] = 'Минималната височина на буквата трябва да е между 5 и 200 мм.';
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
    <label>Телефон за контакт (показва се във футъра)<input name="contact_phone" value="<?= $v('contact_phone') ?>"></label>
  </section>

  <section class="card">
    <h2>Конфигуратор</h2>
    <label class="check"><input type="checkbox" name="show_prices" value="1" <?= ($s['show_prices'] ?? '0') === '1' ? 'checked' : '' ?>> Показвай ориентировъчна цена „от–до“ на клиентите</label>
    <label>Валута<input name="currency" value="<?= $v('currency', '€') ?>" maxlength="8"></label>
    <label>Минимална височина на буквата (мм)<input name="min_letter_mm" type="number" step="0.5" min="5" max="200" value="<?= $v('min_letter_mm', '30') ?>"></label>
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
    <label>От (From) – име<input name="mail_from_name" value="<?= $v('mail_from_name') ?>"></label>
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
