<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

class FlushNametagQueue extends Command
{
    protected $signature = 'nametag:flush-queue {--mark=skipped} {--dry-run}';
    protected $description = 'Clear nametag queue and mark affected employees';

    public function handle()
    {
        $mark = $this->option('mark') ?: 'skipped';
        $dry = (bool) $this->option('dry-run');

        $jobsCount = DB::table('jobs')->where('queue', 'nametag')->count();
        $this->info("Found {$jobsCount} jobs in queue 'nametag'.");

        $employees = Employee::whereIn('nametag_status', ['queued', 'processing'])->get();
        $this->info("Employees with queued/processing status: {$employees->count()}.");

        if ($dry) {
            $this->info('Dry-run mode: no changes made.');
            return 0;
        }

        DB::beginTransaction();
        try {
            // Mark employees as requested
            $updated = Employee::whereIn('nametag_status', ['queued', 'processing'])
                ->update(['nametag_status' => $mark, 'nametag_error' => 'cancelled:flush-queue']);

            // Delete queue jobs
            $deleted = DB::table('jobs')->where('queue', 'nametag')->delete();

            DB::commit();

            $this->info("Marked {$updated} employees as '{$mark}' and deleted {$deleted} jobs.");
            return 0;
        } catch (\Throwable $ex) {
            DB::rollBack();
            $this->error('Failed to flush queue: ' . $ex->getMessage());
            return 1;
        }
    }
}
