<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Observers\LogChangesObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // event => listeners (jika ada)
    ];

    public function boot(): void
    {
        User::observe(LogChangesObserver::class);
        Employee::observe(LogChangesObserver::class);
        Opd::observe(LogChangesObserver::class);
        OpdUnit::observe(LogChangesObserver::class);
    }
}
