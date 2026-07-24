<?php

namespace App\Services;

use App\Http\Requests\EmployeeStoreRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EmployeeOrchestrator
{
    protected string $requestId;

    public function __construct(
        protected EmployeeService      $service,
        protected EmployeePhotoService $photo,
    ) {
        $this->requestId = (string) Str::uuid();
    }

    /* ===============================================================
     | DELETE (soft & hard)
     =============================================================== */

    public function delete(Employee $employee): array
    {
        try {
            $this->service->delete($employee);
            $this->audit($employee, 'delete', ['source' => 'soft']);
            return $this->ok($employee, 'Pegawai berhasil dihapus (soft delete).');
        } catch (\Throwable $e) {
            $this->logError('employee.delete.failed', $e, ['employee_id' => $employee->id]);
            return $this->fail('Gagal menghapus pegawai.', $e, $employee);
        }
    }

    public function forceDelete(Employee $employee): array
    {
        try {
            $this->service->forceDeleteCompletely($employee);
            $this->audit($employee, 'force_delete', ['source' => 'hard']);
            return $this->ok($employee, 'Pegawai dihapus permanen beserta file dan token terkait.');
        } catch (\Throwable $e) {
            $this->logError('employee.force_delete.failed', $e, ['employee_id' => $employee->id]);
            return $this->fail('Gagal menghapus pegawai secara permanen.', $e, $employee);
        }
    }

    /* ===============================================================
     | CREATE
     =============================================================== */

    public function createWithMedia(EmployeeStoreRequest $request): array
    {
        $data  = $request->validated();
        $actor = $request->user();

        DB::beginTransaction();

        try {
            $employee = $this->service->create($data, $actor);

            $this->handlePhotoOrFail($request, $employee);
            $this->handleSkOrFail($request->file('sk_file'), $employee);

            // Ensure business flow is enforced for newly created employees as well:
            // original -> rembg -> compose to jabatan color -> persist
            try {
                $this->photo->ensureProcessed($employee);
            } catch (\Throwable $e) {
                $this->logError('employee.photo.ensure_processed.failed', $e, [
                    'employee_id' => $employee->id,
                ]);
            }

            DB::commit();

            $this->audit($employee, 'create', [
                'actor_id' => $actor?->id,
                'source'   => 'manual',
                'payload'  => Arr::only($data, ['nip','nama','opd_id','opd_unit_id']),
            ]);

            return $this->ok($employee, 'Pegawai berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->logError('employee.create.failed', $e, ['payload' => $data]);

            return $this->fail('Gagal menyimpan pegawai.', $e);
        }
    }

    /* ===============================================================
     | UPDATE
     =============================================================== */

    public function updateWithMedia(EmployeeUpdateRequest $request, Employee $employee): array
    {
        $data       = $request->validated();
        $actor      = $request->user();
        $before     = $employee->replicate();
        $oldJabatan = $employee->jabatan_type;

        DB::beginTransaction();

        try {
            $employee = $this->service->update($employee, $data, $actor);

            $this->handlePhotoOrFail($request, $employee, $oldJabatan);
            $this->handleSkOrFail($request->file('sk_file'), $employee, true);

            // Sinkron background jika hanya jabatan berubah
            $hasCleanedInput = trim((string) $request->input('foto_path', '')) !== '';
            if (
                ! $request->hasFile('foto')
                && ! $hasCleanedInput
                && $oldJabatan !== $employee->jabatan_type
                && ! $employee->foto_is_manual
            ) {
                $this->photo->syncBackgroundByJabatan($employee);
            }
                // Always ensure business photo flow: original -> rembg -> compose to jabatan color -> persist
                try {
                    $this->photo->ensureProcessed($employee);
                } catch (\Throwable $e) {
                    $this->logError('employee.photo.ensure_processed.failed', $e, [
                        'employee_id' => $employee->id,
                    ]);
                }

            DB::commit();

            $this->audit($employee, 'update', [
                'actor_id' => $actor?->id,
                'before'   => Arr::only($before->toArray(), ['opd_id','opd_unit_id','jabatan_type']),
                'after'    => Arr::only($employee->toArray(), ['opd_id','opd_unit_id','jabatan_type']),
            ]);

            return $this->ok($employee, 'Perubahan berhasil disimpan.');
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->logError('employee.update.failed', $e, [
                'employee_id' => $employee->id,
            ]);

            return $this->fail('Gagal menyimpan perubahan.', $e, $employee);
        }
    }

    /* ===============================================================
     | STATUS
     =============================================================== */

    public function activate(Employee $employee): array
    {
        return $this->toggleStatus($employee, true);
    }

    public function deactivate(Employee $employee): array
    {
        return $this->toggleStatus($employee, false);
    }

    protected function toggleStatus(Employee $employee, bool $active): array
    {
        DB::beginTransaction();

        try {
            $employee = $active
                ? $this->service->activate($employee)
                : $this->service->deactivate($employee);

            DB::commit();

            $this->audit($employee, $active ? 'activate' : 'deactivate');

            return $this->ok(
                $employee,
                $active ? 'Pegawai berhasil diaktifkan.' : 'Pegawai berhasil dinonaktifkan.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->logError('employee.status.failed', $e, [
                'employee_id' => $employee->id,
                'target'      => $active ? 'activate' : 'deactivate',
            ]);

            return $this->fail(
                $active
                    ? 'Gagal mengaktifkan pegawai.'
                    : 'Gagal menonaktifkan pegawai.',
                $e,
                $employee
            );
        }
    }

    /* ===============================================================
     | MEDIA (FAIL FAST)
     =============================================================== */

    protected function handlePhotoOrFail($request, Employee $employee, ?string $oldJabatan = null): void
    {
        $cleanedRelPath = trim((string) $request->input('foto_path', ''));
        $hasUpload      = $request->hasFile('foto');
        $isManual       = $request->boolean('foto_is_manual');

        if (! $hasUpload && $cleanedRelPath === '') {
            if ($request->has('foto_is_manual') && $employee->foto_is_manual !== $isManual) {
                $employee->foto_is_manual = $isManual;
                $employee->save();
            }
            return;
        }

        if ($isManual && $hasUpload) {
            $ok = $this->photo->saveManualFinalPhoto($request->file('foto'), $employee);
        } else {
            $ok = $this->photo->uploadAndProcess(
                $hasUpload ? $request->file('foto') : null,
                $employee,
                [
                    'x' => $request->integer('crop_x'),
                    'y' => $request->integer('crop_y'),
                    'w' => $request->integer('crop_width'),
                    'h' => $request->integer('crop_height'),
                ],
                $cleanedRelPath !== '' ? $cleanedRelPath : null
            );
        }

        if (! $ok) {
            throw new \RuntimeException('Gagal memproses foto pegawai.');
        }

        if (
            ! $isManual
            && $cleanedRelPath === ''
            && ($oldJabatan === null || $oldJabatan !== $employee->jabatan_type)
        ) {
            $this->photo->syncBackgroundByJabatan($employee);
        }
    }

    protected function handleSkOrFail(?UploadedFile $file, Employee $employee, bool $replace = false): void
    {
        if (! $file) return;

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'pdf') {
            throw new \RuntimeException('SK harus berupa PDF.');
        }

        $finalDir = public_path('uploads/employee_sk');
        File::ensureDirectoryExists($finalDir);

        $filename  = $employee->nip . '_' . time() . '.pdf';
        $finalPath = "{$finalDir}/{$filename}";

        if ($replace && $employee->sk_file_path) {
            $old = public_path($employee->sk_file_path);
            if ($old && is_file($old)) {
                @unlink($old);
            }
        }

        $file->move($finalDir, $filename);

        if (! is_file($finalPath)) {
            throw new \RuntimeException('Gagal menyimpan file SK.');
        }

        $employee->forceFill([
            'sk_file_path'   => "uploads/employee_sk/{$filename}",
            'sk_uploaded_at' => now(),
        ])->save();
    }

    /* ===============================================================
     | OBSERVABILITY
     =============================================================== */

    protected function audit(Employee $employee, string $event, array $extra = []): void
    {
        activity('employee')
            ->performedOn($employee)
            ->event($event)
            ->withProperties(array_merge([
                'request_id' => $this->requestId,
                'nip'        => $employee->nip,
                'opd_id'     => $employee->opd_id,
                'unit_id'    => $employee->opd_unit_id,
                'status'     => $employee->status_aktif,
            ], $extra))
            ->log("employee.{$event}");
    }

    protected function logError(string $key, \Throwable $e, array $context = []): void
    {
        Log::error($key, array_merge($context, [
            'request_id' => $this->requestId,
            'exception'  => get_class($e),
            'message'    => $e->getMessage(),
        ]));
    }

    protected function ok(Employee $employee, string $message): array
    {
        return [
            'success'  => true,
            'employee' => $employee,
            'message'  => $message,
            'error'    => null,
        ];
    }

    protected function fail(string $message, \Throwable $e, ?Employee $employee = null): array
    {
        return [
            'success'  => false,
            'employee' => $employee,
            'message'  => $message,
            'error'    => app()->environment('production') ? null : $e->getMessage(),
        ];
    }
}
