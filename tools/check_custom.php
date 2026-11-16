<?php
/**
 * Проверка на размера по избор от командния ред (не се качва на хостинга):
 *   php tools/check_custom.php
 * - пример за цена: стандартен размер и размер по избор от същата форма;
 * - палет и „две части“ за всички стандартни размери (материал по подразбиране и най-тежкият);
 * - сървърна проверка на граници, стъпка и отношение височина/ширина.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/inc/bootstrap.php';
function size_txt(array $m): string
{
    return shape_is_round($m['shape']) ? 'Ø' . fmt_num($m['w']) . '×' . fmt_num($m['h']) : fmt_num($m['w']) . '×' . fmt_num($m['d']) . '×' . fmt_num($m['h']);
}

$cat = load_catalog(true);
$S = $cat['settings'];
$cur = $S['currency'];
$formBy = array_column($cat['forms'], null, 'code');

function line(string $s = ''): void { echo $s, "\n"; }
function mat_opts(array $cat, array $m): array
{
    $out = [];
    foreach ($m['options'] as $mo) {
        $o = $cat['options'][$mo['id']];
        if ($o['group'] === 'material') $out[$o['code']] = ['opt' => $o, 'mo' => $mo];
    }
    return $out;
}

line('=== 1. Пример за цена (форма „Огнище квадратно“, инокс 2 мм, без екстри и надпис) ===');
$std = catalog_model($cat, 'sq_m');
$v1 = validate_config(['model' => 'sq_m', 'options' => ['material' => 'inox2'], 'quantity' => 1], $cat);
line(sprintf('Стандартен %s (%s): %s – %s %s (цената на размера от админа)', $std['size_label'], size_txt($std), $v1['price']['min'], $v1['price']['max'], $cur));
$mat = mat_opts($cat, $std)['inox2']['opt'];
$k = 1 + $S['custom_surcharge_pct'] / 100;
foreach ([[50, 50, 35], [70, 70, 40]] as [$w, $d, $h]) {
    $v = validate_config(['model' => 'sq_m', 'custom' => ['w' => $w, 'd' => $d, 'h' => $h], 'options' => ['material' => 'inox2'], 'quantity' => 1], $cat);
    $a = $v['price']['area_m2'];
    $fx = $formBy['sq']['custom']['fixed'];
    line(sprintf('По избор %s×%s×%s см: площ %.3f m² → (%.3f × %s…%s + %s) × %.2f = %s – %s %s; тегло %s кг%s%s',
        $w, $d, $h, $a, $a, $mat['price_m2_min'], $mat['price_m2_max'], $fx, $k, $v['price']['min'], $v['price']['max'], $cur,
        fmt_num($v['weight']['kg']), $v['config']['pallet'] ? '; ПАЛЕТ' : '', $v['config']['two_parts'] ? '; ДВЕ ЧАСТИ (' . $v['config']['two_parts_part'] . ')' : ''));
}

line();
line('=== 2. Стандартни размери: тегло, палет (праг ' . fmt_num($S['pallet_kg']) . ' кг), побиране в листа ' . $S['sheet_w_mm'] . '×' . $S['sheet_h_mm'] . ' мм ===');
foreach ($cat['models'] as $m) {
    $mats = mat_opts($cat, $m);
    $row = [];
    foreach ($mats as $code => $x) {
        $v = validate_config(['model' => $m['code'], 'options' => ['material' => $code], 'quantity' => 1], $cat);
        $row[] = $code . ' ' . fmt_num($v['weight']['kg']) . ' кг' . ($v['config']['pallet'] ? ' [палет]' : '');
    }
    $sf = sheet_fit($m, $formBy[$m['form']]['construction'] ?? 'panels');
    line(sprintf('%-14s %-12s %s%s', $m['code'], size_txt($m), implode(', ', $row), $sf['fits'] ? '' : ' | не се побира: ' . $sf['part']));
}

line();
line('=== 3. Сървърна проверка на границите ===');
$cases = [
    ['sq_m', ['w' => 135, 'd' => 135, 'h' => 40]],
    ['sq_m', ['w' => 47, 'd' => 47, 'h' => 30]],
    ['sq_m', ['w' => 40, 'd' => 40, 'h' => 40]],
    ['sq_m', ['w' => 120, 'd' => 120, 'h' => 60]],
    ['hex60', ['w' => 125, 'd' => 125, 'h' => 50]],
    [$formBy['ringbowl']['models'][0] ?? 'rb60', ['w' => 70, 'd' => 70, 'h' => 50]],
];
foreach ($cases as [$code, $dims]) {
    $v = validate_config(['model' => $code, 'custom' => $dims, 'quantity' => 1], $cat);
    line(sprintf('%-6s %s → %s', $code, json_encode($dims), $v['errors']['size'] ?? 'приет'));
}
