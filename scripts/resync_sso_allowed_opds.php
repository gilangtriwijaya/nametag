<?php
// Usage: php scripts/resync_sso_allowed_opds.php <sso_user_id>

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);

if ($argc < 2) {
    echo "Usage: php scripts/resync_sso_allowed_opds.php <sso_user_id>\n";
    exit(1);
}

$ssoId = (int)$argv[1];

use App\Models\User;
use App\Services\UserSyncService;

$u = User::where('sso_user_id', $ssoId)->first();
if (! $u) {
    echo "User with sso_user_id={$ssoId} not found.\n";
    exit(1);
}

$payload = [
    'id' => $u->sso_user_id,
    'app_role' => $u->sso_app_role ?? null,
    'app_roles' => $u->sso_app_roles ?? null,
    'app_role_slug' => null,
    'allowed_opd_ids_by_app' => $u->sso_allowed_opds_by_app ?? null,
    'allowed_opd_ids' => null,
    'is_opd_locked' => null,
];

$svc = new UserSyncService();
$svc->syncFromPayload($payload, []);

echo "Resync done for sso_user_id={$ssoId}\n";
