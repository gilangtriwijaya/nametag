<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/../../nametag/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__ . '/../../nametag/vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/../../nametag/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
