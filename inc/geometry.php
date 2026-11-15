<?php
declare(strict_types=1);

/**
 * Геометрия и разположение на надписа.
 * ВАЖНО: същата логика е в assets/js/geometry.js – при промяна сменете и двете.
 * Константите се подават на браузъра от тук, за да няма разминаване.
 */
function geometry_layout(): array
{
    return [
        'margin_ratio' => 0.08,     // страничен отстъп като част от ширината на стената
        'margin_min_mm' => 25,      // но не по-малко от това
        'band_plain' => [0.22, 0.80],     // зона за надпис без орнамент (от горния ръб, част от височината)
        'band_orn_text' => [0.50, 0.88],  // зона за надпис, когато има орнамент
        'band_orn' => [0.10, 0.42],       // зона за орнамента
        'bridges' => [0.30, 0.70],  // мостчета: позиции като част от височината на главна буква
        'bridge_ratio' => 0.08,     // дебелина на мостчето спрямо височината на главна буква
        'bridge_min_mm' => 3,
    ];
}

/** Нормализира надписа: без водещи/крайни интервали, единични интервали между думите */
function normalize_text(string $t): string
{
    $t = preg_replace('/\s+/u', ' ', $t) ?? '';
    return trim($t);
}

function text_length(string $t): int
{
    return mb_strlen($t);
}

/** Стени на модела в мм: [ширина, височина] в реда отпред, дясно, отзад, ляво */
function model_walls(array $m): array
{
    $h = $m['h'] * 10;
    switch ($m['shape']) {
        case 'hex':
            $s = $m['w'] / 2 * 10;
            return array_fill(0, 6, [$s, $h]);
        case 'grill':
        case 'box':
        default:
            $w = $m['w'] * 10;
            $d = $m['d'] * 10;
            return [[$w, $h], [$d, $h], [$w, $h], [$d, $h]];
    }
}

/** Стените, които се декорират според избора "на колко страни" */
function decorated_walls(array $m, $sides): array
{
    $walls = model_walls($m);
    if ($sides === 'all') {
        return $walls;
    }
    $n = max(1, min(count($walls), (int)$sides));
    return array_slice($walls, 0, $n);
}

/**
 * Побира надписа в най-тясната декорирана стена.
 * Връща размер на шрифта (мм), ширина, височина на буквата (мм) и дали има малки букви.
 */
function text_fit(string $text, string $fontKey, array $walls, bool $hasOrnament): ?array
{
    $fm = font_metrics();
    $font = $fm['fonts'][$fontKey] ?? null;
    if ($text === '' || !$font || !$walls) {
        return null;
    }
    $L = geometry_layout();
    $minW = min(array_map(fn($w) => $w[0], $walls));
    $wallH = $walls[0][1];

    $sumAdv = 0;
    $yMax = 0;
    $yMin = 0;
    $hasLower = false;
    foreach (mb_str_split($text) as $ch) {
        $g = $font['g'][$ch] ?? null;
        if (!$g) {
            return null;
        }
        $sumAdv += $g[0];
        $yMax = max($yMax, $g[2]);
        $yMin = min($yMin, $g[1]);
        if (mb_strtoupper($ch) !== $ch) {
            $hasLower = true;
        }
    }
    $upm = $font['upm'];
    $margin = max($L['margin_min_mm'], $L['margin_ratio'] * $minW);
    $availW = $minW - 2 * $margin;
    $band = $hasOrnament ? $L['band_orn_text'] : $L['band_plain'];
    $bandH = ($band[1] - $band[0]) * $wallH;

    $sizeW = $availW / ($sumAdv / $upm);
    $sizeH = $bandH / (max(1, $yMax - $yMin) / $upm);
    $size = min($sizeW, $sizeH);

    $letter = ($hasLower ? $font['xh'] : $font['cap']) * $size / $upm;
    return [
        'size_mm' => round($size, 2),
        'width_mm' => round($sumAdv * $size / $upm, 2),
        'letter_mm' => round($letter, 1),
        'cap_mm' => round($font['cap'] * $size / $upm, 1),
        'has_lower' => $hasLower,
    ];
}

/**
 * Ориентир за админа: колко ГЛАВНИ букви се побират при минималната височина
 * (по средната ширина на българските главни букви) на най-тясната от дадените стени.
 */
function text_capacity_estimate(array $walls, string $fontKey, float $minMm): ?int
{
    $font = font_metrics()['fonts'][$fontKey] ?? null;
    if (!$font || !$walls) {
        return null;
    }
    $L = geometry_layout();
    $minW = min(array_map(fn($w) => $w[0], $walls));
    $availW = $minW - 2 * max($L['margin_min_mm'], $L['margin_ratio'] * $minW);
    $sum = 0;
    $n = 0;
    foreach (mb_str_split('АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЬЮЯ') as $c) {
        if (isset($font['g'][$c])) {
            $sum += $font['g'][$c][0];
            $n++;
        }
    }
    if (!$n) {
        return null;
    }
    // ширина на една буква при минималната височина (в мм)
    $perChar = ($sum / $n) * $minMm / $font['cap'];
    return max(0, (int)floor($availW / $perChar));
}

/** Площ на ламарината (м²) – приблизително, ако админът не е задал своя */
function sheet_area_m2(array $m): float
{
    if (!empty($m['sheet_area_m2'])) {
        return (float)$m['sheet_area_m2'];
    }
    $w = $m['w'];
    $d = $m['d'];
    $h = $m['h'];
    switch ($m['shape']) {
        case 'hex':
            $s = $w / 2;
            return (3 * sqrt(3) / 2 * $s * $s + 6 * $s * $h) / 10000;
        default:
            return ($w * $d + 2 * ($w + $d) * $h) / 10000;
    }
}
