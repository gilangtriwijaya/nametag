<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
    // Re-render helper
    \App\Console\Commands\ReRenderNametag::class,
        \App\Console\Commands\MirrorOpdFromSso::class,
        \App\Console\Commands\MirrorUsersFromSso::class,
        \App\Console\Commands\ResyncSsoAllowedOpds::class,
        \App\Console\Commands\CleanupImportPreviews::class,
        \App\Console\Commands\CleanupNametags::class,
        \App\Console\Commands\RembgWarmup::class,
        \App\Console\Commands\FlushNametagQueue::class,
        \App\Console\Commands\ListNametagStuck::class,
        \App\Console\Commands\CleanupStaleSessionsCommand::class,
        \App\Console\Commands\CheckSessionHealth::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Mirror OPD & units twice daily (every 12 hours)
        $schedule->command('sso:mirror-opd --only=all')->cron('0 */12 * * *');
        // Mirror users twice daily
        $schedule->command('sso:mirror-users')->cron('5 */12 * * *');
        // Resync allowed OPDs for users twice daily
        $schedule->command('sso:resync-allowed-opds')->cron('10 */12 * * *');
        // Cleanup import previews and temp upload files daily
        $schedule->command('import:cleanup --hours=24')->daily();
        // Cleanup generated nametag image files older than 3 days
        $schedule->command('nametag:cleanup --days=3')->dailyAt('02:10');
        // Cleanup stale session files daily at 3AM (prevent redirect loop from session corruption)
        $schedule->command('session:cleanup --days=7')->dailyAt('03:00');
        // Check session health daily at 4AM
        $schedule->command('session:check-health')->dailyAt('04:00')->onFailure(function () {
            \Illuminate\Support\Facades\Log::warning('[Scheduler] session:check-health failed');
        });

        // Rebuild public statistik cache hourly
        $schedule->command('statpub:rebuild')
            ->hourly()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
