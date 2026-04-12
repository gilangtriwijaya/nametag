<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RenderNametagBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $employeeIds;
    public int $userId;
    public string $batchId;
    public array $options;

    // Lightweight dispatcher
    public int $timeout = 1200;
    public int $tries   = 1;

    public function __construct(array $employeeIds, int $userId, string $batchId, array $options = [])
    {
        $this->employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $this->userId      = $userId;
        $this->batchId     = $batchId;
        $this->options     = $options;
    }

    public static function progressKey(int $userId, string $batchId): string
    {
        return "nametag:progress:{$userId}:{$batchId}";
    }

    public function handle(): void
    {
        Log::info('nametag: batch dispatcher started', ['batch' => $this->batchId, 'count' => count($this->employeeIds,)]);
        try { Log::withContext(['batch' => $this->batchId]); } catch (\Throwable $_) {}
        $total = count($this->employeeIds);

        // Initialize batch-level metadata (idempotent)
        $key = self::progressKey($this->userId, $this->batchId);
        $existing = Cache::get($key, []);

        Cache::put($key, [
            'total'       => $total,
            'done'        => $existing['done'] ?? 0,
            'fail'        => $existing['fail'] ?? 0,
            'eta'         => $existing['eta'] ?? 0,
            'started_at'  => $existing['started_at'] ?? now()->toIso8601String(),
            'finished_at' => $existing['finished_at'] ?? null,
            'status'      => 'dispatched',
        ], now()->addHours(2));

        // Cache the employee id list per-batch so the UI can inspect queued items
        try {
            Cache::put($key . ':employees', $this->employeeIds, now()->addHours(2));
            // mark each employee's latest job token so older jobs know to skip
            foreach ($this->employeeIds as $empId) {
                try { Cache::put("nametag:latest:{$empId}", $this->batchId, now()->addHours(24)); } catch (\Throwable $_) {}
            }
        } catch (\Throwable $_) {}

        // Register this batch in the active batches list
        try {
            $activeKey = 'nametag:active_batches';
            $list = Cache::get($activeKey, []);
            $list[$this->batchId] = [
                'batch' => $this->batchId,
                'user'  => $this->userId,
                'total' => $total,
                'created_at' => now()->toIso8601String(),
            ];
            Cache::put($activeKey, $list, now()->addHours(4));
        } catch (\Throwable $_) {}

        // Ensure DB record status is 'queued' -> 'running'
        try {
            $b = \App\Models\NametagBatch::find($this->batchId);
            if ($b) {
                $b->update(['status' => 'running', 'started_at' => now()]);
            }
        } catch (\Throwable $_) {}

        // Also mark DB employees as 'processing queued' -> keep them queued until worker picks them up
        try {
            if (!empty($this->employeeIds)) {
                \App\Models\Employee::whereIn('id', $this->employeeIds)
                    ->update(['nametag_status' => 'queued', 'nametag_error' => null]);
            }
        } catch (\Throwable $_) {
            // ignore
        }

        // Dispatch per-employee jobs with chunking and gentle rate control
        $chunkSize = (int) config('nametag.dispatch_chunk_size', 20);
        $delayPerChunk = (int) config('nametag.dispatch_chunk_delay_seconds', 0);

        $chunks = array_chunk($this->employeeIds, max(1, $chunkSize));
        foreach ($chunks as $i => $chunk) {
            foreach ($chunk as $empId) {
                RenderSingleNametagJob::dispatch($empId, $this->userId, $this->batchId, $this->options)
                    ->onQueue('nametag');
            }

            if ($delayPerChunk > 0 && isset($chunks[$i + 1])) {
                // small pause between chunks to avoid spikes
                sleep($delayPerChunk);
            }
        }

        Log::info('nametag: batch dispatched', [
            'batch' => $this->batchId,
            'user'  => $this->userId,
            'total' => $total,
        ]);
    }
}
