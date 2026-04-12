<?php
// scripts/finish_single_batch.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$id = 'c69dcc56-9a96-47c3-98fa-b76a93127c21';

$before = DB::table('nametag_batches')->where('id', $id)->first();
if (!$before) {
    echo "Batch not found: $id\n";
    exit(1);
}

$now = date('Y-m-d H:i:s');
$updated = DB::table('nametag_batches')
    ->where('id', $id)
    ->update([
        'status' => 'finished',
        'done' => DB::raw('`total`'),
        'finished_at' => $now,
        'updated_at' => $now,
    ]);

echo "updated rows: $updated\n\n";
$row = DB::table('nametag_batches')->where('id', $id)->first();
print_r((array)$row);
