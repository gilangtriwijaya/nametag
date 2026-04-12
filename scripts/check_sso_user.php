<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$id = 42;
$u = User::where('sso_user_id', $id)->first();
if (! $u) {
    echo "NOUSER\n";
    exit(0);
}
$roles = $u->getRoleNames()->toArray();
echo "UID:" . ($u->id ?? 'null') . "\n";
echo "USERNAME:" . ($u->username ?? '') . "\n";
echo "ROLES:" . json_encode($roles) . "\n";
// show model_has_roles rows
$rows = \DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->where('model_id', $u->id)->get();
echo "PIVOT:" . json_encode($rows) . "\n";
