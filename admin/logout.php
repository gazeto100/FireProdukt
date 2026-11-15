<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
$_SESSION = [];
session_regenerate_id(true);
redirect(url('admin/index.php'));
