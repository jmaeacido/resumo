<?php

/**
 * Strip the Laragon subdirectory prefix so Laravel routes match correctly.
 * e.g. /resumo/ → /
 */
function resumo_fix_subdirectory_path(): void
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicDir = dirname($scriptName);

    if (! str_ends_with($publicDir, '/public')) {
        return;
    }

    $basePath = dirname($publicDir);

    if ($basePath === '/' || $basePath === '.' || $basePath === '') {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $query = parse_url($uri, PHP_URL_QUERY);

    if ($path !== $basePath && ! str_starts_with($path, $basePath.'/')) {
        return;
    }

    $path = substr($path, strlen($basePath)) ?: '/';
    $_SERVER['REQUEST_URI'] = $path.($query ? '?'.$query : '');
}

resumo_fix_subdirectory_path();

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
