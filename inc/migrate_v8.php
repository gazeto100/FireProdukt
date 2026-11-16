<?php
declare(strict_types=1);

/**
 * Миграция v8 (етап 4): повече модели скари.
 * - нови форми: настолен мангал, мангал куфар, барбекю с капак, огнище с плоча за печене, стойка за чеверме,
 *   вложка за зидано барбекю (само по мярка);
 * - нови групи опции: „Дебелина на плочата“ (6/8/10 мм) и „Място на надписа“ (предна стена / капак);
 * - нови екстри: мотор за чеверме, шишове 6/10 бр., втора скара на ниво, ръжен и лопатка;
 * - „включени“ екстри: is_default = 1 в групата екстри означава, че екстрата е част от модела (напр. капакът на барбекюто);
 * - forms.custom_only: формата се поръчва само по мярка.
 * Всички размери, тегла и цени са ПРИМЕРНИ и се редактират от админа. Идемпотентна.
 */
function migrate_v8(): void
{
    $pdo = db();
    if (!db_all("SHOW COLUMNS FROM forms LIKE 'custom_only'")) {
        $pdo->exec('ALTER TABLE forms ADD COLUMN custom_only TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_enabled');
    }

    $typeId = fn(string $c) => (int)(db_one('SELECT id FROM product_types WHERE code = ?', [$c])['id'] ?? 0);
    $groupId = fn(string $c) => (int)(db_one('SELECT id FROM option_groups WHERE code = ?', [$c])['id'] ?? 0);
    $optId = fn(string $g, string $c) => (int)(db_one('SELECT o.id FROM options o JOIN option_groups g ON g.id = o.group_id WHERE g.code = ? AND o.code = ?', [$g, $c])['id'] ?? 0);

    // --- нови групи опции ---
    $gIns = $pdo->prepare('INSERT IGNORE INTO option_groups (code, name, hint, multi, required, sort) VALUES (?, ?, ?, 0, 1, ?)');
    $gIns->execute(['plate', 'Дебелина на плочата', 'Плочата е от черна стомана. По-дебелата държи топлината по-равномерно и не се изкривява.', 4]);
    $gIns->execute(['text_place', 'Място на надписа', null, 5]);

    // --- нови опции ---
    $ins = $pdo->prepare('INSERT IGNORE INTO options (group_id, code, name, hint, subgroup, price_type, price_min, price_max, weight_kg, render, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$groupId('plate'), 'plate6', 'Плоча 6 мм', 'Лека, загрява се бързо', null, 'add', 0, 0, 0, '{"plate_mm":6}', 1]);
    $ins->execute([$groupId('plate'), 'plate8', 'Плоча 8 мм', 'Най-често избирана', null, 'add', 60, 80, 0, '{"plate_mm":8}', 2]);
    $ins->execute([$groupId('plate'), 'plate10', 'Плоча 10 мм', 'Най-равномерна топлина', null, 'add', 120, 160, 0, '{"plate_mm":10}', 3]);
    $ins->execute([$groupId('text_place'), 'tp_front', 'На предната стена', 'Вижда се, докато печете', null, 'add', 0, 0, 0, '{"text_place":"front"}', 1]);
    $ins->execute([$groupId('text_place'), 'tp_lid', 'На капака', 'Вижда се при отворен капак', null, 'add', 0, 0, 0, '{"text_place":"lid"}', 2]);
    $ex = $groupId('extras');
    $newExtras = [
        ['cheverme_motor', 'Мотор за чеверме', 'Върти шиша равномерно – 220 V', 'cook', 90, 130, 2.5, ['extra' => 'motor'], 14],
        ['skewers6', 'Комплект шишове – 6 бр.', 'Инокс, с дръжки', 'cook', 25, 35, 1.2, ['extra' => 'skewers', 'count' => 6], 15],
        ['skewers10', 'Комплект шишове – 10 бр.', 'Инокс, с дръжки', 'cook', 38, 50, 2.0, ['extra' => 'skewers', 'count' => 10], 16],
        ['grate2', 'Втора скара на ниво', 'Повдигната скара за топлене и бавно печене', 'cook', 35, 50, 2.5, ['extra' => 'grate2'], 17],
        ['tools', 'Ръжен и лопатка', 'За разбъркване и прибиране на жарта', 'comfort', 25, 35, 1.5, ['extra' => 'tools'], 18],
    ];
    foreach ($newExtras as $e) {
        $ins->execute([$ex, $e[0], $e[1], $e[2], $e[3], 'add', $e[4], $e[5], $e[6], json_encode($e[7]), $e[8]]);
    }

    // --- правила ---
    $rule = $pdo->prepare('INSERT IGNORE INTO option_rules (option_id, kind, target_id) VALUES (?, ?, ?)');
    foreach ([
        [['extras', 'skewers6'], 'excludes', ['extras', 'skewers10']],
        [['extras', 'grate2'], 'requires', ['extras', 'grate']],
    ] as $r) {
        $a = $optId(...$r[0]);
        $b = $optId(...$r[2]);
        if ($a && $b) $rule->execute([$a, $r[1], $b]);
    }

    // --- нови форми (вид „Скара / мангал“) ---
    require_once __DIR__ . '/migrate_v6.php';
    $defs = form_custom_defaults();
    $grill = $typeId('grill');
    $forms = [
        // код, име, форма, описание, ред, размер по избор, само по мярка
        ['tabletop', 'Настолен мангал', 'tabletop', 'Малък мангал на ниски крачета – за маса, тераса или балкон.', 1, 1, 0],
        ['suitcase', 'Мангал куфар', 'suitcase', 'Сгъва се като куфар с дръжка, краката се разгъват. Надписът е на капака.', 4, 0, 0],
        ['bbq', 'Барбекю с капак', 'bbq', 'На стойка, с капак на панти – за печене и на бавен огън.', 7, 1, 0],
        ['plancha', 'Огнище с плоча за печене', 'plancha', 'Огън в средата и широка плоча около него – печете и се топлите едновременно.', 9, 1, 0],
        ['cheverme', 'Стойка за чеверме', 'cheverme', 'Корито за жар и две стойки с нива за височината на шиша.', 8, 1, 0],
        ['insert', 'Вложка за зидано барбекю', 'insert', 'Тава за жар и скара за вграждане в тухлено барбекю. Изработва се по мярка.', 3, 1, 1],
    ];
    $fIns = $pdo->prepare('INSERT IGNORE INTO forms (code, type_id, name, shape, description, sort, active, custom_enabled, custom_only, w_min, w_max, d_min, d_max, h_min, h_max, step_cm, max_h_ratio, custom_fixed)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($forms as $f) {
        $d = $defs[$f[2]];
        $fIns->execute([$f[0], $grill, $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $f[0] === 'insert' ? 1 : 5, $d[6], $d[7]]);
    }
    // ред на картите от най-малката към най-голямата (по най-малкия размер на формата)
    foreach (['tabletop' => 1, 'picnic' => 2, 'insert' => 3, 'suitcase' => 4, 'mangal' => 5, 'stand' => 6, 'bbq' => 7, 'cheverme' => 8, 'plancha' => 9] as $c => $sort) {
        $pdo->prepare('UPDATE forms SET sort = ? WHERE code = ?')->execute([$sort, $c]);
    }

    // --- нови модели ---
    $models = [
        // код, форма, име, надпис на бутона, форма за рисуване, ш, д, в, цена от, до, ред
        ['tabletop30', 'tabletop', 'Настолен мангал 30×20', '30×20', 'tabletop', 30, 20, 10, 70, 95, 30],
        ['tabletop40', 'tabletop', 'Настолен мангал 40×25', '40×25', 'tabletop', 40, 25, 12, 90, 120, 31],
        ['suitcase45', 'suitcase', 'Мангал куфар 45×30', '45×30', 'suitcase', 45, 30, 10, 130, 170, 32],
        ['suitcase60', 'suitcase', 'Мангал куфар 60×35', '60×35', 'suitcase', 60, 35, 12, 170, 220, 33],
        ['bbq60', 'bbq', 'Барбекю с капак 60×40', '60×40', 'bbq', 60, 40, 22, 420, 540, 34],
        ['bbq80', 'bbq', 'Барбекю с капак 80×45', '80×45', 'bbq', 80, 45, 24, 520, 660, 35],
        ['plancha80', 'plancha', 'Огнище с плоча Ø80', 'Ø80', 'plancha', 80, 80, 90, 690, 850, 36],
        ['plancha100', 'plancha', 'Огнище с плоча Ø100', 'Ø100', 'plancha', 100, 100, 90, 890, 1090, 37],
        ['plancha_sq80', 'plancha', 'Огнище с плоча 80×80', '80×80', 'plancha_sq', 80, 80, 90, 720, 890, 38],
        ['cheverme100', 'cheverme', 'Стойка за чеверме 100×40', '100×40', 'cheverme', 100, 40, 20, 260, 340, 39],
        ['cheverme120', 'cheverme', 'Стойка за чеверме 120×40', '120×40', 'cheverme', 120, 40, 20, 300, 390, 40],
        // вложката е само по мярка: този ред е основата (опции и начален размер), цената се смята по площ
        ['insert_base', 'insert', 'Вложка за зидано барбекю', 'по мярка', 'insert', 60, 40, 10, 150, 200, 41],
    ];
    $mIns = $pdo->prepare('INSERT IGNORE INTO models (type_id, form_id, code, name, size_label, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, sort)
        SELECT type_id, id, ?, ?, ?, ?, ?, ?, ?, 40, ?, ?, ? FROM forms WHERE code = ?');
    foreach ($models as $m) {
        $mIns->execute([$m[0], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7], $m[8], $m[9], $m[10], $m[1]]);
    }

    // --- съвместимост ---
    $fonts = ['russo', 'oswald', 'dela'];
    $orn = ['none', 'flames', 'mountains', 'pines', 'shevitsa', 'perf_dense', 'perf_sparse'];
    $inox = ['inox2', 'inox3', 'black3'];
    $sk = ['skewers6', 'skewers10'];
    $compat = [
        'tabletop' => ['material' => $inox, 'font' => $fonts, 'sides' => ['s1', 's2'], 'ornament' => $orn,
            'extras' => array_merge(['grate', 'griddle', 'bag'], $sk, ['tools'])],
        'suitcase' => ['material' => $inox, 'font' => $fonts, 'sides' => ['s1'], 'ornament' => $orn,
            'extras' => array_merge(['grate', 'griddle', 'hooks'], $sk, ['tools'])],
        'bbq' => ['material' => $inox, 'font' => $fonts, 'legs' => ['high'], 'sides' => ['s1'], 'text_place' => ['tp_front', 'tp_lid'], 'ornament' => $orn,
            'extras' => array_merge(['grate', 'lid', 'thermometer', 'grate2', 'griddle', 'side_table', 'side_table_left', 'hooks', 'ash_pan', 'wheels'], $sk, ['tools'])],
        'plancha' => ['material' => ['black3', 'corten'], 'plate' => ['plate6', 'plate8', 'plate10'], 'font' => $fonts, 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'],
            'extras' => ['grate', 'hooks', 'tools']],
        'plancha_sq' => ['material' => ['black3', 'corten'], 'plate' => ['plate6', 'plate8', 'plate10'], 'font' => $fonts, 'sides' => ['s1'], 'ornament' => $orn,
            'extras' => ['grate', 'hooks', 'tools']],
        'cheverme' => ['material' => $inox, 'font' => $fonts, 'sides' => ['s1', 's2'], 'ornament' => $orn,
            'extras' => array_merge(['cheverme_motor'], $sk, ['tools'])],
        'insert' => ['material' => $inox, 'font' => $fonts, 'sides' => ['s1'], 'ornament' => ['none'],
            'extras' => array_merge(['grate', 'grate2', 'griddle', 'ash_pan'], $sk, ['tools'])],
    ];
    // подразбиращи се: материал (огнището с плоча – черна стомана), плоча 6 мм, надпис отпред;
    // в екстрите „по подразбиране“ = включено в модела (капакът и скарата на барбекюто, скарата на вложката)
    $defaults = ['material' => 'inox2', 'legs' => 'high', 'font' => 'russo', 'sides' => 's1', 'ornament' => 'none', 'plate' => 'plate6', 'text_place' => 'tp_front'];
    $included = ['bbq' => ['grate', 'lid'], 'insert' => ['grate']];
    $moIns = $pdo->prepare('INSERT IGNORE INTO model_options (model_id, option_id, is_default, price_min, price_max) VALUES (?, ?, ?, ?, ?)');
    foreach ($models as $m) {
        $mid = (int)(db_one('SELECT id FROM models WHERE code = ?', [$m[0]])['id'] ?? 0);
        if (!$mid || db_one('SELECT 1 x FROM model_options WHERE model_id = ? LIMIT 1', [$mid])) {
            continue; // вече настроен (напр. от админа)
        }
        $shape = $m[4];
        foreach ($compat[$shape] as $g => $codes) {
            foreach ($codes as $c) {
                $oid = $optId($g, $c);
                if (!$oid) continue;
                $pMin = $pMax = null;
                if ($g === 'extras') {
                    $def = in_array($c, $included[$shape] ?? [], true);
                    if ($def) $pMin = $pMax = 0; // включено в цената на модела
                } else {
                    $def = $g === 'material' && str_starts_with($shape, 'plancha') ? $c === 'black3' : $defaults[$g] === $c;
                    if ($g === 'legs') $pMin = $pMax = 0; // стойката е част от барбекюто
                }
                // по-голямата плоча е по-скъпа
                if ($m[0] === 'plancha100' && $c === 'plate8') { $pMin = 90; $pMax = 120; }
                if ($m[0] === 'plancha100' && $c === 'plate10') { $pMin = 180; $pMax = 240; }
                $moIns->execute([$mid, $oid, $def ? 1 : 0, $pMin, $pMax]);
            }
        }
    }

    // огнището с плоча е от черна стомана по подразбиране: базовата цена е за черна стомана (коефициент 1), кортенът е по-скъп
    $pdo->exec("UPDATE model_options mo JOIN models m ON m.id = mo.model_id JOIN options o ON o.id = mo.option_id JOIN option_groups g ON g.id = o.group_id
        SET mo.price_min = IF(o.code = 'black3', 1, 1.15), mo.price_max = IF(o.code = 'black3', 1, 1.2)
        WHERE g.code = 'material' AND m.shape IN ('plancha', 'plancha_sq') AND mo.price_min IS NULL");

    // --- новите екстри към съществуващите мангали ---
    $add = [
        'mangal_picnic' => array_merge($sk, ['tools']),
        'mangal_std' => array_merge($sk, ['grate2', 'tools']),
        'mangal60' => array_merge($sk, ['grate2', 'tools']),
        'mangal80' => array_merge($sk, ['grate2', 'tools']),
        'grill_stand' => array_merge($sk, ['grate2', 'tools']),
        'stand80' => array_merge($sk, ['grate2', 'tools']),
    ];
    $moAdd = $pdo->prepare('INSERT IGNORE INTO model_options (model_id, option_id, is_default) VALUES (?, ?, 0)');
    foreach ($add as $mc => $codes) {
        $mid = (int)(db_one('SELECT id FROM models WHERE code = ?', [$mc])['id'] ?? 0);
        if (!$mid) continue;
        foreach ($codes as $c) {
            if ($oid = $optId('extras', $c)) $moAdd->execute([$mid, $oid]);
        }
    }
}
