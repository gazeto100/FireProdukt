<?php
// Проверка от командния ред: височина на буквите и грешки, изчислени от сървъра.
// php tools/check_text.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/inc/bootstrap.php';
$cat = load_catalog(true);
$tests = ['Я', 'ИВАНОВИ', 'ФАМИЛИЯ ПЕТРОВ', 'ABCDEFGHIJKLMN', 'Ivanovi', 'Семейство Ив', 'ЖЖЖЖЖЖЖЖЖЖЖЖЖЖ', 'ПЕТРОВИ ЗАВИНАГИ', 'Fire €'];
foreach (['russo', 'oswald', 'dela'] as $font) {
    foreach ($tests as $t) {
        $r = validate_config(['model' => 'sq_m', 'options' => ['font' => $font, 'sides' => 's1', 'ornament' => 'none'], 'text' => $t], $cat);
        printf("%s | %s | %s | %s\n", $font, $t, $r['config']['letter_mm'] ?? '-', $r['errors']['text'] ?? 'OK');
    }
}
