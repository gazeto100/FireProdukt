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
            'price_type' => $o['price_type'],
            'price_min' => (float)$o['price_min'],
            'price_max' => (float)$o['price_max'],
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

    // Показваме само типове, които имат поне един активен модел
    $usedTypes = array_unique(array_column($models, 'type'));
    $types = array_values(array_filter($types, fn($t) => in_array($t['code'], $usedTypes, true)));

    return [
        'types' => $types,
        'groups' => $groups,
        'options' => $options,
        'models' => $models,
        'settings' => [
            'min_letter_mm' => (float)setting('min_letter_mm', '30'),
            'show_prices' => setting('show_prices', '0') === '1',
            'currency' => setting('currency', '€'),
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
            unset($o['price_min'], $o['price_max'], $o['price_type']);
        }
        unset($o);
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
