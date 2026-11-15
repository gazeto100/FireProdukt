<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

if (admin_id()) {
    redirect(url('admin/inquiries.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $ip = ip_hash();
    db()->prepare('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)')->execute();
    $fails = (int)db_one('SELECT COUNT(*) c FROM login_attempts WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)', [$ip])['c'];
    if ($fails >= 5) {
        $error = 'Твърде много неуспешни опити. Опитайте отново след 15 минути.';
    } else {
        $user = db_one('SELECT * FROM admins WHERE username = ?', [trim((string)($_POST['username'] ?? ''))]);
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];
            unset($_SESSION['csrf']);
            db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $user['id']]);
            }
            redirect(url('admin/inquiries.php'));
        }
        db()->prepare('INSERT INTO login_attempts (ip_hash) VALUES (?)')->execute([$ip]);
        $error = 'Грешно потребителско име или парола.';
    }
}

admin_header('Вход');
?>
<form method="post" class="a-login card">
  <h1>Вход в админ панела</h1>
  <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Потребител<input name="username" autocomplete="username" required autofocus></label>
  <label>Парола<input name="password" type="password" autocomplete="current-password" required></label>
  <button class="a-btn">Вход</button>
</form>
<?php admin_footer(); ?>
