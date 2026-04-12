<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\NametagRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RenderSingleNametagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $employeeId;
    public int $userId;
    public string $batchId;
    public array $options;

    // single-employee job: shorter timeout, allow retries
    public int $timeout = 1200;
    public int $tries   = 2;

    public function __construct(int $employeeId, int $userId, string $batchId, array $options = [])
    {
        $this->employeeId = (int) $employeeId;
        $this->userId     = $userId;
        $this->batchId    = $batchId;
        $this->options    = $options;
    }

    public function handle(NametagRenderService $renderer): void
    {
        Log::info('nametag: single job started', ['batch' => $this->batchId, 'employee' => $this->employeeId]);

        // heartbeat indicator for ops: worker ran a nametag job
        try {
            // avoid writing to file cache when filesystem is not writable
            $driver = config('cache.default');
            $okToWrite = true;
            if ($driver === 'file') {
                $dataPath = storage_path('framework/cache/data');
                $okToWrite = is_dir($dataPath) && is_writable($dataPath);
            }
            if ($okToWrite) {
                Cache::put('worker:nametag:alive', now()->toIso8601String(), 60);
            }
        } catch (\Throwable $_) {
            // ignore cache errors
        }

        // add batch context to logs
        try {
            Log::withContext(['batch' => $this->batchId, 'employee' => $this->employeeId]);
        } catch (\Throwable $_) {
            // ignore
        }

        $key = RenderNametagBatchJob::progressKey($this->userId, $this->batchId);

        $onlyFront = (bool) ($this->options['only_front'] ?? false);
        $onlyBack  = (bool) ($this->options['only_back'] ?? false);

        $startedAt = Cache::get($key . ':started_at') ?? null;
        if (!$startedAt) {
            // Ensure started_at exists on the batch key
            $batch = Cache::get($key, []);
            $batch['started_at'] = $batch['started_at'] ?? now()->toIso8601String();
            Cache::put($key, $batch, now()->addHours(2));
        }

        $doneIncrement = 0;
        $failIncrement = 0;
        $skipIncrement = 0;

        try {
            $e = Employee::find($this->employeeId);
            if (!$e) {
                Log::warning('nametag: single job employee not found', ['id' => $this->employeeId, 'batch' => $this->batchId]);
                $failIncrement = 1;
            } else {
                // If a newer job exists for this employee, skip this stale job
                try {
                    $latest = Cache::get("nametag:latest:{$this->employeeId}");
                } catch (\Throwable $_) {
                    $latest = null;
                }

                if ($latest && $latest !== $this->batchId) {
                    Log::info('nametag: skipping stale job', [
                        'employee' => $this->employeeId,
                        'job'      => $this->batchId,
                        'latest'   => $latest,
                    ]);
                    $skipIncrement = 1;
                    try {
                        $e->update(['nametag_status' => 'skipped', 'nametag_error' => 'skipped_by_newer_job']);
                    } catch (\Throwable $_) {
                        // ignore
                    }
                } else {
                    // mark processing
                    try {
                        $e->update(['nametag_status' => 'processing']);
                    } catch (\Throwable $_) {
                        // ignore
                    }

                    if (!$this->isEmployeeActive($e)) {
                        Log::info('nametag: single job skip non-aktif', ['id' => $this->employeeId, 'batch' => $this->batchId]);
                        $skipIncrement = 1;
                        try {
                            $e->update(['nametag_status' => 'ready']);
                        } catch (\Throwable $_) {
                            // ignore
                        }
                    } else {
                        $tplFront = $this->resolveTemplate(config('nametag.templates.front.background'), 'PolosFront.png');
                        $tplBack  = $this->resolveTemplate(config('nametag.templates.back.background'),  'PolosBack.png');

                        if (($onlyFront && $onlyBack) || (($onlyFront && !$tplFront) || ($onlyBack && !$tplBack))) {
                            Log::warning('nametag: single job invalid mode or missing template', ['id' => $this->employeeId, 'batch' => $this->batchId]);
                            $failIncrement = 1;
                        } else {
                            $okF = $onlyBack  ? true : $renderer->renderFront($e, $tplFront);
                            $okB = $onlyFront ? true : $renderer->renderBack($e,  $tplBack);

                            // Record to activity log (important for audit trail)
                            try {
                                if (!$onlyBack) {
                                    activity('nametag')
                                        ->performedOn($e)
                                        ->event('render_front')
                                        ->withProperties([
                                            'ok'    => $okF,
                                            'via'   => 'job',
                                            'batch' => $this->batchId,
                                        ])
                                        ->log($okF ? 'Render front OK (via job)' : 'Render front gagal (via job)');
                                }

                                if (!$onlyFront) {
                                    activity('nametag')
                                        ->performedOn($e)
                                        ->event('render_back')
                                        ->withProperties([
                                            'ok'    => $okB,
                                            'via'   => 'job',
                                            'batch' => $this->batchId,
                                        ])
                                        ->log($okB ? 'Render back OK (via job)' : 'Render back gagal (via job)');
                                }
                            } catch (\Throwable $ex) {
                                Log::warning('nametag: failed to record activity log in job', [
                                    'employee' => $this->employeeId,
                                    'batch'    => $this->batchId,
                                    'error'    => $ex->getMessage(),
                                ]);
                            }

                            if ($okF && $okB) {
                                $doneIncrement = 1;
                                try {
                                    $e->update([
                                        'nametag_status' => 'ready',
                                        'nametag_generated_at' => now(),
                                        'nametag_error' => null,
                                    ]);
                                } catch (\Throwable $ex) {
                                    // Log error but don't fail the job - render succeeded, DB update is secondary
                                    Log::warning('nametag: failed to update status to ready', [
                                        'employee' => $this->employeeId,
                                        'error' => $ex->getMessage(),
                                    ]);
                                }
                            } else {
                                $failIncrement = 1;
                                try {
                                    $e->update([
                                        'nametag_status' => 'failed',
                                        'nametag_error' => 'render_failed',
                                    ]);
                                } catch (\Throwable $_) {
                                    // ignore
                                }
                                Log::warning('nametag: render failed single', ['employee' => $this->employeeId, 'okF' => $okF, 'okB' => $okB, 'batch' => $this->batchId]);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $t) {
            $failIncrement = 1;
            Log::error('nametag: render single error', ['employee' => $this->employeeId, 'err' => $t->getMessage(), 'batch' => $this->batchId]);
        }

        // Update batch progress (persist to DB authoritative record)
        // Read-modify-write
        try {
            $batch = Cache::get($key, []);
            $total = (int) ($batch['total'] ?? 0);
            $done  = (int) ($batch['done'] ?? 0);
            $fail  = (int) ($batch['fail'] ?? 0);
            $skip  = (int) ($batch['skipped'] ?? 0);

            $done += $doneIncrement;
            $fail += $failIncrement;
            $skip += $skipIncrement;
            $processed = $done + $fail + $skip;

            $startedAt = $batch['started_at'] ?? now()->toIso8601String();
            $t0 = strtotime($startedAt);
            $spent = $t0 ? max(1, time() - $t0) : 1;
            $perItem = $processed ? max(0.1, $spent / max(1, $processed)) : ($processed ? ($spent / $processed) : 0.0);
            $eta = (int) round($perItem * max(0, $total - $processed));

            $batch['total'] = $total;
            $batch['done']    = $done;
            $batch['fail']    = $fail;
            $batch['skipped'] = $skip;
            $batch['eta']   = $eta;
            $batch['status'] = ($processed >= $total && $total > 0) ? ($fail > 0 ? 'finished_with_errors' : 'finished') : 'running';
            if ($batch['status'] === 'finished' || $batch['status'] === 'finished_with_errors') {
                $batch['finished_at'] = now()->toIso8601String();
                $batch['eta'] = 0;
            }

            Cache::put($key, $batch, now()->addHours(2));
            // if failure, append to failed ids list for retry capability
            if ($failIncrement > 0) {
                try {
                    $failedKey = $key . ':failed_ids';
                    $failed = Cache::get($failedKey, []);
                    $failed[] = $this->employeeId;
                    $failed = array_values(array_unique($failed));
                    Cache::put($failedKey, $failed, now()->addHours(24));
                } catch (\Throwable $_) {
                    // ignore
                }
            }
        } catch (\Throwable $t) {
            Log::warning('nametag: failed to update batch progress', ['err' => $t->getMessage(), 'batch' => $this->batchId]);
        }

        // Update DB authoritative batch record
        try {
            $b = \App\Models\NametagBatch::find($this->batchId);
            if ($b) {
                $b->done = ($b->done ?? 0) + $doneIncrement;
                $b->fail = ($b->fail ?? 0) + $failIncrement;
                $b->skipped = ($b->skipped ?? 0) + $skipIncrement;
                $processed = ($b->done + $b->fail + $b->skipped);
                if ($b->total > 0 && $processed >= $b->total) {
                    $b->status = $b->fail > 0 ? 'finished_with_errors' : 'finished';
                    $b->finished_at = now();
                } else {
                    $b->status = 'running';
                }
                $b->save();
            }
        } catch (\Throwable $_) {
            // ignore DB write failures but log earlier
        }

        // If batch is finished (all processed), remove from active batches registry
        try {
            $processed = ($batch['done'] ?? 0) + ($batch['fail'] ?? 0) + ($batch['skipped'] ?? 0);
            if (($batch['total'] ?? 0) > 0 && $processed >= $batch['total']) {
                $activeKey = 'nametag:active_batches';
                $list = Cache::get($activeKey, []);
                if (isset($list[$this->batchId])) {
                    unset($list[$this->batchId]);
                    Cache::put($activeKey, $list, now()->addHours(4));
                }
                // also remove per-batch employees cache (keep failed ids)
                try {
                    Cache::forget($key . ':employees');
                } catch (\Throwable $_) {
                    // ignore
                }
            }
        } catch (\Throwable $_) {
            // ignore
        }
    }

    private function resolveTemplate($cfgPath, string $fileName): ?string
    {
        $candidates = [];
        if ($cfgPath && is_string($cfgPath)) {
            $candidates[] = $cfgPath;
        }
        $candidates[] = public_path("templates/{$fileName}");
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot) {
            $candidates[] = "{$docRoot}/anambas-id/templates/{$fileName}";
            $candidates[] = "{$docRoot}/templates/{$fileName}";
        }
        foreach (array_unique($candidates) as $p) {
            $real = @realpath($p);
            if ($real && @is_file($real)) {
                return $real;
            }
        }
        return null;
    }

    private function isEmployeeActive(Employee $e): bool
    {
        static $cols = null;
        try {
            if ($cols === null) {
                $cols = [
                    'status_aktif' => Schema::hasColumn('employees', 'status_aktif'),
                    'is_active'    => Schema::hasColumn('employees', 'is_active'),
                    'status'       => Schema::hasColumn('employees', 'status'),
                    'kedudukan'    => Schema::hasColumn('employees', 'kedudukan'),
                    'kedudukan_kepegawaian' => Schema::hasColumn('employees', 'kedudukan_kepegawaian'),
                    'status_kepegawaian'    => Schema::hasColumn('employees', 'status_kepegawaian'),
                ];
            }

            if ($cols['status_aktif']) {
                $sa = (string) ($e->status_aktif ?? '');
                if ($sa !== '') {
                    return strtoupper($sa) === 'AKTIF';
                }
            }

            if ($cols['is_active'] && (int) ($e->is_active ?? 0) === 1) {
                return true;
            }

            if ($cols['status']) {
                $s = strtoupper((string) ($e->status ?? ''));
                if ($s === 'AKTIF' || str_starts_with($s, 'AKTIF')) {
                    return true;
                }
            }

            foreach (['kedudukan', 'kedudukan_kepegawaian', 'status_kepegawaian'] as $col) {
                if (!empty($cols[$col])) {
                    $v = strtoupper((string) ($e->{$col} ?? ''));
                    if ($v !== '' && str_contains($v, 'AKTIF')) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Throwable $t) {
            Log::warning('nametag: isEmployeeActive failed', ['err' => $t->getMessage(), 'emp' => $e->id ?? null]);
            return true;
        }
    }
}
