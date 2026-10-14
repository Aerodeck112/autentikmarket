<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Instalare nouă (fără .env): instalatorul web, pentru găzduirile fără Terminal.
if (! file_exists(__DIR__.'/../.env') && file_exists(__DIR__.'/install.php')) {
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
    $dir = str_ends_with($dir, '/public') ? substr($dir, 0, -7) : $dir; // aplicație urcată direct în public_html
    header('Location: '.$dir.'/install.php', true, 302);
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
