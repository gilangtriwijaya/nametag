<?php

namespace App\Console\Commands;

use App\Services\StatistikPublikService;
use Illuminate\Console\Command;

class StatpubRebuild extends Command
{
    protected $signature = 'statpub:rebuild {--force : Ignore cache and rebuild immediately}';

    protected $description = 'Rebuild public statistics cache';

    public function handle(StatistikPublikService $service): int
    {
        $started = microtime(true);

        $service->rebuildCache();

        $this->info(sprintf(
            'Selesai dalam %dms. Cache key: %s',
            (int) ((microtime(true) - $started) * 1000),
            $service->cacheKey()
        ));

        return self::SUCCESS;
    }
}
