<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(204);
    exit;
}
$type = (string)($_POST['type'] ?? '');
if (in_array($type, ['model_select', 'text_entered'], true)) {
    $model = filter_var($_POST['model'] ?? null, FILTER_VALIDATE_INT) ?: null;
    record_event($type, $model);
}
http_response_code(204);
