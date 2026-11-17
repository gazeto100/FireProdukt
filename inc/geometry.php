<?php
declare(strict_types=1);

/**
 * Геометрия и разположение на надписа.
 * ВАЖНО: същата логика е в assets/js/geometry.js – при промяна сменете и двете.
 * Константите (вкл. настройките от админа) се подават на браузъра от тук, за да няма разминаване.
 */
const TEXT_MAX_CHARS = 40;   // технически таван срещу злоупотреба (не се показва на клиента)
const TEXT_MAX_LINES = 2;

/** Кръгли форми: размерът е диаметър (Ø) × височина. Огледало на ROUND_SHAPES в geometry.js */
const ROUND_SHAPES = ['hex', 'octagon', 'ringbowl', 'plancha', 'bowl', 'ringbench'];

/**
 * Пропорции на новите форми (етап 4) – огледало в geometry.js и render.js:
 * огнище с плоча: основа = 0,62 × Ø (ширина), отвор за огъня = 0,45 × Ø, панел за надписа = 0,85 × височината;
 * при кръглата основа се вижда плосък участък 0,7 × Ø на основата; барбекю: капак = 0,6 × височината на корпуса.
 */
const PLANCHA_BASE = 0.62;
const PLANCHA_HOLE = 0.45;
const PLANCHA_PANEL_H = 0.85;
const PLANCHA_CHORD = 0.7;
const BBQ_LID = 0.6;
/** Огнище на кръстата стойка: дълбочина на тавата = 0,3 × ширината (най-много 0,6 × общата височина), стойка = 0,8 × ширината */
const CROSS_TRAY = 0.3;
const CROSS_STAND = 0.8;

/**
 * Огнище на кръстата стойка – размери на детайлите в мм. Огледало на crossParts() в geometry.js.
 * Тавата е обърната пресечена пирамида: горе W × W, дъно B × B, дълбочина Ht, стените са наклонени с t = Ht/2 навътре.
 * Стойката: две плочи Ws широки на прорез; горният им ръб е изрязан по профила на тавата (V-образен),
 * дъното на тавата е на височина Hb, а върховете на плочите опират в наклонените стени на височина Hb + rise.
 */
function cross_parts(float $W, float $H): array
{
    $Ht = min(CROSS_TRAY * $W, 0.6 * $H);
    $t = $Ht / 2;
    $B = $W - 2 * $t;
    $Ws = min(0.92 * $W, max(CROSS_STAND * $W, $B + 0.06 * $W));
    $over = max(0.0, $Ws / 2 - $B / 2);
    $rise = $t > 0 ? $over * $Ht / $t : 0.0;
    $Hb = $H - $Ht;
    return ['Ht' => $Ht, 't' => $t, 'B' => $B, 'Ws' => $Ws, 'Hb' => $Hb, 'rise' => $rise, 'plateH' => $Hb + $rise,
        'slant' => sqrt($Ht * $Ht + $t * $t),
        // площ в мм²: тава (дъно + 4 трапеца) и двете плочи (правоъгълник до дъното + двата върха)
        'tray_mm2' => $B * $B + 4 * ($W + $B) / 2 * sqrt($Ht * $Ht + $t * $t),
        'plates_mm2' => 2 * ($Ws * $Hb + $over * $rise)];
}

/**
 * Форми за обекти (v10) – размери на детайлите в мм. Огледало на objParts() в geometry.js и на рендера.
 * bowl (Ø D × обща височина H): купа с дълбочина Hb = min(0,6·R; 0,6·H) на крак Hp = H − Hb; вертикален пояс
 *   по ръба Hc = 0,45·Hb – в него е надписът (видим плосък участък 0,62·D);
 * ringbench (Ø D, височина на пейката H): пейка с ширина 33 см, огнище в средата Ø 2·Rp (Rp = min(43 см; 0,45·Ri)),
 *   1,4 × височината на пейката; надписът е в горната част на огнището (над пейката). Седалката е от дърво – не влиза в теглото и листа;
 * firetable (w × d × h): кутия, плот с отвор r = 0,36·min(w, d), огнена купа в отвора;
 * column (w × w × h): колона с огън горе; надписът е в горната част, орнаментът – под него.
 */
const OBJ_CHORD = 0.62;
const BENCH_SEAT = 330;

function obj_parts(string $shape, float $W, float $D, float $H): array
{
    switch ($shape) {
        case 'bowl':
            $R = $W / 2;
            $Hb = min(0.6 * $R, 0.6 * $H);
            $Hc = 0.45 * $Hb;
            return ['R' => $R, 'Hb' => $Hb, 'Hp' => $H - $Hb, 'Hc' => $Hc, 'chord' => $W * OBJ_CHORD,
                'mm2' => M_PI * $R * $R * 1.15 + M_PI * $W * $Hc + 2 * M_PI * 0.2 * $R * ($H - $Hb) + M_PI * (0.42 * $R) ** 2];
        case 'ringbench':
            $Ro = $W / 2;
            $Ri = max(0.5 * $Ro, $Ro - BENCH_SEAT);
            $Rp = min(430.0, 0.45 * $Ri);
            $Hpit = 1.4 * $H;
            return ['Ro' => $Ro, 'Ri' => $Ri, 'Rp' => $Rp, 'Hb' => $H, 'Hpit' => $Hpit, 'chord' => 2 * $Rp * OBJ_CHORD,
                // само металните части: двете стени на пейката и огнището (стена и дъно)
                'mm2' => 2 * M_PI * ($Ro + $Ri) * $H + 2 * M_PI * $Rp * $Hpit + M_PI * $Rp * $Rp];
        case 'firetable':
            $r = 0.36 * min($W, $D);
            return ['r' => $r, 'mm2' => 2 * ($W + $D) * $H + $W * $D - M_PI * $r * $r + M_PI * $r * $r * 1.3];
        case 'column':
            return ['mm2' => 4 * $W * $H + 2 * $W * $W];
    }
    return [];
}

/** Огнище с пейка-пръстен: от колко сегмента е пейката (обиколката не се побира в един лист) */
function bench_segments(array $m): int
{
    if (($m['shape'] ?? '') !== 'ringbench') return 0;
    $L = max((float)setting('sheet_w_mm', '2970'), (float)setting('sheet_h_mm', '1470'));
    return max(1, (int)ceil(M_PI * $m['w'] * 10 / $L));
}

function shape_is_round(string $shape): bool
{
    return in_array($shape, ROUND_SHAPES, true);
}

function geometry_layout(): array
{
    return [
        'edge_mm' => max(5.0, (float)setting('text_edge_mm', '25')),   // поле от ръба на панела (всички страни)
        'bridge_mm' => max(1.0, (float)setting('bridge_mm', '3')),     // ширина на мостчетата в затворените букви
        'band_plain' => [0.22, 0.80],     // зона за надпис без орнамент (част от височината, от горния ръб)
        'band_orn_text' => [0.50, 0.88],  // зона за надпис, когато има орнамент
        'band_orn' => [0.10, 0.42],       // зона за орнамента
        'line_gap' => 0.25,
        // „Надпис и лого“: логото заема горната част от зоната на надписа, надписът – долната (по-малка буква)
        'logo_share' => 0.5,
        'logo_gap' => 0.06,               // разстояние между двата реда, част от височината на главна буква
        // форми със собствена зона за надписа: постамент – над отвора за дърва; огнище с плоча – горната част на основата, под плочата
        'shape_bands' => ['pedestal' => [0.08, 0.46], 'plancha' => [0.10, 0.50], 'plancha_sq' => [0.10, 0.50],
            // купа – целият пояс по ръба; колона – горната част (орнаментът е под надписа)
            'bowl' => [0.12, 0.88], 'column' => [0.07, 0.30], 'ringbench' => [0.08, 0.45]],
        // форми със собствена зона за орнамента
        'shape_orn' => ['column' => [0.36, 0.93]],
        'max_chars' => TEXT_MAX_CHARS,
        'max_lines' => TEXT_MAX_LINES,
    ];
}

/** Минимална височина на буквата според дебелината на материала (настройки за 2 мм и 3 мм) */
function min_letter_for_thickness(float $t): float
{
    return $t <= 2.5 ? (float)setting('min_letter_2mm', '30') : (float)setting('min_letter_3mm', '35');
}

/** Нормализира надписа: до 2 реда, всеки без излишни интервали. Връща масив с редовете. */
function text_lines(string $t): array
{
    $t = str_replace(["\r\n", "\r"], "\n", $t);
    $lines = [];
    foreach (explode("\n", $t) as $ln) {
        $ln = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $ln) ?? '');
        if ($ln !== '') {
            $lines[] = $ln;
        }
    }
    if (count($lines) > TEXT_MAX_LINES) {
        $lines = [$lines[0], implode(' ', array_slice($lines, 1))];
    }
    return $lines;
}

function normalize_text(string $t): string
{
    return implode("\n", text_lines($t));
}

/** Брой знаци без разделителя между редовете */
function text_length(string $t): int
{
    return mb_strlen(str_replace("\n", '', $t));
}

/** Разделя един ред на два при интервала, най-близък до средата (по брой знаци). null ако няма интервал. */
function split_two_lines(string $line): ?array
{
    $chars = mb_str_split($line);
    $mid = count($chars) / 2;
    $best = null;
    foreach ($chars as $i => $c) {
        if ($c === ' ' && ($best === null || abs($i - $mid) < abs($best - $mid))) {
            $best = $i;
        }
    }
    if ($best === null) {
        return null;
    }
    return [trim(mb_substr($line, 0, $best)), trim(mb_substr($line, $best + 1))];
}

/** Стени на модела в мм: [ширина, височина] в реда отпред, дясно, отзад, ляво */
function model_walls(array $m): array
{
    $h = $m['h'] * 10;
    switch ($m['shape']) {
        case 'hex':
            $s = $m['w'] / 2 * 10;
            return array_fill(0, 6, [$s, $h]);
        case 'octagon':
            // ширината е диаметърът през ъглите; страната е D·sin(22,5°)
            return array_fill(0, 8, [$m['w'] * 10 * sin(M_PI / 8), $h]);
        case 'cross':
            // надписът е на предната наклонена стена на тавата – като при трапецовидното огнище: най-тясната (долна) ширина × дълбочината
            $cp = cross_parts($m['w'] * 10, $h);
            return array_fill(0, 4, [$cp['B'], $cp['Ht']]);
        case 'bowl':
            $bp = obj_parts('bowl', $m['w'] * 10, $m['d'] * 10, $h);
            return [[$bp['chord'], $bp['Hc']]];
        case 'ringbench':
            $rp = obj_parts('ringbench', $m['w'] * 10, $m['d'] * 10, $h);
            return [[$rp['chord'], $rp['Hpit']]];
        case 'ringbowl':
            // пръстенът под купата: Ø = 0,72·Ø купа, височина = 0,55·общата; надписът е в предната му част
            return [[$m['w'] * 10 * 0.72 * 0.62, $h * 0.55]];
        case 'suitcase':
            // мангал куфар: надписът е на капака (ширина × дълбочина)
            return [[$m['w'] * 10, $m['d'] * 10]];
        case 'bbq':
            // барбекю: на капака (ширина × дълбочина) или на предната стена – според избора „Място на надписа“
            if (($m['text_place'] ?? '') === 'lid') {
                return [[$m['w'] * 10, $m['d'] * 10]];
            }
            return [[$m['w'] * 10, $h], [$m['d'] * 10, $h], [$m['w'] * 10, $h], [$m['d'] * 10, $h]];
        case 'plancha':
            // кръгла основа: плосък видим участък отпред
            return [[$m['w'] * 10 * PLANCHA_BASE * PLANCHA_CHORD, $h * PLANCHA_PANEL_H]];
        case 'plancha_sq':
            return [[$m['w'] * 10 * PLANCHA_BASE, $h * PLANCHA_PANEL_H]];
        case 'grill':
        case 'tabletop':
        case 'cheverme':
        case 'insert':
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

/** Вертикалната зона за надписа на стена с височина $wallH, с поле от ръба */
function text_band(float $wallH, bool $hasOrnament, array $L, string $shape = 'box', string $logo = ''): array
{
    $b = $L['shape_bands'][$shape] ?? ($hasOrnament ? $L['band_orn_text'] : $L['band_plain']);
    $top = max($b[0] * $wallH, $L['edge_mm']);
    $bottom = min($b[1] * $wallH, $wallH - $L['edge_mm']);
    // „Надпис и лого“: надписът е под логото – в долната част на зоната (огледало на textBand() в geometry.js)
    if ($logo === 'text') {
        $top = $top + ($bottom - $top) * $L['logo_share'];
    }
    return [$top, max($top + 1, $bottom)];
}

/**
 * Побира надписа (1 или 2 реда) в най-тясната декорирана стена.
 * Размерът се определя от по-дългия ред и от височината на зоната.
 * Връща размер на шрифта, ширини на редовете, височина на буквата (мм) и т.н.; null при невалиден знак.
 */
function text_fit(array $lines, string $fontKey, array $walls, bool $hasOrnament, string $shape = 'box', string $logo = ''): ?array
{
    $font = font_metrics()['fonts'][$fontKey] ?? null;
    if (!$lines || !$font || !$walls) {
        return null;
    }
    $L = geometry_layout();
    $minW = min(array_map(fn($w) => $w[0], $walls));
    $wallH = $walls[0][1];
    $adv = [];
    $yMax = 0;
    $yMin = 0;
    $hasLower = false;
    foreach ($lines as $i => $ln) {
        $adv[$i] = 0;
        foreach (mb_str_split($ln) as $ch) {
            $g = $font['g'][$ch] ?? null;
            if (!$g) {
                return null;
            }
            $adv[$i] += $g[0];
            $yMax = max($yMax, $g[2]);
            $yMin = min($yMin, $g[1]);
            if (mb_strtoupper($ch) !== $ch) {
                $hasLower = true;
            }
        }
    }
    $upm = $font['upm'];
    $n = count($lines);
    $availW = $minW - 2 * $L['edge_mm'];
    [$bt, $bb] = text_band($wallH, $hasOrnament, $L, $shape, $logo);
    $lineH = max(1, $yMax - $yMin);
    $totalH = $n * $lineH + ($n - 1) * $L['line_gap'] * $font['cap'];
    $size = min($availW / (max($adv) / $upm), ($bb - $bt) / ($totalH / $upm));
    $k = $size / $upm;
    return [
        'size_mm' => round($size, 2),
        'widths_mm' => array_map(fn($a) => round($a * $k, 2), $adv),
        'letter_mm' => round(($hasLower ? $font['xh'] : $font['cap']) * $k, 1),
        'cap_mm' => round($font['cap'] * $k, 1),
        'has_lower' => $hasLower,
        'lines' => $n,
    ];
}

/**
 * Ориентир за админа: колко ГЛАВНИ букви се побират на един ред при дадена минимална височина
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
    $availW = $minW - 2 * $L['edge_mm'];
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
        case 'octagon':
            $r = $w / 2;
            return (2 * sqrt(2) * $r * $r + 8 * $w * sin(M_PI / 8) * $h) / 10000;
        case 'trapezoid':
            // дъно w×w + 4 трапеца (горе по-широко с 2·t; t = 0,22·h), наклонена височина
            $t = 0.22 * $h;
            return ($w * $w + 4 * ($w + $t) * sqrt($h * $h + $t * $t)) / 10000;
        case 'pedestal':
            // кутия без дъно + плот + плитка купа Ø = 0,9·w
            $r = 0.45 * $w;
            return (4 * $w * $h + $w * $d + M_PI * $r * $r * 1.25) / 10000;
        case 'cross':
            // тава + две плочи на стойката
            $cp = cross_parts($w * 10, $h * 10);
            return ($cp['tray_mm2'] + $cp['plates_mm2']) / 1000000;
        case 'bowl':
        case 'ringbench':
        case 'firetable':
        case 'column':
            return obj_parts($m['shape'], $w * 10, $d * 10, $h * 10)['mm2'] / 1000000;
        case 'ringbowl':
            $r = $w / 2;
            return (M_PI * $r * $r * 1.3 + M_PI * 0.72 * $w * 0.55 * $h) / 10000;
        case 'suitcase':
            // тава + капак (борд 2 см) + 4 сгъваеми крака 25 × 4 см
            return ($w * $d + 2 * ($w + $d) * $h + $w * $d + 2 * ($w + $d) * 2 + 400) / 10000;
        case 'bbq':
            // корпус + капак (0,6 × височината) + стойка: 4 крака 75 × 8 см
            return ($w * $d + 2 * ($w + $d) * $h + $w * $d + 2 * ($w + $d) * BBQ_LID * $h + 2400) / 10000;
        case 'plancha':
            // кръгла основа + огнена кутия (Ø 0,45, дълбочина 0,3 × височината); плочата се смята отделно (plate_area_m2)
            return (M_PI * PLANCHA_BASE * $w * $h + M_PI * PLANCHA_HOLE * $w * 0.3 * $h + M_PI * (PLANCHA_HOLE * $w / 2) ** 2) / 10000;
        case 'plancha_sq':
            return (4 * PLANCHA_BASE * $w * $h + M_PI * PLANCHA_HOLE * $w * 0.3 * $h + M_PI * (PLANCHA_HOLE * $w / 2) ** 2) / 10000;
        case 'cheverme':
            // корито + 2 стойки (90 × 6 см + стъпка 40 × 6 см) + 4 крачета 15 × 6 см + шиш
            return ($w * $d + 2 * ($w + $d) * $h + 1560 + 360 + 4 * $w) / 10000;
        default:
            return ($w * $d + 2 * ($w + $d) * $h) / 10000;
    }
}

/** Площ на плочата за печене (m²) – пръстен около отвора за огъня; 0 за формите без плоча. Огледало на plateArea() */
function plate_area_m2(array $m): float
{
    $w = $m['w'];
    $hole = M_PI * (PLANCHA_HOLE * $w / 2) ** 2;
    if ($m['shape'] === 'plancha') {
        return (M_PI * ($w / 2) ** 2 - $hole) / 10000;
    }
    if ($m['shape'] === 'plancha_sq') {
        return ($w * $w - $hole) / 10000;
    }
    return 0.0;
}

/* ---------- Размер по избор (етап 3, част В) ---------- */

/** Какви полета има формата: dh – диаметър и височина; wh – квадрат (ширина = дълбочина); wdh – ширина, дълбочина, височина */
function form_dims_mode(string $shape, array $models): string
{
    if (shape_is_round($shape)) {
        return 'dh';
    }
    foreach ($models as $m) {
        if (abs($m['w'] - $m['d']) > 0.01) {
            return 'wdh';
        }
    }
    return 'wh';
}

/**
 * Проверява размер по избор спрямо границите на формата.
 * Връща ['dims' => [w, d, h], 'errors' => [...]] – грешка за всяка стойност извън границите или извън стъпката.
 */
function custom_dims_check(array $form, array $in, string $shape = ''): array
{
    $mode = $form['custom']['mode'];
    $lim = $form['custom'];
    $num = fn($k) => isset($in[$k]) && is_numeric(str_replace(',', '.', (string)$in[$k])) ? (float)str_replace(',', '.', (string)$in[$k]) : null;
    $w = $num('w');
    $d = $mode === 'wdh' ? $num('d') : $w;
    $h = $num('h');
    $err = [];
    // формата може да има кръгли и квадратни размери (огнище с плоча) – етикетът е по избрания размер
    $round = $shape !== '' ? shape_is_round($shape) : $mode === 'dh';
    $label = ['w' => $round ? 'Диаметърът' : 'Ширината', 'd' => 'Дълбочината', 'h' => 'Височината'];
    $check = $mode === 'wdh' ? ['w' => $w, 'd' => $d, 'h' => $h] : ['w' => $w, 'h' => $h]; // при квадрат/кръг дълбочината = ширината
    foreach ($check as $k => $v) {
        if ($v === null) {
            $err[] = $label[$k] . ' липсва.';
            continue;
        }
        if ($v < $lim[$k . '_min'] - 0.01 || $v > $lim[$k . '_max'] + 0.01) {
            $err[] = sprintf('%s трябва да е между %s и %s см.', $label[$k], fmt_num($lim[$k . '_min']), fmt_num($lim[$k . '_max']));
        } elseif (abs(fmod($v - $lim[$k . '_min'], $lim['step'])) > 0.01 && abs(fmod($v - $lim[$k . '_min'], $lim['step']) - $lim['step']) > 0.01) {
            $err[] = sprintf('%s се задава през %s см.', $label[$k], fmt_num($lim['step']));
        }
    }
    if (!$err && $h > $lim['max_h_ratio'] * $w + 0.01) {
        $err[] = sprintf('Височината може да е най-много %s от ширината (до %s см при ширина %s см).', fmt_num($lim['max_h_ratio']), fmt_num(floor($lim['max_h_ratio'] * $w / $lim['step']) * $lim['step']), fmt_num($w));
    }
    return ['dims' => [$w, $d, $h], 'errors' => $err];
}

/**
 * Побиране на плоските детайли в листа (формат от настройките).
 * $construction: 'panels' – сглобява се от отделни панели (всяка стена и дъното поотделно);
 *                'bent'   – корпус, огънат от едно парче (проверява се разгънатата заготовка).
 * Кръглите форми (купа с пръстен) винаги се проверяват по обиколката π × D до дължината на листа.
 * Огледало на sheetFit() в assets/js/geometry.js. Връща ['fits' => bool, 'part' => описание на детайла, който не се побира]
 */
function sheet_fit(array $m, string $construction = 'panels'): array
{
    $sw = (float)setting('sheet_w_mm', '2970');
    $sh = (float)setting('sheet_h_mm', '1470');
    $L = max($sw, $sh);
    $S = min($sw, $sh);
    $fits = fn($a, $b) => ($a <= $L && $b <= $S) || ($a <= $S && $b <= $L);
    $w = $m['w'] * 10;
    $d = $m['d'] * 10;
    $h = $m['h'] * 10;
    $mm = fn($v) => fmt_num(round($v));
    $bent = $construction === 'bent';
    $parts = [];
    switch ($m['shape']) {
        case 'ringbowl':
            $circ = M_PI * 0.72 * $w;
            if ($circ > $L) return ['fits' => false, 'part' => 'пръстен с обиколка ' . $mm($circ) . ' мм (листът е ' . $mm($L) . ' мм)'];
            $parts[] = [$w, $w, 'дъно на купата Ø' . $mm($w) . ' мм'];
            break;
        case 'cross':
            // тава от 4 трапецовидни панела и дъно + две плочи на стойката
            $cp = cross_parts($w, $h);
            $parts[] = [$w, $cp['slant'], 'стена на тавата ' . $mm($w) . '×' . $mm($cp['slant']) . ' мм'];
            $parts[] = [$cp['B'], $cp['B'], 'дъно на тавата ' . $mm($cp['B']) . '×' . $mm($cp['B']) . ' мм'];
            $parts[] = [$cp['Ws'], $cp['plateH'], 'плоча на стойката ' . $mm($cp['Ws']) . '×' . $mm($cp['plateH']) . ' мм'];
            break;
        case 'bowl':
            // купата се изтегля от кръгла заготовка Ø; поясът по ръба е от сегменти (заварени)
            $parts[] = [$w, $w, 'заготовка на купата Ø' . $mm($w) . ' мм'];
            break;
        case 'ringbench':
            // пейката е от сегменти (bench_segments); огнището в средата – валцувано, по обиколката
            $rb = obj_parts('ringbench', $w, $d, $h);
            $pc = 2 * M_PI * $rb['Rp'];
            if ($pc > $L) return ['fits' => false, 'part' => 'огнище с обиколка ' . $mm($pc) . ' мм (листът е ' . $mm($L) . ' мм)'];
            $parts[] = [$pc, $rb['Hpit'], 'стена на огнището ' . $mm($pc) . '×' . $mm($rb['Hpit']) . ' мм'];
            break;
        case 'firetable':
            $parts[] = [$w, $h, 'стена ' . $mm($w) . '×' . $mm($h) . ' мм'];
            $parts[] = [$d, $h, 'стена ' . $mm($d) . '×' . $mm($h) . ' мм'];
            $parts[] = [$w, $d, 'плот ' . $mm($w) . '×' . $mm($d) . ' мм'];
            break;
        case 'column':
            $parts[] = [$w, $h, 'стена ' . $mm($w) . '×' . $mm($h) . ' мм'];
            break;
        case 'plancha':
            // кръгла основа – валцувана, по обиколката; плочата – кръг Ø
            $circ = M_PI * PLANCHA_BASE * $w;
            if ($circ > $L) return ['fits' => false, 'part' => 'основа с обиколка ' . $mm($circ) . ' мм (листът е ' . $mm($L) . ' мм)'];
            $parts[] = [$circ, $h, 'основа ' . $mm($circ) . '×' . $mm($h) . ' мм'];
            $parts[] = [$w, $w, 'плоча Ø' . $mm($w) . ' мм'];
            break;
        case 'plancha_sq':
            $parts[] = [PLANCHA_BASE * $w, $h, 'стена на основата ' . $mm(PLANCHA_BASE * $w) . '×' . $mm($h) . ' мм'];
            $parts[] = [$w, $w, 'плоча ' . $mm($w) . '×' . $mm($w) . ' мм'];
            break;
        case 'hex':
        case 'octagon':
            $n = $m['shape'] === 'hex' ? 6 : 8;
            $side = $m['shape'] === 'hex' ? $w / 2 : $w * sin(M_PI / 8);
            if ($bent) {
                // лента от всички стени, огъната по ръбовете
                $parts[] = [$n * $side, $h, 'разгъната заготовка ' . $mm($n * $side) . '×' . $mm($h) . ' мм'];
            } else {
                $parts[] = [$side, $h, 'стена ' . $mm($side) . '×' . $mm($h) . ' мм'];
            }
            $parts[] = [$w, $w, 'дъно ' . $mm($w) . '×' . $mm($w) . ' мм'];
            break;
        default:
            // правоъгълни: box, trapezoid (горният ръб е по-широк), pedestal, grill
            $wallW = $m['shape'] === 'trapezoid' ? $w + 2 * 0.22 * $h : $w;
            if ($bent) {
                // дъно + две стени от едно парче: (ширина + 2 × височина) по късата страна на листа; предната и задната стена отделно
                if ($w + 2 * $h > $S || $d > $L) {
                    return ['fits' => false, 'part' => 'разгъната заготовка ' . $mm($w + 2 * $h) . ' мм (късата страна на листа е ' . $mm($S) . ' мм)'];
                }
                $parts[] = [$wallW, $h, 'стена ' . $mm($wallW) . '×' . $mm($h) . ' мм'];
            } else {
                $parts[] = [$wallW, $h, 'стена ' . $mm($wallW) . '×' . $mm($h) . ' мм'];
                $parts[] = [$d, $h, 'стена ' . $mm($d) . '×' . $mm($h) . ' мм'];
                $parts[] = [$w, $d, 'дъно ' . $mm($w) . '×' . $mm($d) . ' мм'];
            }
    }
    foreach ($parts as [$a, $b, $label]) {
        if (!$fits($a, $b)) return ['fits' => false, 'part' => $label];
    }
    return ['fits' => true, 'part' => ''];
}
