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

class RenderNametagsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int[] */
    public array $employeeIds;
    public int $userId;
    public string $batchId;
    public array $options;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(array $employeeIds, int $userId, string $batchId, array $options = [])
    {
        // normalisasi ID (unique & int)
        $this->employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $this->userId      = $userId;
        $this->batchId     = $batchId;
        $this->options     = $options;
    }

    public static function progressKey(int $userId, string $batchId): string
    {
        return "nametag:progress:{$userId}:{$batchId}";
    }

    public function handle(NametagRenderService $renderer): void
    {
        $key   = self::progressKey($this->userId, $this->batchId);
        $total = count($this->employeeIds);

        if ($total === 0) {
            Cache::put($key, [
                'total'       => 0,
                'done'        => 0,
                'fail'        => 0,
                'eta'         => 0,
                'started_at'  => now()->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'status'      => 'empty',
            ], now()->addHours(2));

            Log::info('nametag: batch empty', [
                'batch'   => $this->batchId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $done      = 0;
        $fail      = 0;
        $startedAt = now()->toIso8601String();

        // override progress awal dari controller → status running
        // preserve any initial ETA written by the controller (queued estimate)
        $existing = Cache::get($key, []);
        $preservedEta = $existing['eta'] ?? null;
        Cache::put($key, [
            'total'       => $total,
            'done'        => 0,
            'fail'        => 0,
            'eta'         => $preservedEta,
            'started_at'  => $startedAt,
            'finished_at' => null,
            'status'      => 'running',
        ], now()->addHours(2));

        $t0 = microtime(true);

        // Resolve template satu kali di awal
        $tplFront = $this->resolveTemplate(config('nametag.templates.front.background'), 'PolosFront.png');
        $tplBack  = $this->resolveTemplate(config('nametag.templates.back.background'),  'PolosBack.png');

        $onlyFront = (bool) ($this->options['only_front'] ?? false);
        $onlyBack  = (bool) ($this->options['only_back']  ?? false);

        // jaga-jaga kalau ada yang manggil langsung job tanpa validasi controller
        if ($onlyFront && $onlyBack) {
            Log::warning('nametag: job called with both only_front & only_back = true', [
                'batch'   => $this->batchId,
                'user_id' => $this->userId,
            ]);

            Cache::put($key, [
                'total'       => $total,
                'done'        => 0,
                'fail'        => $total,
                'eta'         => 0,
                'started_at'  => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'status'      => 'invalid_mode',
            ], now()->addHours(2));

            return;
        }

        $needFront = !$onlyBack;
        $needBack  = !$onlyFront;

        // Kalau template yang diwajibkan tidak ada sama sekali, jangan buang waktu looping
        if (($needFront && !$tplFront) || ($needBack && !$tplBack)) {
            Log::error('nametag: required template missing, abort batch', [
                'batch'      => $this->batchId,
                'user_id'    => $this->userId,
                'need_front' => $needFront,
                'need_back'  => $needBack,
                'tpl_front'  => $tplFront,
                'tpl_back'   => $tplBack,
            ]);

            Cache::put($key, [
                'total'       => $total,
                'done'        => 0,
                'fail'        => $total,
                'eta'         => 0,
                'started_at'  => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'status'      => 'template_missing',
            ], now()->addHours(2));

            return;
        }

        foreach ($this->employeeIds as $empId) {
            $tick = microtime(true);

            try {
                /** @var Employee|null $e */
                $e = Employee::query()->find($empId);

                if (!$e) {
                    Log::warning('nametag: employee not found in batch', [
                        'employee_id' => $empId,
                        'batch'       => $this->batchId,
                    ]);
                    $fail++;
                } else {
                    // Guard aktif: utamakan status_aktif = 'AKTIF'
                    if (!$this->isEmployeeActive($e)) {
                        Log::info('nametag: skip non-aktif', [
                            'employee_id' => $empId,
                            'batch'       => $this->batchId,
                        ]);
                        $fail++;
                    } else {
                        $okF = $onlyBack  ? true : $renderer->renderFront($e, $tplFront);
                        $okB = $onlyFront ? true : $renderer->renderBack($e,  $tplBack);

                        if ($okF && $okB) {
                            $done++;
                        } else {
                            $fail++;
                            Log::warning('nametag: render failed for employee', [
                                'employee_id' => $empId,
                                'ok_front'    => $okF,
                                'ok_back'     => $okB,
                                'batch'       => $this->batchId,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $th) {
                $fail++;
                Log::error('nametag: render error', [
                    'employee_id' => $empId,
                    'batch'       => $this->batchId,
                    'err'         => $th->getMessage(),
                ]);
            }

            // Update progress setiap item
            $processed = $done + $fail;
            $spentAll  = microtime(true) - $t0;
            $perItem   = $processed ? $spentAll / $processed : 0.0;
            $eta       = (int) round($perItem * max(0, $total - $processed));

            Cache::put($key, [
                'total'        => $total,
                'done'         => $done,
                'fail'         => $fail,
                'eta'          => $eta,
                'started_at'   => $startedAt,
                'finished_at'  => null,
                'status'       => 'running',
                'last_item_ms' => (int) round((microtime(true) - $tick) * 1000),
            ], now()->addHours(2));
        }

        $status = $fail > 0 ? 'finished_with_errors' : 'finished';

        Cache::put($key, [
            'total'       => $total,
            'done'        => $done,
            'fail'        => $fail,
            'eta'         => 0,
            'started_at'  => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'status'      => $status,
        ], now()->addHours(2));

        Log::info('nametag: batch finished', [
            'batch'   => $this->batchId,
            'user_id' => $this->userId,
            'total'   => $total,
            'done'    => $done,
            'fail'    => $fail,
            'status'  => $status,
        ]);
    }

    /**
     * Cari template dengan beberapa fallback lokasi.
     */
    private function resolveTemplate($cfgPath, string $fileName): ?string
    {
        $candidates = [];

        // path eksplisit dari config (boleh absolute / relative)
        if ($cfgPath && is_string($cfgPath)) {
            $candidates[] = $cfgPath;
        }

        // default di public/templates
        $candidates[] = public_path("templates/{$fileName}");

        // fallback: root hosting (shared hosting style)
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

        Log::warning('nametag: template not found', [
            'file'       => $fileName,
            'cfg_path'   => $cfgPath,
            'candidates' => $candidates,
        ]);

        return null;
    }

    /**
     * Pegawai aktif:
     * - Prioritas: status_aktif = 'AKTIF'
     * - Fallback jaga-jaga: is_active=1, status='AKTIF', atau kolom *_kepegawaian mengandung 'AKTIF'
     */
    private function isEmployeeActive(Employee $e): bool
    {
        try {
            if (Schema::hasColumn('employees', 'status_aktif')) {
                $sa = (string) ($e->status_aktif ?? '');
                if ($sa !== '') {
                    return strtoupper($sa) === 'AKTIF';
                }
            }

            if (Schema::hasColumn('employees', 'is_active')
                && (int) ($e->is_active ?? 0) === 1
            ) {
                return true;
            }

            if (Schema::hasColumn('employees', 'status')) {
                $s = strtoupper((string) ($e->status ?? ''));
                if ($s === 'AKTIF' || str_starts_with($s, 'AKTIF')) {
                    return true;
                }
            }

            foreach (['kedudukan', 'kedudukan_kepegawaian', 'status_kepegawaian'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $v = strtoupper((string) ($e->{$col} ?? ''));
                    if ($v !== '' && str_contains($v, 'AKTIF')) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Throwable $t) {
            Log::warning('nametag: isEmployeeActive check failed', [
                'employee_id' => $e->id ?? null,
                'err'         => $t->getMessage(),
            ]);

            // Kalau gagal cek skema, jangan blokir render
            return true;
        }
    }
}
