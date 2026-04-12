<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->handle(new Symfony\Component\Console\Input\ArgvInput, new Symfony\Component\Console\Output\ConsoleOutput);

use App\Models\User;
use App\Models\SsoAllowedOpd;

$email = $argv[1] ?? null;
if (!$email) {
    echo "Usage: php scripts/show_user_sso.php <email>\n";
    exit(1);
}

$u = User::where('email', $email)->first();
if (! $u) {
    echo "User with email {$email} not found\n";
    exit(1);
}

echo "Local id: {$u->id} sso_user_id: {$u->sso_user_id} name: {$u->name}\n";
echo "sso_app_roles: " . json_encode($u->sso_app_roles) . "\n";
echo "sso_allowed_opds_by_app: " . json_encode($u->sso_allowed_opds_by_app) . "\n";

$rows = SsoAllowedOpd::where('user_id', $u->id)->get();
if ($rows->isEmpty()) {
    echo "No sso_allowed_opds rows\n";
} else {
    foreach ($rows as $r) {
        $opd = \App\Models\Opd::find($r->opd_id);
        $nama = $opd ? $opd->nama : '(not found)';
        echo "allowed local opd_id: {$r->opd_id} name: {$nama}\n";
    }
}
