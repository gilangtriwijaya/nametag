<?php
// Quick script to parse the stored template using EmployeeImportService
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    /** @var \App\Services\EmployeeImportService $service */
    $service = $app->make(\App\Services\EmployeeImportService::class);
    $path = storage_path('app/templates/employee_import_template.csv');
    echo "Parsing: $path\n";
    $result = $service->parseUploadedFile($path);
    echo "OK\n";
    echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
