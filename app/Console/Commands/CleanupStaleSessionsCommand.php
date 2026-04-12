<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanupStaleSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:cleanup {--days=7 : Days to keep sessions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove stale/expired session files to prevent corruption';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $sessionPath = storage_path('framework/sessions');
        
        if (!is_dir($sessionPath)) {
            $this->info('Session directory not found.');
            return 0;
        }

        $threshold = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        $files = File::files($sessionPath);
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                File::delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned up {$deleted} stale session files (older than {$days} days).");
        
        // Also clear cache files
        $cachePath = storage_path('framework/cache');
        if (is_dir($cachePath)) {
            $cacheDeleted = 0;
            $cacheFiles = File::files($cachePath);
            foreach ($cacheFiles as $file) {
                if (filemtime($file) < $threshold) {
                    File::delete($file);
                    $cacheDeleted++;
                }
            }
            $this->info("Cleaned up {$cacheDeleted} stale cache files.");
        }

        return 0;
    }
}
