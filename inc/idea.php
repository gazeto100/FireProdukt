<?php
declare(strict_types=1);

/**
 * „По ваша идея“: запитване със снимки/скица/чертеж и размери (вид на запитването 'idea').
 * Страница po-vasha-ideya.php, приемане api/idea.php. Настройки: idea_budgets (диапазони, по един на ред),
 * idea_min_price („Индивидуалните проекти започват от … €“; празно – изречението не се показва).
 */

function idea_whats(): array
{
    return ['firepit' => 'Огнище', 'grill' => 'Скара или мангал', 'other' => 'Друго изделие от метал'];
}

function idea_materials(): array
{
    return ['inox' => 'Инокс', 'black' => 'Черна стомана', 'corten' => 'Кортен', 'unsure' => 'Не съм сигурен'];
}

/** Бюджетни диапазони от настройките + „Не знам“: [ключ => текст] */
function idea_budgets(): array
{
    $out = [];
    $i = 0;
    foreach (preg_split('/\r?\n/', (string)setting('idea_budgets', '')) as $ln) {
        $ln = trim($ln);
        if ($ln !== '') $out['b' . $i++] = mb_substr($ln, 0, 60);
    }
    $out['unknown'] = 'Не знам';
    return $out;
}

/** „Индивидуалните проекти започват от {сума} €. Ще се свържем с вас до {срок}.“ (първото изречение – само ако има сума) */
function idea_footer_text(): string
{
    $min = trim((string)setting('idea_min_price', ''));
    $t = $min !== '' ? 'Индивидуалните проекти започват от ' . $min . ' ' . setting('currency', '€') . '. ' : '';
    return $t . 'Ще се свържем с вас до ' . contact_deadline() . '.';
}

/** Четим списък за запитване „По идея“ (админ, имейли, благодарствена страница) */
function idea_lines(array $cfg): array
{
    $d = $cfg['dims'] ?? [];
    $dims = array_filter([$d['w'] ?? null, $d['d'] ?? null, $d['h'] ?? null], fn($v) => $v !== null && $v !== '');
    $dimTxt = '';
    if ($dims) {
        $dimTxt = 'ширина ' . fmt_num($d['w']) . ' см' . (isset($d['d']) && $d['d'] !== null ? ', дълбочина ' . fmt_num($d['d']) . ' см' : '') . ', височина ' . fmt_num($d['h']) . ' см (приблизително)';
    }
    $lines = [
        'Вид' => 'По ваша идея',
        'Какво' => idea_whats()[$cfg['what'] ?? ''] ?? '—',
        'Описание' => ($cfg['description'] ?? '') !== '' ? $cfg['description'] : (!empty($cfg['description_removed']) ? '(изтрито)' : '—'),
        'Размери' => $dimTxt ?: '—',
        'Материал' => idea_materials()[$cfg['material'] ?? ''] ?? '—',
        'Надпис или лого' => ($cfg['inscription'] ?? '') !== '' ? $cfg['inscription'] : (!empty($cfg['inscription_removed']) ? '(изтрито)' : 'без'),
        'Брой' => (int)($cfg['quantity'] ?? 1) . ' бр.',
        'Бюджет' => ($cfg['budget'] ?? '') !== '' ? $cfg['budget'] : '—',
    ];
    $files = $cfg['files'] ?? [];
    $lines['Файлове'] = $files
        ? implode("\n", array_map(fn($f) => ($f['name'] ?? '(изтрит)') . ' (' . strtoupper($f['ext'] ?? '') . ', ' . fmt_bytes((int)($f['size'] ?? 0)) . ')', $files))
        : (!empty($cfg['files_removed']) ? (int)$cfg['files_removed'] . ' (изтрити)' : '—');
    return $lines;
}

/**
 * Имейлите по „По идея“: ['admin' => [...] | null, 'client' => [...] | null] с to, subject, text, html, attach (bool).
 * До администратора – всички данни; файловете се прикачват, ако общо са до 15 MB, иначе – линк към админа.
 * До клиента – потвърждение със списък на изпратеното, без цена.
 */
function idea_mails(array $d, array $config, int $filesBytes): array
{
    $site = site_name();
    $lines = idea_lines($config);
    $contact = ['Име' => $d['name'], 'Телефон' => $d['phone'], 'Имейл' => $d['email'], 'Населено място' => $d['city'] ?: '—'];
    $admUrl = abs_url('admin/inquiry.php?id=' . $d['id']);
    $attach = $filesBytes <= IDEA_MAIL_ATTACH_MAX;
    $out = ['admin' => null, 'client' => null];
    $rowsHtml = function (array $rows, bool $bold): string {
        $h = '';
        foreach ($rows as $k => $v) {
            $h .= '<tr><td style="padding:6px 10px;color:#666;border-bottom:1px solid #eee;vertical-align:top">' . e($k) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee">' . ($bold ? '<b>' : '') . nl2br(e($v)) . ($bold ? '</b>' : '') . '</td></tr>';
        }
        return $h;
    };

    $notify = setting('notify_email', '');
    if ($notify !== '') {
        $t = "Ново запитване ПО ИДЕЯ {$d['code']}\n\n";
        foreach (array_merge($contact, $lines) as $k => $v) $t .= "$k: $v\n";
        $t .= "\n" . ($attach ? 'Файловете са прикачени.' : 'Файловете са общо ' . fmt_bytes($filesBytes) . ' – над 15 MB, затова не са прикачени. Свалете ги от админа.') . "\n";
        $t .= "\nИзточник: " . ($d['utm_source'] ?: '—') . ' / ' . ($d['utm_medium'] ?: '—') . ' / ' . ($d['utm_campaign'] ?: '—') . "\n";
        $t .= "\nДетайли: $admUrl\n";
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222"><h2 style="margin:0 0 12px">Запитване по идея ' . e($d['code']) . '</h2>'
            . '<table style="border-collapse:collapse">' . $rowsHtml(array_merge($contact, $lines), true) . '</table>'
            . '<p>' . ($attach ? 'Файловете са прикачени.' : 'Файловете са общо ' . e(fmt_bytes($filesBytes)) . ' – над 15 MB, затова не са прикачени.') . ' <a href="' . e($admUrl) . '">Отвори в админ панела</a></p></div>';
        $what = idea_whats()[$config['what'] ?? ''] ?? 'изделие';
        $out['admin'] = ['to' => $notify, 'subject' => "Запитване по идея {$d['code']} – $what ({$d['name']})", 'text' => $t, 'html' => $html, 'attach' => $attach];
    }
    if ($d['email'] !== '') {
        $t = "Здравейте, {$d['name']}!\n\nПолучихме вашата идея ({$d['code']}). Ще се свържем с вас до " . contact_deadline() . ", за да уточним детайлите. Предложението с цена и проект изпращаме след това.\n\nВие изпратихте:\n\n";
        foreach ($lines as $k => $v) $t .= "$k: $v\n";
        $t .= "\nПоздрави,\n$site\n";
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;max-width:560px"><p>Здравейте, ' . e($d['name']) . '!</p>'
            . '<p>Получихме вашата идея (<b>' . e($d['code']) . '</b>). Ще се свържем с вас до ' . e(contact_deadline()) . ', за да уточним детайлите. Предложението с цена и проект изпращаме след това.</p>'
            . '<p>Вие изпратихте:</p><table style="border-collapse:collapse;width:100%">' . $rowsHtml($lines, false) . '</table><p>Поздрави,<br>' . e($site) . '</p></div>';
        $out['client'] = ['to' => $d['email'], 'subject' => "Получихме вашата идея {$d['code']}", 'text' => $t, 'html' => $html, 'attach' => false];
    }
    return $out;
}
