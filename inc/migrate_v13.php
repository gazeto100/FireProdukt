<?php
declare(strict_types=1);

/**
 * Миграция v13: „По ваша идея“ (запитвания с вид 'idea' – колоната inquiries.kind вече я има).
 * Настройки: бюджетни диапазони (по един на ред) и „Индивидуалните проекти започват от … €“. ПРИМЕРНИ стойности. Идемпотентна.
 */
function migrate_v13(): void
{
    $st = db()->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)');
    $st->execute(['idea_budgets', "до 500 €\n500–1000 €\n1000–2000 €\nнад 2000 €"]);
    $st->execute(['idea_min_price', '400']);
}
