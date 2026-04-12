<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\EmployeeRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use App\Models\EmployeeQrToken;
use App\Models\EmployeeScanLog;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepository $repo
    ) {}

    /* ============================================================
       CREATE
       ============================================================ */

    public function create(array $data, $user): Employee
    {
        $this->assertUser($user);

        $data = $this->normalizeNameDegree($data);
        $this->fixOpdUnitRelations($data, $user);

        unset($data['status_aktif'], $data['foto']);

        return DB::transaction(function () use ($data) {
            try {
                return $this->repo->create($data);
            } catch (QueryException $e) {
                $this->throwIfDuplicateActiveNip($e);
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
        });
    }

    /* ============================================================
       UPDATE
       ============================================================ */

    public function update(Employee $employee, array $data, $user): Employee
    {
        $this->assertUser($user);

        $data = $this->normalizeNameDegree($data);
        $this->fixOpdUnitRelations($data, $user, $employee);

        unset($data['status_aktif'], $data['foto']);

        return DB::transaction(function () use ($employee, $data) {
            try {
                return $this->repo->update($employee, $data);
            } catch (QueryException $e) {
                $this->throwIfDuplicateActiveNip($e);
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
        });
    }

    /* ============================================================
       DELETE
       ============================================================ */

    public function delete(Employee $employee): void
    {
        if ($employee->status_aktif === 'AKTIF') {
            throw new RuntimeException('Pegawai masih AKTIF. Nonaktifkan terlebih dahulu.');
        }

        $employee->delete();
    }

    /**
     * Hard delete: remove all traces of the employee including related
     * qr tokens, scan logs, generated nametag files, jobs and photos.
     */
    public function forceDeleteCompletely(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $id = (int) $employee->id;

            // 1) Delete scan logs related to this employee's tokens
            try {
                $tokenIds = EmployeeQrToken::where('employee_id', $id)->pluck('id')->all();
                if (!empty($tokenIds)) {
                    EmployeeScanLog::whereIn('token_id', $tokenIds)->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('forceDelete.scanlog_delete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
            }

            // 2) Force delete QR tokens (ensure removed even if SoftDeletes used)
            try {
                EmployeeQrToken::where('employee_id', $id)->forceDelete();
            } catch (\Throwable $e) {
                Log::warning('forceDelete.qr_delete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
            }

            // 3) Remove nametag generated files (front/back)
            try {
                $front = public_path("nametag/front/{$id}.png");
                $back  = public_path("nametag/back/{$id}.png");
                if (is_file($front)) { @unlink($front); }
                if (is_file($back))  { @unlink($back); }
            } catch (\Throwable $e) {
                Log::warning('forceDelete.nametag_unlink_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
            }

            // 4) Remove related queue jobs (nametag queue payloads referencing employee_id)
            try {
                DB::table('jobs')
                    ->where('queue', 'nametag')
                    ->where('payload', 'like', '%"employee_id":' . $id . '%')
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('forceDelete.jobs_delete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
            }

            // 5) Delete derived photo files via EmployeePhotoService if available
            try {
                if (app()->bound(\App\Services\EmployeePhotoService::class)) {
                    app(\App\Services\EmployeePhotoService::class)->deleteAll($employee);
                } else {
                    // fallback: try known paths
                    $paths = [
                        $employee->foto_path ? public_path($employee->foto_path) : null,
                        public_path("uploads/employees/clean/{$id}.png"),
                        public_path("uploads/employees/nametag/{$id}.png"),
                        public_path("uploads/employees/{$id}.png"),
                    ];
                    foreach ($paths as $p) if ($p && is_file($p)) @unlink($p);
                }
            } catch (\Throwable $e) {
                Log::warning('forceDelete.photo_delete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
            }

            // 6) Finally, force delete the employee record
            try {
                // activity before deletion
                try {
                    activity('employee')
                        ->performedOn($employee)
                        ->causedBy(auth()->user())
                        ->withProperties(['operation' => 'force_delete', 'employee_id' => $id])
                        ->log('employee.force_delete.requested');
                } catch (\Throwable $e) {
                    Log::warning('forceDelete.activity_request_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
                }

                $employee->forceDelete();

                // log completion
                Log::info('forceDelete.completed', ['employee_id' => $id]);
                try {
                    activity('employee')
                        ->causedBy(auth()->user())
                        ->withProperties(['operation' => 'force_delete', 'employee_id' => $id])
                        ->log('employee.force_delete.completed');
                } catch (\Throwable $e) {
                    Log::warning('forceDelete.activity_complete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
                }
            } catch (\Throwable $e) {
                Log::error('forceDelete.employee_force_delete_failed', ['employee_id' => $id, 'err' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    /* ============================================================
       ACTIVATE / DEACTIVATE
       ============================================================ */

    public function activate(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee) {
            try {
                $employee->update(['status_aktif' => 'AKTIF']);
                return $employee;
            } catch (QueryException $e) {
                $this->throwIfDuplicateActiveNip($e);
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
        });
    }

    public function deactivate(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee) {
            $employee->update(['status_aktif' => 'NONAKTIF']);
            return $employee;
        });
    }

    /* ============================================================
       HELPER: VALIDATION
       ============================================================ */

    private function assertUser($user): void
    {
        if (! $user) {
            throw new RuntimeException('Pengguna tidak terautentik.');
        }
    }

    /* ============================================================
       HELPER: NORMALISASI NAMA
       ============================================================ */

    private function normalizeNameDegree(array $data): array
    {
        foreach (['gelar_depan', 'gelar_belakang', 'nama'] as $k) {
            if (! array_key_exists($k, $data)) {
                continue;
            }

            $v = trim((string) $data[$k]);
            $v = preg_replace('/\s+/', ' ', $v);
            $v = preg_replace('/,+/', ',', $v);
            $v = preg_replace('/\s*,\s*/', ', ', $v);

            // Apply gelar normalization (quote-escape parsing) for gelar fields
            // Store input BEFORE normalization in gelar_belakang_input for re-edit preservation
            // Store normalized result in gelar_belakang column for display/generate
            if ($k === 'gelar_belakang') {
                // Save input as-is (with quotes if user added them) for re-editing
                $data['gelar_belakang_input'] = $v;
                // Normalize for display/generate (removes quotes, applies rules)
                $v = \App\Support\NametagData::normalizeGelarPublic($v);
            } elseif ($k === 'gelar_depan') {
                $v = \App\Support\NametagData::normalizeGelarPublic($v);
            }

            $data[$k] = $v;
        }

        return $data;
    }

    /* ============================================================
       HELPER: ROLE CHECK
       ============================================================ */

    private function hasAnyRoleInsensitive($user, array $candidates): bool
    {
        $have = array_map('mb_strtolower', $user->getRoleNames()->toArray());
        $want = array_map('mb_strtolower', $candidates);

        return (bool) array_intersect($have, $want);
    }

    /* ============================================================
       HELPER: OPD / UNIT SYNC
       ============================================================ */

    private function fixOpdUnitRelations(array &$data, $user, Employee $existing = null): void
    {
        $isSuperOrOrg = $this->hasAnyRoleInsensitive($user, [
            'superadmin',
            'org_admin', 'org-admin', 'org admin',
            'admin_organisasi', 'admin organisasi',
        ]);

        $isOpdLevel = $this->hasAnyRoleInsensitive($user, [
            'admin opd', 'admin-opd', 'admin_opd',
            'verifikator opd', 'verifikator-opd', 'verifikator_opd',
        ]);

        $managed = method_exists($user, 'managedUnitIds')
            ? array_map('intval', (array) $user->managedUnitIds())
            : [];

        $isUnitLevel = ! $isSuperOrOrg && ! $isOpdLevel && ! empty($managed);

        if (! empty($data['opd_unit_id'])) {
            $unit = DB::table('opd_units')
                ->where('id', $data['opd_unit_id'])
                ->whereNull('deleted_at')
                ->first();

            if (! $unit) {
                throw new RuntimeException('Unit OPD tidak ditemukan.');
            }

            if ($isSuperOrOrg) {
                $data['opd_id'] ??= (int) $unit->opd_id;

                if ((int) $data['opd_id'] !== (int) $unit->opd_id) {
                    throw new RuntimeException('Unit OPD tidak sesuai dengan OPD.');
                }

                $data['nama_unit_opd'] ??= $unit->nama;
                return;
            }

            if ((int) $unit->opd_id !== (int) $user->opd_id) {
                throw new RuntimeException('Unit OPD harus berada dalam OPD Anda.');
            }

            if ($isUnitLevel && ! in_array((int) $data['opd_unit_id'], $managed, true)) {
                throw new RuntimeException('Anda tidak memiliki akses ke unit tersebut.');
            }

            $data['opd_id']     = $user->opd_id;
            $data['nama_unit_opd'] ??= $unit->nama;
            return;
        }

        if ($isUnitLevel) {
            throw new RuntimeException('Unit OPD wajib dipilih untuk akun level unit.');
        }

        if (empty($data['opd_id'])) {
            $data['opd_id'] = $existing?->opd_id ?? $user->opd_id;
        }

        if ($existing) {
            if (! array_key_exists('nama_unit_opd', $data)) {
                $data['nama_unit_opd'] = $existing->nama_unit_opd;
            }

            if (! array_key_exists('opd_unit_id', $data)) {
                $data['opd_unit_id'] = $existing->opd_unit_id;
            }
        }
    }

    /* ============================================================
       HELPER: DUPLICATE ACTIVE NIP
       ============================================================ */

    private function throwIfDuplicateActiveNip(QueryException $e): void
    {
        if (
            ($e->errorInfo[0] ?? null) === '23000'
            && str_contains($e->getMessage(), 'uq_employees_nip_active_once')
        ) {
            throw new RuntimeException(
                'Sudah ada entri AKTIF dengan NIP tersebut.'
            );
        }
    }
}
