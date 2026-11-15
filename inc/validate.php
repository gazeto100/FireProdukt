<?php
declare(strict_types=1);

/**
 * Проверява конфигурацията, изпратена от браузъра, спрямо каталога в базата.
 * Връща ['errors' => [поле => съобщение], 'config' => нормализирана конфигурация, 'price' => ..., 'weight' => ...]
 */
function validate_config(array $in, array $cat): array
{
    $errors = [];
    $model = catalog_model($cat, (string)($in['model'] ?? ''));
    if (!$model) {
        return ['errors' => ['model' => 'Изберете модел.'], 'config' => null];
    }

    $compat = [];
    foreach ($model['options'] as $mo) {
        $compat[$mo['id']] = $mo;
    }
    // опциите на модела, групирани по група
    $byGroup = [];
    foreach ($compat as $oid => $mo) {
        $o = $cat['options'][$oid];
        $byGroup[$o['group']][$o['code']] = ['opt' => $o, 'mo' => $mo];
    }

    $inOpts = is_array($in['options'] ?? null) ? $in['options'] : [];
    $sel = [];
    $chosen = [];
    foreach ($cat['groups'] as $g) {
        $code = $g['code'];
        $avail = $byGroup[$code] ?? [];
        if (!$avail) {
            continue;
        }
        if ($g['multi']) {
            $vals = $inOpts[$code] ?? [];
            if (!is_array($vals)) {
                $vals = [];
            }
            $chosen[$code] = [];
            foreach (array_unique(array_map('strval', $vals)) as $v) {
                if (!isset($avail[$v])) {
                    $errors['options.' . $code] = 'Избраната екстра не е налична за този модел.';
                    continue;
                }
                $sel[] = $avail[$v];
                $chosen[$code][] = ['code' => $v, 'name' => $avail[$v]['opt']['name']];
            }
            continue;
        }
        $v = isset($inOpts[$code]) ? (string)$inOpts[$code] : '';
        if ($v === '') {
            foreach ($avail as $c => $a) {
                if ($a['mo']['default']) {
                    $v = $c;
                    break;
                }
            }
            if ($v === '') {
                $v = (string)array_key_first($avail);
            }
        }
        if (!isset($avail[$v])) {
            $errors['options.' . $code] = 'Невалиден избор за „' . $g['name'] . '“.';
            continue;
        }
        $sel[] = $avail[$v];
        $chosen[$code] = ['code' => $v, 'name' => $avail[$v]['opt']['name']];
    }

    // Надпис
    $text = normalize_text((string)($in['text'] ?? ''));
    $fontKey = isset($chosen['font']['code']) ? (string)($byGroup['font'][$chosen['font']['code']]['opt']['render']['font'] ?? 'russo') : 'russo';
    $sidesVal = isset($chosen['sides']['code']) ? ($byGroup['sides'][$chosen['sides']['code']]['opt']['render']['sides'] ?? 1) : 1;
    $pattern = isset($chosen['ornament']['code']) ? ($byGroup['ornament'][$chosen['ornament']['code']]['opt']['render']['pattern'] ?? null) : null;
    $walls = decorated_walls($model, $sidesVal);
    $fit = null;

    $thickness = isset($chosen['material']['code']) ? (float)($byGroup['material'][$chosen['material']['code']]['opt']['render']['thickness_mm'] ?? 2) : 2.0;
    $minMm = min_letter_for_thickness($thickness);
    if ($text !== '') {
        // перфорацията е около надписа и не заема отделна зона
        $chk = text_check(text_lines($text), $fontKey, $walls, $pattern !== null && $pattern !== 'perf', $minMm, $model['shape']);
        $fit = $chk['fit'];
        if ($chk['error'] !== null) {
            $errors['text'] = $chk['error'];
        }
    }

    $qty = filter_var($in['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    if ($qty === false) {
        $errors['quantity'] = 'Количеството трябва да е между 1 и 50.';
        $qty = 1;
    }

    // правила между опциите (от данните): „изключва се с“ (в двете посоки) и „изисква“
    $selIds = array_map(fn($x) => $x['opt']['id'], $sel);
    foreach ($cat['rules'] ?? [] as $r) {
        $a = $cat['options'][$r['option']] ?? null;
        $b = $cat['options'][$r['target']] ?? null;
        if (!$a || !$b || !in_array($a['id'], $selIds, true)) {
            continue;
        }
        if ($r['kind'] === 'excludes' && in_array($b['id'], $selIds, true)) {
            $errors['options.' . $a['group']] = '„' . $a['name'] . '“ и „' . $b['name'] . '“ не могат заедно.';
        } elseif ($r['kind'] === 'requires' && !in_array($b['id'], $selIds, true)) {
            $errors['options.' . $a['group']] = '„' . $a['name'] . '“ изисква „' . $b['name'] . '“.';
        }
    }

    $isDecorated = $text !== '' || $pattern !== null;
    $price = calc_price($model, $sel, count($walls), $isDecorated, (int)$qty);
    $weight = calc_weight($model, $sel);

    $config = [
        'model' => [
            'code' => $model['code'],
            'name' => $model['name'],
            'type' => $model['type'],
            'shape' => $model['shape'],
            'w' => $model['w'],
            'd' => $model['d'],
            'h' => $model['h'],
        ],
        'options' => $chosen,
        'text' => $text,
        'text_length' => text_length($text),
        'font' => $fontKey,
        'letter_mm' => $fit['letter_mm'] ?? null,
        'text_lines' => $fit['lines'] ?? 0,
        'min_letter_mm' => $minMm,
        'decorated_walls' => $isDecorated ? count($walls) : 0,
        'quantity' => (int)$qty,
        'weight_kg' => $weight['kg'],
        'area_m2' => $weight['area_m2'],
    ];

    return ['errors' => $errors, 'config' => $config, 'price' => $price, 'weight' => $weight, 'model' => $model];
}

/**
 * Единственото правило, което спира надписа: минималната височина на буквата.
 * (Плюс технически таван 40 знака и само позволени знаци.) Огледало на checkText() в geometry.js.
 * Връща ['error' => ?string, 'fit' => ?array, 'split' => ?array (предложение за 2 реда)]
 */
function text_check(array $lines, string $fontKey, array $walls, bool $hasOrnament, float $minMm, string $shape = 'box'): array
{
    $text = implode("\n", $lines);
    $len = text_length($text);
    if ($len > TEXT_MAX_CHARS) {
        return ['error' => 'Надписът е твърде дълъг – до ' . TEXT_MAX_CHARS . ' знака.', 'fit' => null, 'split' => null];
    }
    $allowed = font_metrics()['allowed'] ?? '';
    $bad = [];
    foreach (mb_str_split(str_replace("\n", '', $text)) as $ch) {
        if (mb_strpos($allowed, $ch) === false) {
            $bad[$ch] = true;
        }
    }
    if ($bad) {
        return ['error' => 'Тези знаци не могат да се изрежат: ' . implode(' ', array_keys($bad)), 'fit' => null, 'split' => null];
    }
    $fit = text_fit($lines, $fontKey, $walls, $hasOrnament, $shape);
    if (!$fit) {
        return ['error' => 'Надписът не може да бъде разположен.', 'fit' => null, 'split' => null];
    }
    if ($fit['letter_mm'] + 0.05 >= $minMm) {
        return ['error' => null, 'fit' => $fit, 'split' => null];
    }
    $split = count($lines) === 1 ? split_two_lines($lines[0]) : null;
    $err = sprintf('Буквите стават %d мм, а минимумът е %s мм. ', (int)floor($fit['letter_mm']), fmt_num($minMm))
        . ($split ? 'Съкрати надписа, избери по-тесен шрифт или го раздели на два реда.' : 'Съкрати надписа или избери по-тесен шрифт.');
    return ['error' => $err, 'fit' => $fit, 'split' => $split];
}

/** Четим списък на конфигурацията за имейли и админа */
function config_lines(array $cfg, array $cat): array
{
    if (($cfg['kind'] ?? '') === 'project') {
        return project_lines($cfg);
    }
    $groupNames = [];
    foreach ($cat['groups'] as $g) {
        $groupNames[$g['code']] = $g['name'];
    }
    $m = $cfg['model'];
    $lines = [
        'Модел' => $m['name'],
        'Размери' => ($m['shape'] ?? '') === 'hex'
            ? sprintf('Ø%s × %s см', fmt_num($m['w']), fmt_num($m['h']))
            : sprintf('%s × %s × %s см', fmt_num($m['w']), fmt_num($m['d']), fmt_num($m['h'])),
    ];
    foreach ($cfg['options'] as $g => $v) {
        $label = $groupNames[$g] ?? $g;
        if ($g === 'extras') {
            $lines[$label] = $v ? implode(', ', array_column($v, 'name')) : 'без';
        } elseif ($g === 'font' || $g === 'sides') {
            continue;
        } else {
            $lines[$label] = $v['name'];
        }
    }
    if ($cfg['text'] !== '') {
        $lines['Надпис'] = '„' . str_replace("\n", ' / ', $cfg['text']) . '“ (' . $cfg['text_length'] . ' знака' . (($cfg['text_lines'] ?? 1) > 1 ? ', на 2 реда' : '') . ')';
        $lines['Шрифт'] = $cfg['options']['font']['name'] ?? '';
        $lines['Височина на буквите'] = $cfg['letter_mm'] !== null ? '≈ ' . number_format((float)$cfg['letter_mm'], 0, ',', '') . ' мм' : '—';
    } elseif (!empty($cfg['text_removed'])) {
        $lines['Надпис'] = '(изтрит, ' . (int)($cfg['text_length'] ?? 0) . ' знака)';
    } else {
        $lines['Надпис'] = 'без';
    }
    if ($cfg['decorated_walls'] > 0) {
        $lines['Декорирани страни'] = ($cfg['options']['sides']['name'] ?? '') . ' (' . $cfg['decorated_walls'] . ')';
    }
    $lines['Количество'] = $cfg['quantity'] . ' бр.';
    $lines['Приблизително тегло'] = fmt_kg((float)$cfg['weight_kg']) . ' / бр.';
    return $lines;
}

function fmt_num($n): string
{
    return rtrim(rtrim(number_format((float)$n, 1, ',', ''), '0'), ',');
}
