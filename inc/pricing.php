<?php
declare(strict_types=1);

/**
 * Ориентировъчна цена и тегло.
 * ВАЖНО: същата логика е в assets/js/geometry.js (calcPrice / calcWeight).
 *
 * $sel – списък избрани опции: ['opt' => опция от каталога, 'mo' => ред от съвместимостта на модела]
 */
function calc_price(array $model, array $sel, int $decoratedCount, bool $isDecorated, int $qty): array
{
    $factorMin = 1.0;
    $factorMax = 1.0;
    $addMin = 0.0;
    $addMax = 0.0;
    foreach ($sel as $s) {
        $o = $s['opt'];
        $pmin = $s['mo']['price_min'];
        $pmax = $s['mo']['price_max'];
        if ($o['price_type'] === 'factor') {
            $factorMin *= $pmin ?: 1;
            $factorMax *= $pmax ?: 1;
            continue;
        }
        if ($o['group'] === 'sides' && !$isDecorated) {
            continue; // без надпис и орнамент страните не се таксуват
        }
        $mult = !empty($o['render']['per_side']) ? $decoratedCount : 1;
        $addMin += $pmin * $mult;
        $addMax += $pmax * $mult;
    }
    // Единичната цена се закръгля до 5 (надолу за "от", нагоре за "до"), после се умножава по количеството
    $unitMin = floor(($model['price_min'] * $factorMin + $addMin) / 5) * 5;
    $unitMax = ceil(($model['price_max'] * $factorMax + $addMax) / 5) * 5;
    return [
        'unit_min' => $unitMin,
        'unit_max' => $unitMax,
        'min' => $unitMin * $qty,
        'max' => $unitMax * $qty,
    ];
}

function calc_weight(array $model, array $sel): array
{
    $thickness = 2.0;
    $density = 7.93;
    $extra = 0.0;
    foreach ($sel as $s) {
        $o = $s['opt'];
        if ($o['group'] === 'material') {
            $thickness = (float)($o['render']['thickness_mm'] ?? $thickness);
            $density = (float)($o['render']['density'] ?? $density);
        }
        $extra += $o['weight_kg'];
    }
    $area = sheet_area_m2($model);
    // м² × мм × кг/дм³ = кг  (1 м² × 1 мм = 1 дм³)
    $kg = $area * $thickness * $density + $extra;
    return ['kg' => round($kg, 1), 'area_m2' => round($area, 3)];
}

/**
 * Цена за размер по избор:
 * (площ на ламарината m² × цена на m² за материала + фиксирана сума за формата) × (1 + надценка %)
 * + опциите (крачета, надпис по страни, орнамент, екстри) както при стандартните размери.
 * Единичната цена се закръгля до 5 и после се умножава по количеството.
 * ВАЖНО: огледало в assets/js/geometry.js (calcPriceCustom).
 */
function calc_price_custom(array $model, array $form, array $sel, int $decoratedCount, bool $isDecorated, int $qty): array
{
    $area = sheet_area_m2(array_merge($model, ['sheet_area_m2' => null]));
    $m2min = 0.0;
    $m2max = 0.0;
    $addMin = 0.0;
    $addMax = 0.0;
    foreach ($sel as $s) {
        $o = $s['opt'];
        if ($o['group'] === 'material') {
            $m2min = (float)($o['price_m2_min'] ?? 0);
            $m2max = (float)($o['price_m2_max'] ?? 0);
            continue;
        }
        if ($o['price_type'] === 'factor') {
            continue;
        }
        if ($o['group'] === 'sides' && !$isDecorated) {
            continue;
        }
        $mult = !empty($o['render']['per_side']) ? $decoratedCount : 1;
        $addMin += $s['mo']['price_min'] * $mult;
        $addMax += $s['mo']['price_max'] * $mult;
    }
    $k = 1 + (float)setting('custom_surcharge_pct', '20') / 100;
    $fixed = (float)$form['custom']['fixed'];
    $unitMin = floor((($area * $m2min + $fixed) * $k + $addMin) / 5) * 5;
    $unitMax = ceil((($area * $m2max + $fixed) * $k + $addMax) / 5) * 5;
    return ['unit_min' => $unitMin, 'unit_max' => $unitMax, 'min' => $unitMin * $qty, 'max' => $unitMax * $qty, 'area_m2' => round($area, 3)];
}
