<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if ($argc < 2) {
    echo "Usage: php clean_file.php <src-path> [dst-path]\n";
    exit(2);
}
$src = $argv[1];
$dst = $argv[2] ?? null;
if (!is_file($src)) { echo "Source not found: $src\n"; exit(3); }
/**
 * Use new BackgroundRemovalService
 */
$bg = $app->make(App\Services\BackgroundRemovalService::class);
if (!$dst) {
    $base = pathinfo($src, PATHINFO_FILENAME);
    $dstDir = public_path('uploads/employees/clean');
    if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
    $dst = $dstDir . '/' . $base . '.png';
}
// prefer explicit clean into destination
$cleaned = $bg->clean($src, $dst);
if ($cleaned) {
    echo "Cleaned -> $cleaned\n";
    exit(0);
}
echo "Clean failed\n";
exit(4);
