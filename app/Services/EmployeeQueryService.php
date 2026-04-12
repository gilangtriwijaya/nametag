<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeQueryService
{
    /* ============================================================
     * Helpers role
     * ============================================================
     */

    /**
     * Cek apakah user punya salah satu role (case-insensitive).
     */
    private function hasAnyRoleInsensitive(User $user, array $candidates): bool
    {
        $have = array_map('mb_strtolower', $user->getRoleNames()->toArray());
        $want = array_map('mb_strtolower', $candidates);

        return (bool) array_intersect($have, $want);
    }

    /* ============================================================
     * Query index
     * ============================================================
     */

    /**
     * Query dasar untuk index pegawai, sudah otomatis scope ke OPD / unit
     * sesuai role user & filter request.
     *
     * @param  Request $request
     * @param  User    $user
     * @param  int|null $contextOpdId   OPD dari konteks (mis. session current_opd_id)
     * @param  bool    $opdLocked       Apakah konteks OPD dikunci (mis. session opd_locked)
     */
    public function queryIndex(
        Request $request,
        User $user,
        ?int $contextOpdId = null,
        bool $opdLocked = false
    ): Builder {
        $q      = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', '')); // AKTIF / NONAKTIF / ''
        $opdId  = $request->query('opd_id');                    // hanya super/org admin yang bisa override
        $unitId = $request->query('opd_unit_id');
        $unitKerjaId = $request->query('unit_kerja_id');
        $opdParentOnly = (bool) $request->query('opd_parent_only', 0); // Filter untuk OPD induk saja

        $builder = Employee::query()
            ->with(['opd', 'opdUnit', 'unitKerja'])
            ->whereNull('employees.deleted_at');

        // ===== Deteksi role utama =====
        $isSuper = $this->hasAnyRoleInsensitive($user, ['superadmin']);
        $isOrg   = $this->hasAnyRoleInsensitive($user, [
            'org admin', 'org_admin', 'org-admin',
            'admin_organisasi', 'admin organisasi', 'admin bagian organisasi',
        ]);
        $isAdminOpd = $this->hasAnyRoleInsensitive($user, [
            'admin opd', 'admin-opd', 'admin_opd', 'Admin OPD',
        ]);
        $isVerOpd = $this->hasAnyRoleInsensitive($user, [
            'verifikator opd', 'verifikator-opd', 'verifikator_opd',
            'Verifikator OPD', 'verifikator',
        ]);

        // DEBUG: Log untuk diagnosa
        if ($opdId) {
            \Log::debug('EmployeeQueryService.queryIndex', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_roles' => $user->getRoleNames()->toArray(),
                'isSuper' => $isSuper,
                'isOrg' => $isOrg,
                'isAdminOpd' => $isAdminOpd,
                'requested_opd_id' => $opdId,
            ]);
        }

        // ===== Scope OPD =====
        if ($isSuper || $isOrg) {
            // Super / Admin Organisasi: bisa lihat semua OPD
            if ($opdLocked && $contextOpdId) {
                $builder->where('employees.opd_id', (int) $contextOpdId);
            } elseif ($opdId) {
                $builder->where('employees.opd_id', (int) $opdId);
            }
        } else {
            // Selain itu: wajib di dalam OPD sendiri (kalau punya opd_id)
            if ($user->opd_id) {
                $builder->where('employees.opd_id', (int) $user->opd_id);
            }
        }

        // ===== SSO-per-app OPD locking enforcement =====
        // IMPORTANT: Admin Organisasi should NEVER be restricted by SSO filtering
        // They are global users and should see employees based on their explicit filter choice
        
        // Only apply SSO filtering for non-global roles
        $isSsoExcluded = ($isSuper || $isOrg);  // Super + admin organisasi are ALWAYS excluded
        
        if (!$isSsoExcluded) {
            try {
                $appCode = (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
                $allowed = $user->ssoAllowedOpdIds($appCode);
                if (!empty($allowed)) {
                    $builder->whereIn('employees.opd_id', $allowed);
                }
            } catch (\Throwable $e) {
                // fail safe: do not block listing on DB errors
            }
        }

        // ===== Scope Unit Kerja =====
        // Untuk akun level-unit, biasanya punya managedUnitIds().
        $managedUnitIds = method_exists($user, 'managedUnitIds')
            ? array_map('intval', (array) $user->managedUnitIds())
            : [];

        /**
         * Catatan penting:
         * - Admin OPD & Verifikator OPD: TIDAK boleh dibatasi ke unit saja,
         *   supaya tetap bisa lihat pegawai level OPD (opd_unit_id NULL).
         * - Admin Unit / Verifikator Unit: justru dibatasi ke unit yang dikelola.
         *
         * Karena di sini kita tidak punya helper isAdminUnit/isVerUnit,
         * kita pakai logika:
         *   "kalau dia punya managedUnitIds DAN bukan Super/Org/AdminOpd/VerOpd,
         *    berarti dia akun level unit".
         */
        $isUnitLevelAccount = ! empty($managedUnitIds)
            && ! ($isSuper || $isOrg || $isAdminOpd || $isVerOpd);

        if ($isUnitLevelAccount) {
            // Admin Unit / Verifikator Unit → hanya pegawai di unit yang dikelola
            $builder->whereIn('employees.opd_unit_id', $managedUnitIds);
        } elseif ($unitId) {
            // Filter manual via dropdown (misal superadmin pilih Unit tertentu)
            $builder->where('employees.opd_unit_id', (int) $unitId);
        } elseif ($opdParentOnly && $opdId) {
            // Filter untuk menampilkan hanya pegawai dari OPD induk (tanpa unit OPD)
            $builder->whereNull('employees.opd_unit_id');
        }

        // ===== Filter Unit Kerja (normalized) =====
        if ($unitKerjaId) {
            $builder->where('employees.unit_kerja_id', (int) $unitKerjaId);
        }

        // ===== Filter status aktif =====
        if ($status !== '') {
            $builder->where('employees.status_aktif', $status);
        }

        // ===== Pencarian bebas =====
        if ($q !== '') {
            $builder->where(function (Builder $w) use ($q) {
                $w->where('employees.nama', 'like', "%{$q}%")
                    ->orWhere('employees.nip', 'like', "%{$q}%")
                    ->orWhere('employees.jabatan', 'like', "%{$q}%")
                    ->orWhere('employees.nama_unit_opd', 'like', "%{$q}%");
            });
        }

        // default urutan: nama asc
        $builder->orderBy('employees.nama');

        return $builder;
    }

    /**
     * Sisipkan info QR terakhir untuk setiap pegawai dalam collection / paginator.
     *
     * Menambahkan properti dinamis:
     * - latest_qr_token
     * - latest_qr_status
     * - latest_qr_created_at
     */
    public function attachLatestQrTokens(LengthAwarePaginator|Collection $employees): void
    {
        if ($employees->isEmpty()) {
            return;
        }

        $ids = $employees->pluck('id')->all();

        // Ambil token terakhir per employee_id (id terbesar diasumsikan terbaru)
        $rows = DB::table('employee_qr_tokens')
            ->selectRaw('employee_id, token, status, created_at')
            ->whereIn('employee_id', $ids)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        foreach ($employees as $emp) {
            $row = $rows[$emp->id][0] ?? null;

            $emp->latest_qr_token      = $row->token      ?? null;
            $emp->latest_qr_status     = $row->status     ?? null;
            $emp->latest_qr_created_at = $row->created_at ?? null;
        }
    }
}
