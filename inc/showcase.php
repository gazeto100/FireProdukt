<?php
declare(strict_types=1);

/**
 * Страницата „За хотели и къщи за гости“ и проектните запитвания.
 * Моделите за обекти са в базата (формите от вид „по проект“, миграция v10) и се редактират от админа.
 */

/** Формите от вид „по проект“ (за картите на страницата и „Интересуват ме“) */
function project_forms(array $cat): array
{
    $proj = [];
    foreach ($cat['types'] as $t) {
        if (!empty($t['is_project'])) $proj[$t['code']] = true;
    }
    return array_values(array_filter($cat['forms'], fn($f) => isset($proj[$f['type']])));
}

function project_materials(): array
{
    return ['inox' => 'Инокс (неръждаема стомана)', 'black' => 'Черна стомана с прахово боядисване', 'corten' => 'Кортен (патинираща стомана)'];
}

function project_materials_short(): array
{
    return ['inox' => 'Инокс', 'black' => 'Черна стомана', 'corten' => 'Кортен'];
}

function project_deadlines(): array
{
    return ['asap' => 'Възможно най-скоро', 'm1' => 'До 1 месец', 'm3' => '1–3 месеца', 'm6' => '3–6 месеца', 'flex' => 'Гъвкав / още планираме'];
}

/** Четим списък за запитване по проект (имейли, админ, благодарствена страница) */
function project_lines(array $cfg): array
{
    $lines = [
        'Вид' => 'Проект за обект',
        'Обект' => ($cfg['object'] ?? '') !== '' ? $cfg['object'] : (!empty($cfg['object_removed']) ? '(изтрит)' : '—'),
        'Брой' => (int)($cfg['quantity'] ?? 1) . ' бр.',
        'Материал' => project_materials()[$cfg['material'] ?? ''] ?? '—',
        'Срок' => project_deadlines()[$cfg['deadline'] ?? ''] ?? '—',
        'Интересуват ме' => $cfg['interests'] ? implode(', ', array_column($cfg['interests'], 'name')) : '—',
    ];
    if (!empty($cfg['logo'])) {
        $lines['Лого'] = $cfg['logo'];
    }
    return $lines;
}
