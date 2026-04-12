<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CheckSessionHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'session:check-health {--user= : Check specific user ID}';

    /**
     * The console command description.
     */
    protected $description = 'Check session health for redirect loop issues';

    public function handle()
    {
        $userId = $this->option('user');

        $this->info('=== SESSION HEALTH CHECK ==='."\n");

        // 1. Check session file count
        $sessionPath = storage_path('framework/sessions');
        if (is_dir($sessionPath)) {
            $sessionCount = count(File::files($sessionPath));
            $this->line("Session files: {$sessionCount}");
            
            if ($sessionCount > 1000) {
                $this->warn("⚠ WARNING: Too many session files ({$sessionCount}) - may cause performance issues");
                $this->line("Run: php artisan session:cleanup --days=1");
            }
        }

        // 2. Check cache file count
        $cachePath = storage_path('framework/cache');
        if (is_dir($cachePath)) {
            $cacheCount = count(File::files($cachePath));
            $this->line("Cache files: {$cacheCount}");
        }

        // 3. Check for stale sessions
        $staleSessionPath = storage_path('framework/sessions');
        if (is_dir($staleSessionPath)) {
            $staleFiles = [];
            $files = File::files($staleSessionPath);
            $threshold = now()->subDays(1)->getTimestamp();
            
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    $staleFiles[] = basename($file);
                }
            }
            
            if ($staleFiles) {
                $this->warn("⚠ Found " . count($staleFiles) . " stale session files (older than 1 day)");
            }
        }

        // 4. Check active users with issues
        try {
            $users = DB::table('users')
                ->where('is_active', 1)
                ->get(['id', 'username', 'sso_user_id', 'opd_id']);
            
            $this->line("\nActive users: " . $users->count());
            
            if ($userId) {
                $user = $users->where('id', $userId)->first();
                if ($user) {
                    $this->line("\nUser #{$userId} ({$user->username}): OK");
                } else {
                    $this->error("User #{$userId} not found");
                }
            }
        } catch (\Throwable $e) {
            $this->error("Database error: " . $e->getMessage());
        }

        // 5. Check recent errors in logs
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $lines = array_reverse(explode("\n", trim(file_get_contents($logFile))));
            $redirectLoops = [];
            $sessionErrors = [];
            
            foreach (array_slice($lines, 0, 500) as $line) {
                if (strpos($line, 'Redirect') !== false || strpos($line, 'redirect') !== false) {
                    $redirectLoops[] = $line;
                }
                if (strpos($line, 'SessionHealthCheck') !== false || strpos($line, 'session') !== false) {
                    $sessionErrors[] = $line;
                }
            }
            
            if ($redirectLoops) {
                $this->warn("\n⚠ Recent redirect issues (" . count($redirectLoops) . "):");
                foreach (array_slice($redirectLoops, 0, 3) as $line) {
                    $this->line("  " . substr($line, 0, 120));
                }
            }
        }

        $this->info("\n✓ Session health check complete");
        
        return 0;
    }
}
