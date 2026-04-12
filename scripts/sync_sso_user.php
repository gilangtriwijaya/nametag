<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payload = [
    'id' => 42,
    'username' => 'verifikatorglobal',
    'name' => 'Verifikator Global',
    'email' => 'verifglobal@anambaskab.go.id',
    'user_type_id' => 3,
    'opd_id' => null,
    'opd_code' => null,
    'opd_unit_id' => null,
    'opd_unit_code' => null,
    'is_active' => 1,
    'app_role' => 'verifikator global',
    'app_roles' => ['verifikator global','verifikator_global','verifikator-global','verifikatorglobal'],
    'app_role_slug' => 'verifikator-global',
];

$mapped = ['opd_id' => null, 'opd_unit_id' => null];

/** @var \App\Services\UserSyncService $sync */
$sync = app(\App\Services\UserSyncService::class);
$user = $sync->syncFromPayload($payload, $mapped);

echo "SYNCED UID:" . $user->id . "\n";
echo "ROLES:" . json_encode($user->getRoleNames()->toArray()) . "\n";
