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
