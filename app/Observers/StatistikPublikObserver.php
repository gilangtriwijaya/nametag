<?php

namespace App\Observers;

use App\Jobs\RebuildStatistikPublikJob;

class StatistikPublikObserver
{
    public function created(mixed $model): void
    {
        RebuildStatistikPublikJob::dispatch()->onQueue('default');
    }

    public function updated(mixed $model): void
    {
        RebuildStatistikPublikJob::dispatch()->onQueue('default');
    }

    public function deleted(mixed $model): void
    {
        RebuildStatistikPublikJob::dispatch()->onQueue('default');
    }
}
