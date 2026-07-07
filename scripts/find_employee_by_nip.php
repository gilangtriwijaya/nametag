<?php
// One-off script to dump employee by NIP
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
$nip = $argv[1] ?? '199111012025212033';
$e = Employee::where('nip', $nip)->first();
if (! $e) {
    echo json_encode(['found' => false]);
    exit(0);
}
$data = $e->toArray();
// include some computed attrs
$data['foto_url'] = $e->foto_url ?? null;
$data['nama_lengkap'] = $e->nama_lengkap ?? null;

echo json_encode(['found' => true, 'employee' => $data], JSON_PRETTY_PRINT);
