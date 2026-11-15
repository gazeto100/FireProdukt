<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_admin();

// CSV за Excel: UTF-8 с BOM, разделител ";" (българските настройки на Excel ползват десетична запетая)
$statuses = inquiry_statuses();
$status = (string)($_GET['status'] ?? '');
$where = [];
$params = [];
if (isset($statuses[$status])) {
    $where[] = 'i.status = ?';
    $params[] = $status;
}
if (in_array($_GET['kind'] ?? '', ['config', 'project'], true)) {
    $where[] = 'i.kind = ?';
    $params[] = $_GET['kind'];
}
if (($_GET['anon'] ?? '') === '1') {
    $where[] = 'i.anonymized_at IS NOT NULL';
} elseif (($_GET['anon'] ?? '') !== 'all') {
    $where[] = 'i.anonymized_at IS NULL';
}
$sql = 'SELECT i.*, (SELECT GROUP_CONCAT(CONCAT(DATE_FORMAT(n.created_at, "%d.%m.%Y"), ": ", n.body) ORDER BY n.id SEPARATOR " | ") FROM inquiry_notes n WHERE n.inquiry_id = i.id) AS notes
  FROM inquiries i' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.id DESC';
$rows = db_all($sql, $params);

/** Защита от CSV/формула инжекция в Excel */
function csv_cell($v): string
{
    $v = (string)$v;
    if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $v = "'" . $v;
    }
    return '"' . str_replace('"', '""', $v) . '"';
}
function dec($v): string
{
    return $v === null || $v === '' ? '' : str_replace('.', ',', rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'));
}

$fname = 'zapitvania-' . date('Y-m-d') . ($status ? '-' . $status : '') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$head = ['Номер', 'Вид', 'Дата', 'Статус', 'Име', 'Телефон', 'Имейл', 'Населено място', 'Бележка на клиента', 'Модел', 'Размери (см)', 'Размер по избор', 'Ширина (см)', 'Дълбочина (см)', 'Височина (см)', 'Диаметър (см)', 'Материал', 'Крачета',
    'Надпис', 'Знаци', 'Шрифт', 'Височина на буквите (мм)', 'Страни', 'Орнамент', 'Екстри', 'Количество', 'Цена от', 'Цена до', 'Тегло (кг/бр.)', 'Доставка с палет', 'От две части',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'Referrer', 'Бележки (админ)', 'Анонимизирано', 'Обект (проект)', 'Срок (проект)', 'Интересуват ме (проект)', 'Лого (проект)'];
fwrite($out, implode(';', array_map('csv_cell', $head)) . "\r\n");
foreach ($rows as $r) {
    $c = json_decode($r['config_json'], true) ?: [];
    $o = $c['options'] ?? [];
    $m = $c['model'] ?? [];
    $round = in_array($m['shape'] ?? '', ['hex', 'octagon', 'cross', 'ringbowl'], true);
    $dims = $m ? ($round ? 'Ø' . fmt_num($m['w']) . ' × ' . fmt_num($m['h']) : fmt_num($m['w']) . ' × ' . fmt_num($m['d']) . ' × ' . fmt_num($m['h'])) : '';
    $isP = $r['kind'] === 'project';
    if ($isP) {
        $m = ['name' => 'Проект'];
        $dims = '';
        $o = ['material' => ['name' => project_materials_short()[$c['material'] ?? ''] ?? '']];
    }
    $line = [
        $r['public_code'], $isP ? 'Проект' : 'Конфигуратор', date('d.m.Y H:i', strtotime($r['created_at'])), $statuses[$r['status']] ?? $r['status'],
        $r['name'], $r['phone'], $r['email'], $r['city'], $r['note'],
        !$isP && !empty($c['custom']) ? ($m['form_name'] ?? $m['name'] ?? '') . ' – по избор' : ($m['name'] ?? ''), $dims, $isP ? '' : (!empty($c['custom']) ? 'да' : 'не'),
        $isP || $round ? '' : dec($m['w'] ?? null), $isP || $round ? '' : dec($m['d'] ?? null), $isP ? '' : dec($m['h'] ?? null), $isP || !$round ? '' : dec($m['w'] ?? null),
        $o['material']['name'] ?? '', $o['legs']['name'] ?? '',
        $c['text'] ?? '', $c['text_length'] ?? '', ($c['text_length'] ?? 0) ? ($o['font']['name'] ?? '') : '', dec($c['letter_mm'] ?? null),
        ($c['decorated_walls'] ?? 0) ? ($o['sides']['name'] ?? '') : '', $o['ornament']['name'] ?? '',
        implode(', ', array_column($o['extras'] ?? [], 'name')), $r['quantity'], dec($r['price_min']), dec($r['price_max']), dec($r['weight_kg']),
        !empty($c['pallet']) ? 'да' : '', !empty($c['two_parts']) ? 'да – ' . ($c['two_parts_part'] ?? '') : '',
        $r['utm_source'], $r['utm_medium'], $r['utm_campaign'], $r['utm_term'], $r['utm_content'], $r['referrer'], $r['notes'],
        $r['anonymized_at'] ? date('d.m.Y', strtotime($r['anonymized_at'])) : '',
        $isP ? ($c['object'] ?? '') : '', $isP ? (project_deadlines()[$c['deadline'] ?? ''] ?? '') : '',
        $isP ? implode(', ', array_column($c['interests'] ?? [], 'name')) : '', $isP ? ($c['logo'] ?? '') : '',
    ];
    fwrite($out, implode(';', array_map('csv_cell', $line)) . "\r\n");
}
fclose($out);
