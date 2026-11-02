<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-detect Laravel base path:
// 1. Local development (Back-end/public -> Back-end)
// 2. Hostinger production (public_html/api -> laravel)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $basePath = __DIR__ . '/..';
} elseif (file_exists(__DIR__ . '/../../laravel/vendor/autoload.php')) {
    $basePath = __DIR__ . '/../../laravel';
} else {
    $basePath = __DIR__ . '/..';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath . '/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath . '/bootstrap/app.php';

$app->handleRequest(Request::capture());