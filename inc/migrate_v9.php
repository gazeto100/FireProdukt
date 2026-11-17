<?php
declare(strict_types=1);

/**
 * Миграция v9: „Кръстато огнище“ → „Огнище на кръстата стойка“.
 * Тава с наклонени стени (обърната пресечена пирамида) лежи във V-образния профил на две плочи на прорез.
 * Размерът на модела е ширината на тавата; стойката е по-тясна (виж cross_parts() в inc/geometry.php).
 * - преименува формата и модела 60 (размерът вече не е Ø, тавата е квадратна);
 * - нова цена на модела 60 (вече включва и тавата) – само ако админът не я е сменял;
 * - нов модел 80 см със същите опции.
 * Всички размери и цени са ПРИМЕРНИ. Идемпотентна.
 */
function migrate_v9(): void
{
    $pdo = db();
    $pdo->prepare("UPDATE forms SET name = ?, description = ? WHERE code = 'cross'")->execute([
        'Огнище на кръстата стойка',
        'Тава за огъня върху две плочи на прорез – сглобява се без инструменти.',
    ]);
    $pdo->prepare("UPDATE models SET name = ?, size_label = ? WHERE code = 'cross60'")->execute(['Огнище на кръстата стойка 60', '60 см']);
    $pdo->exec("UPDATE models SET price_min = 290, price_max = 370 WHERE code = 'cross60' AND price_min = 180 AND price_max = 240");

    if (!db_one("SELECT 1 x FROM models WHERE code = 'cross80'")) {
        $src = db_one("SELECT * FROM models WHERE code = 'cross60'");
        if ($src) {
            $pdo->prepare('INSERT INTO models (type_id, form_id, code, name, size_label, shape, width_cm, depth_cm, height_cm, max_chars, price_min, price_max, sort, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $src['type_id'], $src['form_id'], 'cross80', 'Огнище на кръстата стойка 80', '80 см', 'cross',
                80, 80, 45, $src['max_chars'], 440, 570, (int)$src['sort'] + 1, $src['active'],
            ]);
            $newId = (int)$pdo->lastInsertId();
            // същите опции като при модела 60 (цените по модел се пренасят както са)
            $pdo->prepare('INSERT IGNORE INTO model_options (model_id, option_id, is_default, price_min, price_max)
                SELECT ?, option_id, is_default, price_min, price_max FROM model_options WHERE model_id = ?')->execute([$newId, $src['id']]);
        }
    }
}
