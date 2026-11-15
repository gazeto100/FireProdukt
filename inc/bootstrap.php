<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Sofia');

$cfgFile = APP_ROOT . '/config/config.php';
if (!is_file($cfgFile)) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (preg_match('#/(admin|api)$#', $base)) {
        $base = dirname($base);
    }
    header('Location: ' . rtrim($base, '/') . '/install/');
    exit;
}
$GLOBALS['APP_CONFIG'] = require $cfgFile;

$debug = !empty($GLOBALS['APP_CONFIG']['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/crypto.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/geometry.php';
require __DIR__ . '/pricing.php';
require __DIR__ . '/validate.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/svg_sanitize.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/uploads.php';
require __DIR__ . '/showcase.php';
require __DIR__ . '/migrate.php';
run_migrations();
