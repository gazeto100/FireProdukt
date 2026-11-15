<?php
// Проверка от командния ред: правилата за надписа, както ги прилага сървърът.
//   php tools/check_text.php            -> таблица с примерите
//   php tools/check_text.php --json     -> същото като JSON (за сравнение с браузъра)
// Единственото правило, което спира надписа, е минималната височина на буквата (по дебелина на материала).
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/inc/bootstrap.php';
$cat = load_catalog(true);

// [надпис, шрифт, модел, материал, страни]
$examples = [
    ['сем. Иванови', 'oswald', 'sq_s', 'inox2', 's1'],
    ['сем. Иванови', 'russo', 'sq_s', 'inox2', 's1'],
    ['СЕМЕЙСТВО ИВАНОВИ', 'russo', 'sq_s', 'inox2', 's1'],
    ["СЕМЕЙСТВО\nИВАНОВИ", 'russo', 'sq_s', 'inox2', 's1'],
    ['ЖЕЛЕЗНИЦА', 'dela', 'hex60', 'inox2', 's1'],
    ['ЖЕЛЕЗНИЦА', 'dela', 'hex60', 'black3', 's1'],
    ['Я', 'russo', 'mangal_picnic', 'inox2', 's1'],
    ['ВИЛА РИЛА', 'russo', 'mangal_picnic', 'black3', 's2'],
    ['HOTEL ELENA BANSKO', 'oswald', 'sq_l', 'inox3', 's1'],
    ['ЧЕСТИТ ЮБИЛЕЙ НА НАЙ-ДОБРИЯ ДЯДО НА СВЕТА!', 'oswald', 'sq_l', 'inox2', 's1'],
];
$fontNames = [];
foreach ($cat['options'] as $o) {
    if ($o['group'] === 'font') $fontNames[$o['code']] = $o['name'];
    if ($o['group'] === 'material') $matNames[$o['code']] = $o['name'];
}
$rows = [];
foreach ($examples as [$text, $font, $model, $mat, $sides]) {
    $r = validate_config(['model' => $model, 'options' => ['font' => $font, 'material' => $mat, 'sides' => $sides, 'ornament' => 'none'], 'text' => $text], $cat);
    $m = catalog_model($cat, $model);
    $rows[] = [
        'text' => $text,
        'font' => $fontNames[$font] ?? $font,
        'model' => $m['name'] ?? $model,
        'material' => $matNames[$mat] ?? $mat,
        'min' => $r['config']['min_letter_mm'] ?? null,
        'letter' => $r['config']['letter_mm'] ?? null,
        'lines' => $r['config']['text_lines'] ?? 0,
        'result' => $r['errors']['text'] ?? 'OK',
    ];
}
if (in_array('--json', $argv, true)) {
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit;
}
echo "| # | Надпис | Шрифт | Модел | Материал | Мин. | Височина | Резултат |\n|---|---|---|---|---|---|---|---|\n";
foreach ($rows as $i => $r) {
    printf("| %d | %s | %s | %s | %s | %s мм | %s | %s |\n", $i + 1, str_replace("\n", ' ⏎ ', $r['text']), $r['font'], $r['model'], $r['material'],
        fmt_num($r['min']), $r['letter'] !== null ? '≈ ' . fmt_num($r['letter']) . ' мм' . ($r['lines'] > 1 ? ' (2 реда)' : '') : '—', $r['result']);
}
