<?php
declare(strict_types=1);

/**
 * Миграция v10: моделите „За хотели и обекти“ се конфигурират.
 * - product_types.is_project: видът е „по проект“ – цена по оферта (не се показва), последната стъпка е проектната форма;
 * - нов вид „За хотели и обекти“ с 4 форми от шоурума: кръгла купа, огнище маса на дърва, огнище с пейка-пръстен, висока колона;
 * - нова екстра „Искрогасител“ (изключва се с капака);
 * Запитване от конфигуратора при вид „по проект“: kind = 'project', config_json = пълната конфигурация + обект, срок, лого.
 * Всички размери, тегла и цени са ПРИМЕРНИ и се редактират от админа. Идемпотентна.
 */
function migrate_v10(): void
{
    $pdo = db();
    if (!db_all("SHOW COLUMNS FROM product_types LIKE 'is_project'")) {
        $pdo->exec('ALTER TABLE product_types ADD COLUMN is_project TINYINT(1) NOT NULL DEFAULT 0 AFTER hint');
    }
    $pdo->prepare('INSERT IGNORE INTO product_types (code, name, hint, is_project, sort, active) VALUES (?, ?, ?, 1, 3, 1)')
        ->execute(['object', 'За хотели и обекти', 'Огнища по проект за ресторанти, хотели и къщи за гости']);

    $typeId = (int)(db_one("SELECT id FROM product_types WHERE code = 'object'")['id'] ?? 0);
    $groupId = fn(string $c) => (int)(db_one('SELECT id FROM option_groups WHERE code = ?', [$c])['id'] ?? 0);
    $optId = fn(string $g, string $c) => (int)(db_one('SELECT o.id FROM options o JOIN option_groups g ON g.id = o.group_id WHERE g.code = ? AND o.code = ?', [$g, $c])['id'] ?? 0);

    // --- искрогасител ---
    $pdo->prepare('INSERT IGNORE INTO options (group_id, code, name, hint, subgroup, price_type, price_min, price_max, weight_kg, render, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$groupId('extras'), 'spark_guard', 'Искрогасител', 'Препоръчва се за места с гости.', 'comfort', 'add', 60, 90, 4, '{"extra":"spark_guard"}', 19]);
    $a = $optId('extras', 'spark_guard');
    $b = $optId('extras', 'lid');
    if ($a && $b) {
        $pdo->prepare('INSERT IGNORE INTO option_rules (option_id, kind, target_id) VALUES (?, ?, ?)')->execute([$a, 'excludes', $b]);
    }

    // поставката за дърва вече е и за обекти (там е отстрани): общо описание вместо „под корпуса, само с високи крачета“
    $pdo->prepare("UPDATE options SET hint = ? WHERE code = 'log_rack' AND hint = ?")
        ->execute(['Дървата са винаги подръка', 'Рафт за дърва под корпуса (само с високи крачета)']);

    // --- форми ---
    require_once __DIR__ . '/migrate_v6.php';
    $defs = form_custom_defaults();
    $forms = [
        // код, име, форма, описание, ред, стъпка
        ['bowl', 'Кръгла купа', 'bowl', 'За тераса на ресторант или вътрешен двор – огънят се вижда отвсякъде. Името на обекта е изрязано в пояса на купата.', 1, 5],
        ['firetable', 'Огнище маса на дърва', 'firetable', 'Ниска маса с вградена огнена купа за дърва в плота – за лаундж зони и тераси.', 2, 5],
        ['ringbench', 'Огнище с пейка-пръстен', 'ringbench', 'Кръгла пейка около огнището – място за 10–12 души. Изработва се по проект.', 3, 10],
        ['column', 'Висока колона', 'column', 'Огън на височина и името или логото на обекта, изрязани и осветени отвътре – за входове и алеи.', 4, 5],
    ];
    $fIns = $pdo->prepare('INSERT IGNORE INTO forms (code, type_id, name, shape, description, sort, active, custom_enabled, custom_only, w_min, w_max, d_min, d_max, h_min, h_max, step_cm, max_h_ratio, custom_fixed)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($forms as $f) {
        $d = $defs[$f[2]];
        $fIns->execute([$f[0], $typeId, $f[1], $f[2], $f[3], $f[4], $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $f[5], $d[6], $d[7]]);
    }

    // --- модели (размерите от шоурума) ---
    $models = [
        // код, форма, име, надпис на бутона, форма за рисуване, ш, д, в, цена от, до (само за вътрешна ориентация), ред
        ['bowl100', 'bowl', 'Кръгла купа Ø100', 'Ø100', 'bowl', 100, 100, 55, 900, 1200, 50],
        ['bowl120', 'bowl', 'Кръгла купа Ø120', 'Ø120', 'bowl', 120, 120, 60, 1200, 1550, 51],
        ['firetable160', 'firetable', 'Огнище маса на дърва 160×90', '160×90', 'firetable', 160, 90, 45, 1900, 2500, 52],
        ['ringbench260', 'ringbench', 'Огнище с пейка-пръстен Ø260', 'Ø260', 'ringbench', 260, 260, 45, 4200, 5600, 53],
        ['column40', 'column', 'Висока колона 40×40×120', '40×40×120', 'column', 40, 40, 120, 750, 980, 54],
    ];
    $mIns = $pdo->prepare('INSERT IGNORE INTO models (type_id, form_id, code, name, size_label, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, sort)
        SELECT type_id, id, ?, ?, ?, ?, ?, ?, ?, 40, ?, ?, ? FROM forms WHERE code = ?');
    foreach ($models as $m) {
        $mIns->execute([$m[0], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7], $m[8], $m[9], $m[10], $m[1]]);
    }

    // --- съвместимост ---
    $fonts = ['russo', 'oswald', 'dela'];
    $mats = ['inox2', 'inox3', 'black3', 'corten'];
    $ornAll = ['none', 'flames', 'mountains', 'pines', 'shevitsa', 'perf_dense', 'perf_sparse'];
    $ornPerf = ['none', 'perf_dense', 'perf_sparse'];
    $compat = [
        'bowl' => ['material' => $mats, 'font' => $fonts, 'sides' => ['s1'], 'ornament' => $ornPerf, 'extras' => ['spark_guard', 'lid', 'log_rack']],
        'firetable' => ['material' => $mats, 'font' => $fonts, 'sides' => ['s1', 's2', 'sall'], 'ornament' => $ornAll, 'extras' => ['spark_guard', 'lid', 'log_rack']],
        'ringbench' => ['material' => $mats, 'font' => $fonts, 'sides' => ['s1'], 'ornament' => $ornPerf, 'extras' => ['spark_guard', 'lid']],
        'column' => ['material' => $mats, 'font' => $fonts, 'sides' => ['s1', 's2', 'sall'], 'ornament' => $ornAll, 'extras' => ['spark_guard', 'lid', 'log_rack']],
    ];
    // материал по подразбиране: купата, масата – черна стомана 3 мм; пейката – кортен; колоната – инокс 3 мм
    $defMat = ['bowl' => 'black3', 'firetable' => 'black3', 'ringbench' => 'corten', 'column' => 'inox3'];
    $defaults = ['font' => 'russo', 'sides' => 's1', 'ornament' => 'none'];
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
                if ($g === 'material') {
                    $def = $c === $defMat[$shape];
                    // базовата цена е за материала по подразбиране (коефициент 1), останалите – спрямо него
                    $f = ['black3' => [1, 1], 'inox2' => [1.15, 1.2], 'inox3' => [1.35, 1.45], 'corten' => [1.25, 1.35]][$c];
                    if ($defMat[$shape] === 'corten') $f = ['corten' => [1, 1], 'black3' => [0.8, 0.85], 'inox2' => [0.9, 0.95], 'inox3' => [1.05, 1.1]][$c];
                    if ($defMat[$shape] === 'inox3') $f = ['inox3' => [1, 1], 'inox2' => [0.85, 0.9], 'black3' => [0.75, 0.8], 'corten' => [0.95, 1]][$c];
                    [$pMin, $pMax] = $f;
                } elseif ($g === 'extras') {
                    $def = false;
                } else {
                    $def = ($defaults[$g] ?? '') === $c;
                }
                $moIns->execute([$mid, $oid, $def ? 1 : 0, $pMin, $pMax]);
            }
        }
    }
}
