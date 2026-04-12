<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Gate;

$u = User::where('sso_user_id', 42)->first();
if (! $u) { echo "NOUSER\n"; exit(0); }

// check viewAny
$canViewAny = Gate::forUser($u)->allows('viewAny', Employee::class);
echo "viewAny: ".($canViewAny ? 'ALLOW' : 'DENY')."\n";

// pick an active employee to check generateNametag
$e = Employee::where('status_aktif','AKTIF')->first();
if (!$e) { echo "NOEMP\n"; exit(0); }
$canGenerate = Gate::forUser($u)->allows('generateNametag', $e);
echo "generateNametag (employee {$e->id}): ".($canGenerate ? 'ALLOW' : 'DENY')."\n";

// check manageStatus
$canManage = Gate::forUser($u)->allows('manageStatus', $e);
echo "manageStatus (employee {$e->id}): ".($canManage ? 'ALLOW' : 'DENY')."\n";

// roles
echo "roles: ".json_encode($u->getRoleNames()->toArray())."\n";
