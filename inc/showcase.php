<?php
declare(strict_types=1);

/**
 * Модели за страницата „За хотели и къщи за гости“ – само за показ (не са в конфигуратора).
 * ПРИМЕРНО съдържание: имена, размери и описания се сменят тук.
 * kind: bowl (кръгла купа), table (огнище маса), ring (огнище с пейка-пръстен), column (колона с лого)
 * finish: inox | black | corten
 */
function showcase_items(): array
{
    return [
        ['code' => 'bowl100', 'kind' => 'bowl', 'd' => 100, 'name' => 'Кръгла купа Ø100', 'dims' => 'Ø100 × 55 см', 'finish' => 'inox',
            'desc' => 'За тераса на ресторант или вътрешен двор – огънят се вижда отвсякъде.'],
        ['code' => 'bowl120', 'kind' => 'bowl', 'd' => 120, 'name' => 'Кръгла купа Ø120', 'dims' => 'Ø120 × 60 см', 'finish' => 'corten',
            'desc' => 'Голяма купа от кортен – патината се развива с времето и не се боядисва.'],
        ['code' => 'firetable', 'kind' => 'table', 'w' => 160, 'dd' => 90, 'h' => 45, 'name' => 'Огнище маса на дърва', 'dims' => '160 × 90 × 45 см', 'finish' => 'black',
            'desc' => 'Ниска маса с вградена огнена купа за дърва в плота и искрогасител – за лаундж зони и тераси.'],
        ['code' => 'ringbench', 'kind' => 'ring', 'd' => 260, 'name' => 'Огнище с пейка-пръстен', 'dims' => 'Ø260 см, пейка 45 см', 'finish' => 'corten',
            'desc' => 'Кръгла пейка около огнището – място за 10–12 души.', 'note' => 'Изработва се по проект'],
        ['code' => 'column', 'kind' => 'column', 'w' => 40, 'dd' => 40, 'h' => 120, 'name' => 'Висока колона с лого', 'dims' => '40 × 40 × 120 см', 'finish' => 'inox',
            'desc' => 'Огън на височина и логото на обекта, изрязано и осветено отвътре – за входове и алеи.'],
    ];
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
