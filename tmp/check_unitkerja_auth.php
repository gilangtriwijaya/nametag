<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Gate;

$user = \App\Models\User::whereHas('roles', function($q){ $q->where('name','superadmin'); })->first();
if (! $user) {
    echo json_encode(['error' => 'no_superadmin_user']);
    exit(0);
}

// Directly evaluate before-callback logic from AuthServiceProvider for debugging
$beforeResult = null;
if (method_exists($user, 'hasRole')) {
    $beforeResult = $user->hasRole('superadmin') ? true : null;
}

$g = Gate::forUser($user);
$canViewAny = $g->allows('viewAny', \App\Models\UnitKerja::class);

echo json_encode([
    'user_id' => $user->id,
    'has_role_superadmin' => $user->hasRole('superadmin'),
    'before_result_evaluated' => $beforeResult,
    'can_viewAny' => $canViewAny
], JSON_PRETTY_PRINT);

// Direct policy call
$policy = new \App\Policies\OpdUnitPolicy();
$policyResult = $policy->viewAny($user);
echo "\nPOLICY viewAny returned: " . ($policyResult ? 'true' : 'false') . "\n";
