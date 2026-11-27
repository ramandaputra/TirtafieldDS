<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Normalisasi SCRIPT_NAME agar Laravel mendeteksi subfolder /absensids tanpa /public
if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/public/index.php')) {
    $_SERVER['SCRIPT_NAME'] = str_replace('/public/index.php', '/index.php', $_SERVER['SCRIPT_NAME']);
}
if (isset($_SERVER['PHP_SELF']) && str_contains($_SERVER['PHP_SELF'], '/public/index.php')) {
    $_SERVER['PHP_SELF'] = str_replace('/public/index.php', '/index.php', $_SERVER['PHP_SELF']);
}
if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], '/absensids') === 0 && strpos($_SERVER['REQUEST_URI'], '/absensids') !== 0) {
    $_SERVER['REQUEST_URI'] = '/absensids'.substr($_SERVER['REQUEST_URI'], 10);
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
