<?php
declare(strict_types=1);

/**
 * Миграция v5 (етап 3, част Б): форми, размери, нови форми и размери, перфорация, кортен,
 * нови екстри с подгрупи и правила (изключват се / изискват).
 * Пуска се и при нова инсталация (seed.sql е до v4), така че съдържанието е на едно място.
 * Всички стойности (цени, тегла, размери) са ПРИМЕРНИ и се редактират от админа.
 * Идемпотентна: може да се пусне повторно, без да дублира.
 */
function migrate_v5(): void
{
    $pdo = db();
    // --- структура ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS forms (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, type_id INT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL, shape VARCHAR(16) NOT NULL DEFAULT 'box', description VARCHAR(500) NULL,
        sort INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
        CONSTRAINT fk_forms_type FOREIGN KEY (type_id) REFERENCES product_types(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!db_all("SHOW COLUMNS FROM models LIKE 'form_id'")) {
        $pdo->exec('ALTER TABLE models ADD COLUMN form_id INT UNSIGNED NULL AFTER type_id, ADD COLUMN size_label VARCHAR(40) NULL AFTER name');
    }
    if (!db_all("SHOW COLUMNS FROM options LIKE 'subgroup'")) {
        $pdo->exec("ALTER TABLE options ADD COLUMN subgroup VARCHAR(16) NULL AFTER hint");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS option_rules (
        option_id INT UNSIGNED NOT NULL, kind VARCHAR(10) NOT NULL, target_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (option_id, kind, target_id),
        CONSTRAINT fk_rule_opt FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE,
        CONSTRAINT fk_rule_target FOREIGN KEY (target_id) REFERENCES options(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $typeId = fn(string $c) => (int)(db_one('SELECT id FROM product_types WHERE code = ?', [$c])['id'] ?? 0);
    $groupId = fn(string $c) => (int)(db_one('SELECT id FROM option_groups WHERE code = ?', [$c])['id'] ?? 0);
    $optId = fn(string $g, string $c) => (int)(db_one('SELECT o.id FROM options o JOIN option_groups g ON g.id = o.group_id WHERE g.code = ? AND o.code = ?', [$g, $c])['id'] ?? 0);

    // --- форми (стандартните за каталога) ---
    $forms = [
        ['sq', 'firepit', 'Огнище квадратно', 'box', 1, 'Класическо квадратно огнище.'],
        ['hex', 'firepit', 'Огнище шестоъгълно', 'hex', 2, 'Шест стени за надпис и орнамент.'],
        ['oct', 'firepit', 'Огнище осмоъгълно', 'octagon', 3, 'Осем стени – почти кръгло, огънят се вижда отвсякъде.'],
        ['trap', 'firepit', 'Огнище трапецовидно', 'trapezoid', 4, 'Стени, наклонени навън – повече топлина към седящите.'],
        ['ped', 'firepit', 'Огнище на постамент', 'pedestal', 5, 'Плитка купа върху кутия с отвор за дърва.'],
        ['cross', 'firepit', 'Кръстато сглобяемо огнище', 'cross', 6, 'Две плочи на прорези – сглобява се без инструменти.'],
        ['ringbowl', 'firepit', 'Кръгла купа с пръстен', 'ringbowl', 7, 'Купа върху пръстен с изрязан надпис.'],
        ['picnic', 'grill', 'Мангал за пикник', 'grill', 1, 'Лек мангал, който се пренася в калъф.'],
        ['mangal', 'grill', 'Мангал', 'grill', 2, 'Класически мангал за скара на дървени въглища.'],
        ['stand', 'grill', 'Скара на стойка', 'grill', 3, 'Скара на висока стойка – печете прав.'],
    ];
    $st = $pdo->prepare('INSERT IGNORE INTO forms (code, type_id, name, shape, sort, description) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($forms as $f) {
        $st->execute([$f[0], $typeId($f[1]), $f[2], $f[3], $f[4], $f[5]]);
    }
    $formId = fn(string $c) => (int)(db_one('SELECT id FROM forms WHERE code = ?', [$c])['id'] ?? 0);

    // --- автоматично групиране на съществуващите модели по форма (по име без размера) ---
    foreach (db_all('SELECT id, type_id, name, shape, width_cm, depth_cm, height_cm FROM models WHERE form_id IS NULL') as $m) {
        [$base, $size] = model_name_split((string)$m['name']);
        $f = db_one('SELECT id FROM forms WHERE type_id = ? AND name = ?', [$m['type_id'], $base]);
        if (!$f) {
            $code = 'f' . substr(md5($base . $m['type_id']), 0, 8);
            $pdo->prepare('INSERT IGNORE INTO forms (code, type_id, name, shape, sort) VALUES (?, ?, ?, ?, 50)')->execute([$code, $m['type_id'], $base, $m['shape']]);
            $f = db_one('SELECT id FROM forms WHERE code = ?', [$code]);
        }
        $label = $size !== '' ? $size : model_size_text($m);
        if (preg_match('/^(S|M|L|XL)$/u', $label)) {
            $label .= ' · ' . model_size_text($m);
        }
        $pdo->prepare('UPDATE models SET form_id = ?, size_label = ? WHERE id = ?')->execute([(int)$f['id'], $label, $m['id']]);
    }

    // --- нови опции ---
    $ins = $pdo->prepare('INSERT IGNORE INTO options (group_id, code, name, hint, subgroup, price_type, price_min, price_max, weight_kg, render, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$groupId('material'), 'corten', 'Кортен 3 мм', 'Патинираща стомана – ръждив цвят, без боя', null, 'factor', 1.35, 1.45, 0, '{"finish":"corten","thickness_mm":3,"density":7.85}', 4]);
    $ins->execute([$groupId('ornament'), 'perf_dense', 'Перфорация гъста', null, null, 'add', 20, 30, 0, '{"pattern":"perf","density":"dense","per_side":true}', 6]);
    $ins->execute([$groupId('ornament'), 'perf_sparse', 'Перфорация рядка', null, null, 'add', 15, 25, 0, '{"pattern":"perf","density":"sparse","per_side":true}', 7]);
    $ex = $groupId('extras');
    $newExtras = [
        ['griddle', 'Плоча за печене', 'Дебела стоманена плоча върху половината отвор', 'cook', 35, 50, 4.0, 'griddle', 6],
        ['thermometer', 'Термометър на капака', 'Само с капак', 'cook', 20, 30, 0.2, 'thermometer', 7],
        ['side_table_left', 'Страничен плот вляво', 'Място за чинии и прибори', 'comfort', 40, 60, 3.5, 'side_table_left', 8],
        ['hooks', 'Куки за прибори', 'Три куки отстрани', 'comfort', 12, 18, 0.3, 'hooks', 9],
        ['ash_pan', 'Пепелник', 'Чекмедже за пепелта отдолу', 'comfort', 25, 35, 1.5, 'ash_pan', 10],
        ['wind_guard', 'Ветробран', 'Стени срещу вятъра отзад и отстрани', 'comfort', 45, 65, 3.5, 'wind_guard', 11],
        ['log_rack', 'Поставка за дърва', 'Рафт за дърва под корпуса (само с високи крачета)', 'comfort', 40, 55, 3.0, 'log_rack', 12],
        ['wheels', 'Колела', 'Две колела за лесно местене', 'carry', 30, 45, 2.0, 'wheels', 13],
    ];
    foreach ($newExtras as $e) {
        $ins->execute([$ex, $e[0], $e[1], $e[2], $e[3], 'add', $e[4], $e[5], $e[6], json_encode(['extra' => $e[7]]), $e[8]]);
    }
    // подгрупи за съществуващите екстри
    $sub = ['grate' => 'cook', 'arm' => 'cook', 'lid' => 'cook', 'side_table' => 'comfort', 'bag' => 'carry'];
    foreach ($sub as $c => $g) {
        $pdo->prepare('UPDATE options SET subgroup = ? WHERE group_id = ? AND code = ? AND subgroup IS NULL')->execute([$g, $ex, $c]);
    }
    $pdo->prepare("UPDATE options SET name = 'Страничен плот вдясно' WHERE group_id = ? AND code = 'side_table' AND name = 'Страничен плот'")->execute([$ex]);

    // --- правила между екстрите ---
    $rule = $pdo->prepare('INSERT IGNORE INTO option_rules (option_id, kind, target_id) VALUES (?, ?, ?)');
    $rules = [
        [['extras', 'wind_guard'], 'excludes', ['extras', 'lid']],
        [['extras', 'arm'], 'excludes', ['extras', 'lid']],
        [['extras', 'thermometer'], 'requires', ['extras', 'lid']],
        [['extras', 'log_rack'], 'requires', ['legs', 'high']],
        [['extras', 'wheels'], 'requires', ['legs', 'high']],
    ];
    foreach ($rules as $r) {
        $a = $optId(...$r[0]);
        $b = $optId(...$r[2]);
        if ($a && $b) $rule->execute([$a, $r[1], $b]);
    }

    // --- нови модели (стандартни размери) ---
    $models = [
        // код, форма, име, размер, форма за рисуване, ш, д, в, цена от, до, ред
        ['trap50', 'trap', 'Огнище трапецовидно 50', '50 см', 'trapezoid', 50, 50, 35, 300, 380, 10],
        ['trap70', 'trap', 'Огнище трапецовидно 70', '70 см', 'trapezoid', 70, 70, 45, 420, 520, 11],
        ['ped56', 'ped', 'Огнище на постамент 56', '56 см', 'pedestal', 56, 56, 55, 380, 480, 12],
        ['ped70', 'ped', 'Огнище на постамент 70', '70 см', 'pedestal', 70, 70, 65, 480, 600, 13],
        ['cross60', 'cross', 'Кръстато огнище Ø60', 'Ø60', 'cross', 60, 60, 40, 180, 240, 14],
        ['oct60', 'oct', 'Огнище осмоъгълно Ø60', 'Ø60', 'octagon', 60, 60, 35, 360, 460, 15],
        ['oct80', 'oct', 'Огнище осмоъгълно Ø80', 'Ø80', 'octagon', 80, 80, 40, 480, 600, 16],
        ['ringbowl60', 'ringbowl', 'Кръгла купа с пръстен Ø60', 'Ø60', 'ringbowl', 60, 60, 45, 320, 420, 17],
        ['ringbowl80', 'ringbowl', 'Кръгла купа с пръстен Ø80', 'Ø80', 'ringbowl', 80, 80, 50, 440, 560, 18],
        ['mangal60', 'mangal', 'Мангал 60×30', '60×30', 'grill', 60, 30, 15, 140, 185, 20],
        ['mangal80', 'mangal', 'Мангал 80×35', '80×35', 'grill', 80, 35, 16, 180, 235, 21],
        ['stand80', 'stand', 'Скара на стойка 80×40', '80×40', 'grill', 80, 40, 16, 320, 410, 22],
    ];
    $mIns = $pdo->prepare('INSERT IGNORE INTO models (type_id, form_id, code, name, size_label, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, sort)
        SELECT type_id, id, ?, ?, ?, ?, ?, ?, ?, 40, ?, ?, ? FROM forms WHERE code = ?');
    foreach ($models as $m) {
        $mIns->execute([$m[0], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7], $m[8], $m[9], $m[10], $m[1]]);
    }

    // --- съвместимост на новите модели (по подразбиране черна стомана за новите огнища) ---
    $fireAll = ['material' => ['inox2', 'inox3', 'black3', 'corten'], 'font' => ['russo', 'oswald', 'dela']];
    $orn = ['none', 'flames', 'mountains', 'pines', 'shevitsa', 'perf_dense', 'perf_sparse'];
    $compat = [
        'trap50' => $fireAll + ['legs' => ['none', 'low'], 'sides' => ['s1'], 'ornament' => $orn, 'extras' => ['grate', 'griddle', 'lid']],
        'trap70' => $fireAll + ['legs' => ['none', 'low'], 'sides' => ['s1'], 'ornament' => $orn, 'extras' => ['grate', 'griddle', 'lid']],
        'ped56' => $fireAll + ['legs' => ['none'], 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'], 'extras' => ['grate', 'griddle']],
        'ped70' => $fireAll + ['legs' => ['none'], 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'], 'extras' => ['grate', 'griddle']],
        'cross60' => $fireAll + ['legs' => ['none'], 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'], 'extras' => ['grate']],
        'oct60' => $fireAll + ['legs' => ['none', 'low'], 'sides' => ['s1', 's2', 'sall'], 'ornament' => $orn, 'extras' => ['grate', 'griddle', 'arm', 'lid', 'wind_guard']],
        'oct80' => $fireAll + ['legs' => ['none', 'low'], 'sides' => ['s1', 's2', 'sall'], 'ornament' => $orn, 'extras' => ['grate', 'griddle', 'arm', 'lid', 'wind_guard']],
        'ringbowl60' => $fireAll + ['legs' => ['none'], 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'], 'extras' => ['grate']],
        'ringbowl80' => $fireAll + ['legs' => ['none'], 'sides' => ['s1'], 'ornament' => ['none', 'perf_dense', 'perf_sparse'], 'extras' => ['grate']],
    ];
    $grillOpts = ['material' => ['inox2', 'inox3', 'black3'], 'font' => ['russo', 'oswald', 'dela'], 'sides' => ['s1', 's2', 'sall'], 'ornament' => $orn];
    $compat['mangal60'] = $grillOpts + ['legs' => ['none', 'low', 'high'], 'extras' => ['grate', 'griddle', 'lid', 'thermometer', 'side_table', 'side_table_left', 'hooks', 'ash_pan', 'wind_guard', 'log_rack']];
    $compat['mangal80'] = $compat['mangal60'];
    $compat['stand80'] = $grillOpts + ['legs' => ['high'], 'extras' => ['grate', 'griddle', 'lid', 'thermometer', 'side_table', 'side_table_left', 'hooks', 'ash_pan', 'wind_guard', 'log_rack', 'wheels']];
    $defaults = ['material' => 'black3', 'legs' => null, 'font' => 'russo', 'sides' => 's1', 'ornament' => 'none'];
    $moIns = $pdo->prepare('INSERT IGNORE INTO model_options (model_id, option_id, is_default) VALUES (?, ?, ?)');
    foreach ($compat as $mc => $groups) {
        $mid = (int)(db_one('SELECT id FROM models WHERE code = ?', [$mc])['id'] ?? 0);
        if (!$mid || db_one('SELECT 1 x FROM model_options WHERE model_id = ? LIMIT 1', [$mid])) {
            continue; // вече настроен (напр. от админа)
        }
        foreach ($groups as $g => $codes) {
            foreach ($codes as $i => $c) {
                $oid = $optId($g, $c);
                if (!$oid) continue;
                $def = array_key_exists($g, $defaults) ? ($defaults[$g] === null ? $i === 0 : $defaults[$g] === $c) : false;
                if ($g === 'material' && str_starts_with($mc, 'mangal') || $g === 'material' && str_starts_with($mc, 'stand')) {
                    $def = $c === 'inox2'; // мангалите остават с инокс по подразбиране
                }
                $moIns->execute([$mid, $oid, $def ? 1 : 0]);
            }
        }
    }
    // стойката е включена в цената на скарата на стойка
    $pdo->exec("UPDATE model_options mo JOIN models m ON m.id = mo.model_id JOIN options o ON o.id = mo.option_id JOIN option_groups g ON g.id = o.group_id
        SET mo.price_min = 0, mo.price_max = 0 WHERE m.code = 'stand80' AND g.code = 'legs'");

    // --- нови екстри и кортен към съществуващите модели ---
    $add = [
        'sq_s' => ['material' => ['corten'], 'ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle', 'wind_guard']],
        'sq_m' => ['material' => ['corten'], 'ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle', 'wind_guard']],
        'sq_l' => ['material' => ['corten'], 'ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle', 'wind_guard']],
        'hex60' => ['material' => ['corten'], 'ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle']],
        'mangal_std' => ['ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle', 'thermometer', 'side_table_left', 'hooks', 'ash_pan', 'wind_guard', 'log_rack']],
        'grill_stand' => ['ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle', 'thermometer', 'side_table_left', 'hooks', 'ash_pan', 'wind_guard', 'log_rack', 'wheels']],
        'mangal_picnic' => ['ornament' => ['perf_dense', 'perf_sparse'], 'extras' => ['griddle']],
    ];
    foreach ($add as $mc => $groups) {
        $mid = (int)(db_one('SELECT id FROM models WHERE code = ?', [$mc])['id'] ?? 0);
        if (!$mid) continue;
        foreach ($groups as $g => $codes) {
            foreach ($codes as $c) {
                if ($oid = $optId($g, $c)) $moIns->execute([$mid, $oid, 0]);
            }
        }
    }
}

/** „Огнище квадратно S“ -> ['Огнище квадратно', 'S']; „Мангал стандартен“ -> ['Мангал', '']; „Огнище шестоъгълно Ø60“ -> [..., 'Ø60'] */
function model_name_split(string $name): array
{
    if (preg_match('/^(.*?)\s+(S|M|L|XL|Ø\s?\d+|\d+\s*[×x]\s*\d+(?:\s*[×x]\s*\d+)?|\d+)$/u', $name, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    if (preg_match('/^(.*?)\s+(стандартен|стандартна|стандартно)$/u', $name, $m)) {
        return [trim($m[1]), ''];
    }
    return [$name, ''];
}

function model_size_text(array $m): string
{
    $n = fn($v) => rtrim(rtrim(number_format((float)$v, 1, '.', ''), '0'), '.');
    if (in_array($m['shape'], ['hex', 'octagon', 'cross', 'ringbowl'], true)) {
        return 'Ø' . $n($m['width_cm']);
    }
    return $n($m['width_cm']) . '×' . $n($m['depth_cm']) . ($m['shape'] === 'box' ? '×' . $n($m['height_cm']) : '');
}
