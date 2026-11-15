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

    if ($text !== '') {
        $len = text_length($text);
        if ($len > $model['max_chars']) {
            $errors['text'] = sprintf('Надписът е %d знака, а за този модел са позволени до %d.', $len, $model['max_chars']);
        }
        $allowed = $cat['fonts']['allowed'] ?? '';
        $bad = [];
        foreach (mb_str_split($text) as $ch) {
            if (mb_strpos($allowed, $ch) === false) {
                $bad[$ch] = true;
            }
        }
        if ($bad) {
            $errors['text'] = 'Тези знаци не могат да се изрежат: ' . implode(' ', array_keys($bad));
        }
        if (!isset($errors['text'])) {
            $fit = text_fit($text, $fontKey, $walls, $pattern !== null);
            $minMm = (float)$cat['settings']['min_letter_mm'];
            if (!$fit) {
                $errors['text'] = 'Надписът не може да бъде разположен.';
            } elseif ($fit['letter_mm'] + 0.05 < $minMm) {
                $errors['text'] = sprintf(
                    'Буквите стават %s мм, а минимумът за изрязване е %s мм. Съкратете надписа, изберете по-тесен шрифт или главни букви.',
                    number_format($fit['letter_mm'], 1, ',', ''),
                    rtrim(rtrim(number_format($minMm, 1, ',', ''), '0'), ',')
                );
            }
        }
    }

    $qty = filter_var($in['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    if ($qty === false) {
        $errors['quantity'] = 'Количеството трябва да е между 1 и 50.';
        $qty = 1;
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
        'decorated_walls' => $isDecorated ? count($walls) : 0,
        'quantity' => (int)$qty,
        'weight_kg' => $weight['kg'],
        'area_m2' => $weight['area_m2'],
    ];

    return ['errors' => $errors, 'config' => $config, 'price' => $price, 'weight' => $weight, 'model' => $model];
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
        $lines['Надпис'] = '„' . $cfg['text'] . '“ (' . $cfg['text_length'] . ' знака)';
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
