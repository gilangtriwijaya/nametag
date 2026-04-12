<?php
// scripts/finish_batches.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$ids = [
    '9ff0c56c-0c26-403f-9105-f126744863f9',
    'ce60663e-2db9-4c6b-9d27-940b51f6f46c',
    'c357e454-058d-4851-834a-e99bd8325709'
];

$now = date('Y-m-d H:i:s');

$updated = DB::table('nametag_batches')
    ->whereIn('id', $ids)
    ->update([
        'status' => 'finished',
        'done' => DB::raw('`total`'),
        'finished_at' => $now,
        'updated_at' => $now,
    ]);

echo "updated rows: $updated\n\n";

$rows = DB::table('nametag_batches')->whereIn('id', $ids)->get();
foreach ($rows as $r) {
    print_r((array)$r);
    echo "\n";
}

$remaining = DB::table('nametag_batches')->where('status', 'queued')->count();
echo "\nRemaining queued count: $remaining\n";
