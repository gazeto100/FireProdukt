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

/**
 * Имейлите по проектно запитване: ['admin' => [...] | null, 'client' => [...] | null], всеки с to, subject, text, html.
 * До администратора – с ориентировъчната цена (ако има конфигурация); до клиента – БЕЗ цена при всякакви настройки.
 * $d: id, code, name, phone, email, city, note, object, price_min, price_max, utm_source, utm_medium, utm_campaign
 */
function project_mails(array $d, array $config, array $cat): array
{
    $site = site_name();
    $lines = config_lines($config, $cat);
    $contact = ['Лице за контакт' => $d['name'], 'Телефон' => $d['phone'], 'Имейл' => $d['email'] ?: '—', 'Населено място' => $d['city'] ?: '—', 'Бележка' => $d['note'] ?: '—'];
    $admUrl = abs_url('admin/inquiry.php?id=' . $d['id']);
    $out = ['admin' => null, 'client' => null];

    $notify = setting('notify_email', '');
    if ($notify !== '') {
        $t = "Ново запитване по ПРОЕКТ {$d['code']}\n\n";
        foreach (array_merge($contact, $lines) as $k => $v) $t .= "$k: $v\n";
        $priceTxt = $d['price_min'] !== null ? price_range_text((float)$d['price_min'], (float)$d['price_max']) : null;
        if ($priceTxt !== null) $t .= "\nОриентировъчна цена: $priceTxt (не се показва на клиента)\n";
        $t .= "\nИзточник: " . ($d['utm_source'] ?: '—') . ' / ' . ($d['utm_medium'] ?: '—') . ' / ' . ($d['utm_campaign'] ?: '—') . "\n";
        $t .= "\nДетайли: $admUrl\n";
        $rows = '';
        foreach (array_merge($contact, $lines, $priceTxt !== null ? ['Ориентировъчна цена' => $priceTxt . ' (не се показва на клиента)'] : []) as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee"><b>' . nl2br(e($v)) . '</b></td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222"><h2 style="margin:0 0 12px">Запитване по проект ' . e($d['code']) . '</h2>'
            . '<table style="border-collapse:collapse">' . $rows . '</table><p><a href="' . e($admUrl) . '">Отвори в админ панела</a></p></div>';
        $out['admin'] = ['to' => $notify, 'subject' => "Запитване по проект {$d['code']} – {$d['object']} ({$d['name']})", 'text' => $t, 'html' => $html];
    }
    if ($d['email'] !== '') {
        // без цена: при проект цената е по оферта, независимо от show_prices
        $t = "Здравейте, {$d['name']}!\n\nПолучихме запитването ви по проект ({$d['code']}). Ще се свържем с вас до " . contact_deadline() . ", за да уточним детайлите. Предложението с цена и проект изпращаме след това. Цената е по оферта.\n\n";
        foreach ($lines as $k => $v) $t .= "$k: $v\n";
        $t .= "\nПоздрави,\n$site\n";
        $rows = '';
        foreach ($lines as $k => $v) {
            $rows .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee">' . e($v) . '</td></tr>';
        }
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px"><p>Здравейте, ' . e($d['name']) . '!</p>'
            . '<p>Получихме запитването ви по проект (<b>' . e($d['code']) . '</b>). Ще се свържем с вас до ' . e(contact_deadline()) . ', за да уточним детайлите. Предложението с цена и проект изпращаме след това. Цената е по оферта.</p>'
            . '<table style="border-collapse:collapse;width:100%">' . $rows . '</table><p>Поздрави,<br>' . e($site) . '</p></div>';
        $out['client'] = ['to' => $d['email'], 'subject' => "Получихме запитването ви {$d['code']}", 'text' => $t, 'html' => $html];
    }
    return $out;
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
