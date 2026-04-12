<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

class ListNametagStuck extends Command
{
    protected $signature = 'nametag:list-stuck';
    protected $description = 'List nametag queue jobs and employees with queued/processing statuses';

    public function handle()
    {
        $this->info('Jobs in queue (jobs table):');
        $jobs = DB::table('jobs')->where('queue', 'nametag')->orderBy('id','desc')->limit(200)->get();
        if ($jobs->isEmpty()) {
            $this->info('  (none)');
        } else {
            foreach ($jobs as $j) {
                $this->line(sprintf('  id=%s queue=%s attempts=%s reserved=%s available_at=%s', $j->id, $j->queue, $j->attempts, $j->reserved, $j->available_at));
            }
        }

        $this->info('');
        $this->info('Employees with queued/processing/skipped/failed statuses:');
        $emps = Employee::whereIn('nametag_status', ['queued','processing','skipped','failed'])->orderByDesc('id')->get();
        if ($emps->isEmpty()) {
            $this->info('  (none)');
        } else {
            foreach ($emps as $e) {
                $this->line(sprintf('  id=%s name=%s status=%s generated_at=%s error=%s', $e->id, str_replace("\n", ' ', substr($e->nama ?? $e->nama_lengkap ?? '',0,60)), $e->nametag_status, $e->nametag_generated_at ?? '-', $e->nametag_error ?? '-'));
            }
        }

        return 0;
    }
}
