<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class NametagOrchestrator
{
    public function __construct(
        protected NametagRenderService $renderer
    ) {
    }

    /**
     * Generate nametag untuk 1 pegawai (mode single).
     *
     * Return array:
     * [
     *   'success'     => bool,
     *   'employee_id' => int,
     *   'message'     => string,
     *   'front_ok'    => bool,
     *   'back_ok'     => bool,
     *   'front_url'   => ?string,
     *   'back_url'    => ?string,
     *   'skipped'     => bool,
     *   'reason'      => ?string,
     * ]
     */
    public function generateSingle(Employee $employee, bool $force = true): array
    {
        // Hanya untuk pegawai AKTIF
        if ($employee->status_aktif !== 'AKTIF') {
            return [
                'success'     => false,
                'employee_id' => $employee->id,
                'message'     => 'Pegawai tidak berstatus AKTIF, nametag tidak diproses.',
                'front_ok'    => false,
                'back_ok'     => false,
                'front_url'   => null,
                'back_url'    => null,
                'skipped'     => true,
                'reason'      => 'not_active',
            ];
        }

        $result = $this->renderForEmployee($employee, $force);

        // Activity log per sisi (seperti controller lama)
        try {
            if ($result['front_processed']) {
                activity('nametag')
                    ->performedOn($employee)
                    ->event('render_front')
                    ->withProperties([
                        'force'  => $force,
                        'output' => 'nametag/front/' . $employee->id . '.png',
                        'ok'     => $result['front_ok'],
                    ])
                    ->log($result['front_ok'] ? 'Render front OK' : 'Render front gagal');
            }

            if ($result['back_processed']) {
                activity('nametag')
                    ->performedOn($employee)
                    ->event('render_back')
                    ->withProperties([
                        'force'  => $force,
                        'output' => 'nametag/back/' . $employee->id . '.png',
                        'ok'     => $result['back_ok'],
                    ])
                    ->log($result['back_ok'] ? 'Render back OK' : 'Render back gagal');
            }
        } catch (\Throwable $e) {
            Log::warning('activitylog.nametag_single_failed', [
                'employee_id' => $employee->id,
                'err'         => $e->getMessage(),
            ]);
        }

        $message = ($result['front_ok'] && $result['back_ok'])
            ? 'Nametag berhasil dibuat/diperbarui.'
            : 'Nametag gagal diproses untuk sebagian/seluruh sisi. Cek log untuk detail.';

        return [
            'success'     => (bool) ($result['front_ok'] && $result['back_ok']),
            'employee_id' => $employee->id,
            'message'     => $message,
            'front_ok'    => (bool) $result['front_ok'],
            'back_ok'     => (bool) $result['back_ok'],
            'front_url'   => $result['front_url'],
            'back_url'    => $result['back_url'],
            'skipped'     => false,
            'reason'      => null,
        ];
    }

    /**
     * Batch generate (mode lama, mirip NametagController::run yang sekarang).
     *
     * @param iterable<Employee> $employees
     * @param array $options  [
     *     'force'  => bool,
     *     'opd_id' => int|null,
     *     'limit'  => int|null,
     * ]
     *
     * Return:
     * [
     *   'ok'        => int,
     *   'fail'      => int,
     *   'notes'     => string[],
     *   'total'     => int,
     * ]
     */
    public function batchGenerate(iterable $employees, array $options = []): array
    {
        $force = (bool)($options['force'] ?? true);
        $opdId = $options['opd_id'] ?? null;
        $limit = $options['limit']  ?? null;

        $employees = collect($employees);
        $total     = $employees->count();

        $ok    = 0;
        $fail  = 0;
        $notes = [];

        Log::info('nametag.batch_start', [
            'count' => $total,
            'opd_id'=> $opdId,
            'limit' => $limit,
            'force' => $force,
        ]);

        foreach ($employees as $e) {
            try {
                // Di batch, kita tetap hanya proses pegawai AKTIF
                if ($e->status_aktif !== 'AKTIF') {
                    $notes[] = "ID {$e->id} dilewati (status tidak AKTIF)";
                    Log::info('nametag.batch_item_skipped_not_active', [
                        'employee_id' => $e->id,
                    ]);
                    continue;
                }

                $res = $this->renderForEmployee($e, $force);

                // Activity log batch item (seperti sebelumnya)
                try {
                    activity('nametag')
                        ->performedOn($e)
                        ->event('batch_item')
                        ->withProperties([
                            'front_ok' => $res['front_ok'],
                            'back_ok'  => $res['back_ok'],
                            'front'    => 'nametag/front/' . $e->id . '.png',
                            'back'     => 'nametag/back/' . $e->id . '.png',
                            'force'    => $force,
                        ])
                        ->log($res['front_ok'] && $res['back_ok'] ? 'Batch item OK' : 'Batch item gagal');
                } catch (\Throwable $ex) {
                    Log::warning('activitylog.nametag_batch_item_failed', [
                        'employee_id' => $e->id,
                        'err'         => $ex->getMessage(),
                    ]);
                }

                if ($res['front_ok'] && $res['back_ok']) {
                    $ok++;
                } else {
                    $fail++;
                    $notes[] = "ID {$e->id} gagal (front: " . ($res['front_ok'] ? 'ok' : 'x') . ", back: " . ($res['back_ok'] ? 'ok' : 'x') . ")";
                    Log::warning('nametag.batch_item_failed', [
                        'employee_id' => $e->id,
                        'front_ok'    => $res['front_ok'],
                        'back_ok'     => $res['back_ok'],
                    ]);
                }
            } catch (\Throwable $th) {
                report($th);
                $fail++;
                $notes[] = "ID {$e->id} error: " . $th->getMessage();
                Log::error('nametag.batch_exception', [
                    'employee_id' => $e->id,
                    'err'         => $th->getMessage(),
                ]);
            }
        }

        try {
            activity('nametag')
                ->event('batch_done')
                ->withProperties([
                    'ok'    => $ok,
                    'fail'  => $fail,
                    'total' => $total,
                    'opd'   => $opdId,
                    'limit' => $limit,
                    'force' => $force,
                ])
                ->log('Batch nametag selesai');
        } catch (\Throwable $e) {
            Log::warning('activitylog.nametag_batch_done_failed', [
                'err' => $e->getMessage(),
            ]);
        }

        Log::info('nametag.batch_end', [
            'ok'    => $ok,
            'fail'  => $fail,
            'total' => $total,
        ]);

        return [
            'ok'    => $ok,
            'fail'  => $fail,
            'notes' => $notes,
            'total' => $total,
        ];
    }

    /**
     * Worker internal untuk render satu pegawai:
     *  - cek & siapkan folder output
     *  - panggil render front/back
     *  - hitung front_ok/back_ok
     *  - bangun URL front/back
     *
     * Return:
     * [
     *   'front_ok'        => bool,
     *   'back_ok'         => bool,
     *   'front_processed' => bool,
     *   'back_processed'  => bool,
     *   'front_path'      => string,
     *   'back_path'       => string,
     *   'front_url'       => ?string,
     *   'back_url'        => ?string,
     * ]
     */
    protected function renderForEmployee(Employee $employee, bool $force = true): array
    {
        // Pastikan folder output ada
        [$frontDir, $backDir] = $this->ensureOutputDirectories();

        $frontOut = $frontDir . '/' . $employee->id . '.png';
        $backOut  = $backDir  . '/' . $employee->id . '.png';

        // Ambil konfigurasi template dari config
        $tplFront = (string) config('nametag.templates.front.background', '');
        $tplBack  = (string) config('nametag.templates.back.background', '');

        if (! $tplFront) {
            Log::error('nametag.front_template_not_configured', [
                'employee_id' => $employee->id,
            ]);
        }

        if (! $tplBack) {
            Log::error('nametag.back_template_not_configured', [
                'employee_id' => $employee->id,
            ]);
        }

        Log::info('nametag.render_start', [
            'employee_id' => $employee->id,
            'force'       => $force,
            'tpl_front'   => $tplFront,
            'tpl_back'    => $tplBack,
            'front_out'   => $frontOut,
            'back_out'    => $backOut,
            'front_exists'=> is_file($frontOut),
            'back_exists' => is_file($backOut),
        ]);

        $okFront        = true;
        $okBack         = true;
        $frontProcessed = false;
        $backProcessed  = false;

        try {
            // FRONT
            if ($force || ! is_file($frontOut)) {
                $okFront        = $this->renderer->renderFront($employee, $tplFront ?: null);
                $frontProcessed = true;
            }

            // BACK
            if ($force || ! is_file($backOut)) {
                $okBack        = $this->renderer->renderBack($employee, $tplBack ?: null);
                $backProcessed = true;
            }
        } catch (\Throwable $e) {
            Log::error('nametag.render_exception', [
                'employee_id' => $employee->id,
                'err'         => $e->getMessage(),
            ]);
            $okFront = false;
            $okBack  = false;
        }

        $frontUrl = is_file($frontOut)
            ? asset('nametag/front/' . $employee->id . '.png') . '?v=' . filemtime($frontOut)
            : null;

        $backUrl = is_file($backOut)
            ? asset('nametag/back/' . $employee->id . '.png') . '?v=' . filemtime($backOut)
            : null;

        Log::info('nametag.render_result', [
            'employee_id'    => $employee->id,
            'force'          => $force,
            'front_ok'       => $okFront,
            'back_ok'        => $okBack,
            'front_processed'=> $frontProcessed,
            'back_processed' => $backProcessed,
            'front_out'      => $frontOut,
            'back_out'       => $backOut,
            'front_url'      => $frontUrl,
            'back_url'       => $backUrl,
        ]);

        return [
            'front_ok'        => (bool) $okFront,
            'back_ok'         => (bool) $okBack,
            'front_processed' => (bool) $frontProcessed,
            'back_processed'  => (bool) $backProcessed,
            'front_path'      => $frontOut,
            'back_path'       => $backOut,
            'front_url'       => $frontUrl,
            'back_url'        => $backUrl,
        ];
    }

    /**
     * Pastikan folder output untuk front/back ada.
     *
     * @return array{0:string,1:string} [$frontDir, $backDir]
     */
    protected function ensureOutputDirectories(): array
    {
        $frontDir = public_path('nametag/front');
        $backDir  = public_path('nametag/back');

        if (! File::isDirectory($frontDir)) {
            File::makeDirectory($frontDir, 0755, true);
        }
        if (! File::isDirectory($backDir)) {
            File::makeDirectory($backDir, 0755, true);
        }

        return [$frontDir, $backDir];
    }
}
