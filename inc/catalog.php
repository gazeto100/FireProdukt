<?php
declare(strict_types=1);

/**
 * Зарежда целия каталог (типове, модели, групи опции, опции, съвместимости)
 * в една структура, която се ползва и от сървъра (валидация, цена), и от конфигуратора.
 */
function load_catalog(bool $activeOnly = true): array
{
    $act = $activeOnly ? ' WHERE active = 1' : '';

    $types = db_all('SELECT id, code, name, hint FROM product_types' . $act . ' ORDER BY sort, id');
    $groups = db_all('SELECT id, code, name, hint, multi, required FROM option_groups ORDER BY sort, id');
    $groupCode = [];
    foreach ($groups as &$g) {
        $g['id'] = (int)$g['id'];
        $g['multi'] = (bool)$g['multi'];
        $g['required'] = (bool)$g['required'];
        $groupCode[$g['id']] = $g['code'];
    }
    unset($g);

    $options = [];
    foreach (db_all('SELECT * FROM options' . $act . ' ORDER BY sort, id') as $o) {
        $options[(int)$o['id']] = [
            'id' => (int)$o['id'],
            'group' => $groupCode[(int)$o['group_id']] ?? '',
            'code' => $o['code'],
            'name' => $o['name'],
            'hint' => $o['hint'],
            'subgroup' => $o['subgroup'] ?? null,
            'price_type' => $o['price_type'],
            'price_min' => (float)$o['price_min'],
            'price_max' => (float)$o['price_max'],
            'price_m2_min' => isset($o['price_m2_min']) ? (float)$o['price_m2_min'] : null,
            'price_m2_max' => isset($o['price_m2_max']) ? (float)$o['price_m2_max'] : null,
            'weight_kg' => (float)$o['weight_kg'],
            'render' => json_decode((string)$o['render'], true) ?: new stdClass(),
        ];
    }

    $typeCodes = [];
    foreach ($types as &$t) {
        $t['id'] = (int)$t['id'];
        $typeCodes[$t['id']] = $t['code'];
    }
    unset($t);

    // форми (група размери); неактивна форма скрива всичките си размери
    $formRows = db_all('SELECT * FROM forms' . $act . ' ORDER BY sort, id');
    $formById = [];
    foreach ($formRows as $fr) {
        $formById[(int)$fr['id']] = $fr;
    }

    $models = [];
    $rows = db_all('SELECT * FROM models' . $act . ' ORDER BY sort, id');
    $compat = db_all('SELECT * FROM model_options');
    $byModel = [];
    foreach ($compat as $c) {
        $byModel[(int)$c['model_id']][] = $c;
    }
    foreach ($rows as $m) {
        $tid = (int)$m['type_id'];
        if (!isset($typeCodes[$tid])) {
            continue; // типът е неактивен
        }
        $fid = (int)($m['form_id'] ?? 0);
        if ($activeOnly && $fid && !isset($formById[$fid])) {
            continue; // формата е изключена от админа
        }
        $opts = [];
        foreach ($byModel[(int)$m['id']] ?? [] as $c) {
            $oid = (int)$c['option_id'];
            if (!isset($options[$oid])) {
                continue;
            }
            $opts[] = [
                'id' => $oid,
                'price_min' => $c['price_min'] !== null ? (float)$c['price_min'] : $options[$oid]['price_min'],
                'price_max' => $c['price_max'] !== null ? (float)$c['price_max'] : $options[$oid]['price_max'],
                'default' => (bool)$c['is_default'],
            ];
        }
        $models[] = [
            'id' => (int)$m['id'],
            'code' => $m['code'],
            'type' => $typeCodes[$tid],
            'name' => $m['name'],
            'form' => isset($formById[$fid]) ? $formById[$fid]['code'] : 'm' . $m['id'],
            'size_label' => (string)($m['size_label'] ?? ''),
            'shape' => $m['shape'],
            'w' => (float)$m['width_cm'],
            'd' => (float)$m['depth_cm'],
            'h' => (float)$m['height_cm'],
            'max_chars' => (int)$m['max_chars'],
            'price_min' => (float)$m['price_min'],
            'price_max' => (float)$m['price_max'],
            'sheet_area_m2' => $m['sheet_area_m2'] !== null ? (float)$m['sheet_area_m2'] : null,
            'description' => $m['description'],
            'options' => $opts,
        ];
    }

    // Форми с поне един активен размер (модел без форма става самостоятелна форма)
    $forms = [];
    foreach ($models as $m) {
        $fc = $m['form'];
        if (!isset($forms[$fc])) {
            $fr = null;
            foreach ($formById as $x) {
                if ($x['code'] === $fc) $fr = $x;
            }
            $forms[$fc] = ['code' => $fc, 'name' => $fr['name'] ?? $m['name'], 'type' => $m['type'], 'shape' => $fr['shape'] ?? $m['shape'],
                'description' => $fr['description'] ?? $m['description'], 'sort' => (int)($fr['sort'] ?? 99), 'models' => [],
                // граници за размер по избор (null = формата няма размер по избор)
                'custom' => $fr && !empty($fr['custom_enabled']) && $fr['w_min'] !== null ? [
                    'w_min' => (float)$fr['w_min'], 'w_max' => (float)$fr['w_max'], 'd_min' => (float)$fr['d_min'], 'd_max' => (float)$fr['d_max'],
                    'h_min' => (float)$fr['h_min'], 'h_max' => (float)$fr['h_max'], 'step' => max(0.5, (float)$fr['step_cm']),
                    'max_h_ratio' => (float)$fr['max_h_ratio'], 'fixed' => (float)$fr['custom_fixed'], 'mode' => 'wdh',
                ] : null];
        }
        $forms[$fc]['models'][] = $m['code'];
    }
    $forms = array_values($forms);
    $mByCode = array_column($models, null, 'code');
    foreach ($forms as &$fx) {
        if ($fx['custom']) {
            $fx['custom']['mode'] = form_dims_mode($fx['shape'], array_map(fn($c) => $mByCode[$c], $fx['models']));
        }
    }
    unset($fx);
    usort($forms, fn($a, $b) => [$a['type'], $a['sort']] <=> [$b['type'], $b['sort']]);

    // правила между опциите (изключват се / изискват) – само между активни опции
    $rules = [];
    foreach (db_all('SELECT option_id, kind, target_id FROM option_rules') as $r) {
        if (isset($options[(int)$r['option_id']], $options[(int)$r['target_id']])) {
            $rules[] = ['option' => (int)$r['option_id'], 'kind' => $r['kind'], 'target' => (int)$r['target_id']];
        }
    }

    // Показваме само типове, които имат поне един активен модел
    $usedTypes = array_unique(array_column($models, 'type'));
    $types = array_values(array_filter($types, fn($t) => in_array($t['code'], $usedTypes, true)));

    return [
        'types' => $types,
        'groups' => $groups,
        'options' => $options,
        'models' => $models,
        'forms' => $forms,
        'rules' => $rules,
        'settings' => [
            'min_letter_2mm' => (float)setting('min_letter_2mm', '30'),
            'min_letter_3mm' => (float)setting('min_letter_3mm', '35'),
            'show_prices' => setting('show_prices', '0') === '1',
            'currency' => setting('currency', '€'),
            'sheet_w_mm' => (float)setting('sheet_w_mm', '2970'),
            'sheet_h_mm' => (float)setting('sheet_h_mm', '1470'),
            'pallet_kg' => (float)setting('pallet_kg', '30'),
            'custom_surcharge_pct' => (float)setting('custom_surcharge_pct', '20'),
        ],
        'layout' => geometry_layout(),
        'fonts' => font_metrics(),
    ];
}

/** Каталог за браузъра – без цени, ако са изключени от админа */
function public_catalog(): array
{
    $cat = load_catalog(true);
    if (!$cat['settings']['show_prices']) {
        foreach ($cat['models'] as &$m) {
            unset($m['price_min'], $m['price_max']);
            foreach ($m['options'] as &$o) {
                unset($o['price_min'], $o['price_max']);
            }
            unset($o);
        }
        unset($m);
        foreach ($cat['options'] as &$o) {
            unset($o['price_min'], $o['price_max'], $o['price_type'], $o['price_m2_min'], $o['price_m2_max']);
        }
        unset($o);
        foreach ($cat['forms'] as &$fm) {
            if ($fm['custom']) unset($fm['custom']['fixed']);
        }
        unset($fm);
        unset($cat['settings']['custom_surcharge_pct']);
    }
    return $cat;
}

function font_metrics(): array
{
    static $m = null;
    if ($m === null) {
        $m = json_decode((string)file_get_contents(APP_ROOT . '/assets/fonts/metrics.json'), true) ?: ['allowed' => '', 'fonts' => []];
    }
    return $m;
}

function catalog_model(array $cat, string $code): ?array
{
    foreach ($cat['models'] as $m) {
        if ($m['code'] === $code) {
            return $m;
        }
    }
    return null;
}
