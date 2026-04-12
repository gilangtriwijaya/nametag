<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\OpdUnit;
use App\Models\UnitKerja;
use App\Policies\EmployeePolicy;
use App\Policies\OpdUnitPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{

    protected $policies = [
        Employee::class => EmployeePolicy::class,
        OpdUnit::class  => OpdUnitPolicy::class,
        UnitKerja::class => OpdUnitPolicy::class,
    ];

    public function boot(): void
    {
        // Daftarkan policies
        $this->registerPolicies();

        /**
         * Superadmin bypass semua gate/policy.
         * Return `true` mengizinkan, `null` = lanjutkan ke policy biasa.
         */
        Gate::before(function ($user, string $ability) {
            return (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) ? true : null;
        });
    }
}
