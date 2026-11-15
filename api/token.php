<?php
declare(strict_types=1);
// Нов CSRF токен за конфигуратора, когато сесията е изтекла (напр. страницата е стояла отворена дълго).
// Не пипа form_t: в нова сесия проверката "твърде бързо" не важи, а honeypot и лимитът по IP остават.
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}
json_out(['ok' => true, 'csrf' => csrf_token()]);
