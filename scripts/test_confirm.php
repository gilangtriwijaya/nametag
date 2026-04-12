<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\EmployeeImportService $service */
$service = $app->make(\App\Services\EmployeeImportService::class);
$path = storage_path('app/tmp/imports/sample.csv');

try {
    $preview = $service->parseUploadedFile($path);
    // simulate preview meta
    $previewMeta = ['rows' => $preview['rows'], 'summary' => $preview['summary'], 'upload_path' => 'tmp/imports/sample.csv'];
    $res = $service->processPreview($previewMeta);
    echo json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
