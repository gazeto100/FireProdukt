<?php
declare(strict_types=1);

/**
 * Миграция v6 (етап 3, част В): размер по избор.
 * - граници по форма (мин./макс./стъпка за всяко измерение, макс. отношение височина/ширина), фиксирана сума;
 * - цена на m² по материал (от–до);
 * - настройки: формат на листа, праг за палет, надценка за индивидуален размер.
 * Всички стойности са ПРИМЕРНИ и се редактират от админа. Идемпотентна.
 */
function migrate_v6(): void
{
    $pdo = db();
    $cols = [
        'custom_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'w_min' => 'DECIMAL(6,1) NULL', 'w_max' => 'DECIMAL(6,1) NULL',
        'd_min' => 'DECIMAL(6,1) NULL', 'd_max' => 'DECIMAL(6,1) NULL',
        'h_min' => 'DECIMAL(6,1) NULL', 'h_max' => 'DECIMAL(6,1) NULL',
        'step_cm' => 'DECIMAL(4,1) NOT NULL DEFAULT 5',
        'max_h_ratio' => 'DECIMAL(4,2) NOT NULL DEFAULT 1.00',
        'custom_fixed' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    ];
    foreach ($cols as $c => $def) {
        if (!db_all("SHOW COLUMNS FROM forms LIKE '$c'")) {
            $pdo->exec("ALTER TABLE forms ADD COLUMN $c $def");
        }
    }
    foreach (['price_m2_min', 'price_m2_max'] as $c) {
        if (!db_all("SHOW COLUMNS FROM options LIKE '$c'")) {
            $pdo->exec("ALTER TABLE options ADD COLUMN $c DECIMAL(10,2) NULL AFTER price_max");
        }
    }

    $defaults = form_custom_defaults();
    foreach (db_all('SELECT id, shape FROM forms WHERE w_min IS NULL') as $f) {
        $d = $defaults[$f['shape']] ?? $defaults['box'];
        $pdo->prepare('UPDATE forms SET w_min=?, w_max=?, d_min=?, d_max=?, h_min=?, h_max=?, max_h_ratio=?, custom_fixed=? WHERE id=?')
            ->execute([$d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[6], $d[7], $f['id']]);
    }
    // мангалът за пикник е малък по природа
    $pdo->exec("UPDATE forms SET w_max = 60, d_max = 40, h_max = 20 WHERE code = 'picnic'");
    // купата с пръстен още не е потвърдена – без размер по избор
    $pdo->exec("UPDATE forms SET custom_enabled = 0 WHERE code = 'ringbowl'");

    // цена на m² готово изделие по материал (от–до), € – ПРИМЕРНИ; калибрирани така, че размер по избор,
    // равен на стандартен, да излиза около стандартната цена + надценката за индивидуален размер
    $m2 = ['inox2' => [230, 315], 'inox3' => [300, 410], 'black3' => [175, 245], 'corten' => [320, 440]];
    foreach ($m2 as $code => $p) {
        $pdo->prepare("UPDATE options o JOIN option_groups g ON g.id = o.group_id SET o.price_m2_min = ?, o.price_m2_max = ?
            WHERE g.code = 'material' AND o.code = ? AND o.price_m2_min IS NULL")->execute([$p[0], $p[1], $code]);
    }

    $st = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
    foreach (['sheet_w_mm' => '2970', 'sheet_h_mm' => '1470', 'pallet_kg' => '30', 'custom_surcharge_pct' => '20'] as $k => $v) {
        $st->execute([$k, $v]);
    }
}

/** Граници по подразбиране според формата (макс. 120 см по най-голямата страна) – ПРИМЕРНИ.
 * [w_min, w_max, d_min, d_max, h_min, h_max, max_h_ratio, фиксирана сума] */
function form_custom_defaults(): array
{
    return [
        'box' => [30, 120, 30, 120, 20, 60, 0.8, 60],
        'hex' => [40, 120, 40, 120, 20, 60, 0.8, 80],
        'octagon' => [40, 120, 40, 120, 20, 60, 0.8, 90],
        'trapezoid' => [40, 120, 40, 120, 25, 60, 0.8, 90],
        'pedestal' => [40, 100, 40, 100, 40, 90, 1.5, 140],
        'cross' => [40, 120, 40, 120, 30, 70, 1.0, 40],
        'ringbowl' => [50, 120, 50, 120, 35, 80, 1.0, 120],
        'grill' => [30, 120, 20, 60, 10, 30, 0.6, 40],
        // етап 4
        'tabletop' => [25, 50, 15, 35, 10, 15, 0.6, 30],
        'suitcase' => [40, 70, 25, 40, 10, 15, 0.4, 50],
        'bbq' => [50, 100, 35, 60, 15, 30, 0.6, 90],
        'plancha' => [70, 120, 70, 120, 80, 100, 1.5, 180],
        'plancha_sq' => [70, 120, 70, 120, 80, 100, 1.5, 180],
        'cheverme' => [80, 120, 30, 50, 15, 25, 0.3, 70],
        'insert' => [40, 120, 30, 60, 8, 15, 0.4, 40],
    ];
}
