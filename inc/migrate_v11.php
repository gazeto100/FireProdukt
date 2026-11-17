<?php
declare(strict_types=1);

/**
 * Миграция v11: лого и при обикновените модели (огнища и скари).
 * Нова група „Надпис и лого“ в стъпка „Надпис“: „Само надпис“ (по подразбиране), „Само лого“, „Надпис и лого“.
 * Логото се плаща като екстра (ПРИМЕРНО 25–45 €, сменя се от админа в Опции) и влиза в ориентировъчната цена.
 * Не се добавя към вида „по проект“ – там логото се прикачва в проектната форма. Идемпотентна.
 */
function migrate_v11(): void
{
    $pdo = db();
    $pdo->prepare('INSERT IGNORE INTO option_groups (code, name, hint, multi, required, sort) VALUES (?, ?, ?, 0, 1, ?)')
        ->execute(['logo', 'Надпис и лого', 'Ще получите проект с логото за одобрение преди изработка.', 6]);
    $gid = (int)(db_one("SELECT id FROM option_groups WHERE code = 'logo'")['id'] ?? 0);
    $ins = $pdo->prepare('INSERT IGNORE INTO options (group_id, code, name, hint, subgroup, price_type, price_min, price_max, weight_kg, render, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$gid, 'text_only', 'Само надпис', null, null, 'add', 0, 0, 0, '{"logo":"none"}', 1]);
    $ins->execute([$gid, 'logo_only', 'Само лого', 'Логото на мястото на надписа', null, 'add', 25, 45, 0, '{"logo":"only"}', 2]);
    $ins->execute([$gid, 'text_logo', 'Надпис и лого', 'Логото отгоре, надписът под него', null, 'add', 25, 45, 0, '{"logo":"text"}', 3]);

    $opt = fn(string $c) => (int)(db_one('SELECT id FROM options WHERE group_id = ? AND code = ?', [$gid, $c])['id'] ?? 0);
    $ids = ['text_only' => $opt('text_only'), 'logo_only' => $opt('logo_only'), 'text_logo' => $opt('text_logo')];
    // всички модели от видове, които не са „по проект“, и които още нямат избор за лого
    $models = db_all("SELECT m.id FROM models m JOIN product_types t ON t.id = m.type_id
        WHERE t.is_project = 0 AND NOT EXISTS (SELECT 1 FROM model_options mo JOIN options o ON o.id = mo.option_id WHERE mo.model_id = m.id AND o.group_id = ?)", [$gid]);
    $mo = $pdo->prepare('INSERT IGNORE INTO model_options (model_id, option_id, is_default) VALUES (?, ?, ?)');
    foreach ($models as $m) {
        foreach ($ids as $c => $oid) {
            if ($oid) $mo->execute([(int)$m['id'], $oid, $c === 'text_only' ? 1 : 0]);
        }
    }
}
