<?php
declare(strict_types=1);

/**
 * Геометрия и разположение на надписа.
 * ВАЖНО: същата логика е в assets/js/geometry.js – при промяна сменете и двете.
 * Константите (вкл. настройките от админа) се подават на браузъра от тук, за да няма разминаване.
 */
const TEXT_MAX_CHARS = 40;   // технически таван срещу злоупотреба (не се показва на клиента)
const TEXT_MAX_LINES = 2;

function geometry_layout(): array
{
    return [
        'edge_mm' => max(5.0, (float)setting('text_edge_mm', '25')),   // поле от ръба на панела (всички страни)
        'bridge_mm' => max(1.0, (float)setting('bridge_mm', '3')),     // ширина на мостчетата в затворените букви
        'band_plain' => [0.22, 0.80],     // зона за надпис без орнамент (част от височината, от горния ръб)
        'band_orn_text' => [0.50, 0.88],  // зона за надпис, когато има орнамент
        'band_orn' => [0.10, 0.42],       // зона за орнамента
        'line_gap' => 0.25,               // разстояние между двата реда, част от височината на главна буква
        // форми със собствена зона за надписа: постамент – над отвора за дърва; кръстато – горната част на плочата
        'shape_bands' => ['pedestal' => [0.08, 0.46], 'cross' => [0.10, 0.46]],
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
            // надписът е на едната половина на предната плоча (до прореза)
            return [[$m['w'] * 10 / 2, $h]];
        case 'ringbowl':
            // пръстенът под купата: Ø = 0,72·Ø купа, височина = 0,55·общата; надписът е в предната му част
            return [[$m['w'] * 10 * 0.72 * 0.62, $h * 0.55]];
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

/** Вертикалната зона за надписа на стена с височина $wallH, с поле от ръба */
function text_band(float $wallH, bool $hasOrnament, array $L, string $shape = 'box'): array
{
    $b = $L['shape_bands'][$shape] ?? ($hasOrnament ? $L['band_orn_text'] : $L['band_plain']);
    $top = max($b[0] * $wallH, $L['edge_mm']);
    $bottom = min($b[1] * $wallH, $wallH - $L['edge_mm']);
    return [$top, max($top + 1, $bottom)];
}

/**
 * Побира надписа (1 или 2 реда) в най-тясната декорирана стена.
 * Размерът се определя от по-дългия ред и от височината на зоната.
 * Връща размер на шрифта, ширини на редовете, височина на буквата (мм) и т.н.; null при невалиден знак.
 */
function text_fit(array $lines, string $fontKey, array $walls, bool $hasOrnament, string $shape = 'box'): ?array
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
    [$bt, $bb] = text_band($wallH, $hasOrnament, $L, $shape);
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
            return 2 * $w * $h / 10000;
        case 'ringbowl':
            $r = $w / 2;
            return (M_PI * $r * $r * 1.3 + M_PI * 0.72 * $w * 0.55 * $h) / 10000;
        default:
            return ($w * $d + 2 * ($w + $d) * $h) / 10000;
    }
}

/* ---------- Размер по избор (етап 3, част В) ---------- */

/** Какви полета има формата: dh – диаметър и височина; wh – квадрат (ширина = дълбочина); wdh – ширина, дълбочина, височина */
function form_dims_mode(string $shape, array $models): string
{
    if (in_array($shape, ['hex', 'octagon', 'cross', 'ringbowl'], true)) {
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
function custom_dims_check(array $form, array $in): array
{
    $mode = $form['custom']['mode'];
    $lim = $form['custom'];
    $num = fn($k) => isset($in[$k]) && is_numeric(str_replace(',', '.', (string)$in[$k])) ? (float)str_replace(',', '.', (string)$in[$k]) : null;
    $w = $num('w');
    $d = $mode === 'wdh' ? $num('d') : $w;
    $h = $num('h');
    $err = [];
    $label = ['w' => $mode === 'dh' ? 'Диаметърът' : 'Ширината', 'd' => 'Дълбочината', 'h' => 'Височината'];
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
            $parts[] = [$w, $h, 'плоча ' . $mm($w) . '×' . $mm($h) . ' мм'];
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
