<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$filename = $argv[1] ?? '8fe7ad90-5568-406f-9bf3-66a630c2db6c.png';
$request = Illuminate\Http\Request::create('/internal/rembg/clean-employee', 'POST', ['filename' => $filename]);
$request->server->set('REMOTE_ADDR', '127.0.0.1');
$response = $kernel->handle($request);
echo (string) $response->getContent() . PHP_EOL;
$kernel->terminate($request, $response);
