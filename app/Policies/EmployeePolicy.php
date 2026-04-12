<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /* ============================================================
     *  Helpers dasar
     * ============================================================
     */

    /** Cek role dengan toleransi case & variasi ejaan */
    private function hasAnyRoleInsensitive(User $u, array $candidates): bool
    {
        $have = array_map('mb_strtolower', $u->getRoleNames()->toArray());
        $want = array_map('mb_strtolower', $candidates);

        return (bool) array_intersect($have, $want);
    }

    private function isSuper(User $u): bool
    {
        return $this->hasAnyRoleInsensitive($u, ['superadmin']);
    }

    private function isOrgAdmin(User $u): bool
    {
        return $this->hasAnyRoleInsensitive($u, [
            'org_admin', 'org-admin', 'org admin',
            'admin bagian organisasi', 'admin organisasi', 'admin_organisasi',
        ]);
    }

    /** Verifikator global di Bagor (lintas OPD) */
    private function isAdminBagor(User $u): bool
    {
        return $this->hasAnyRoleInsensitive($u, [
            'admin_bagor', 'admin bagor',
            'verifikator bagor', 'verif bagor',
        ]);
    }

    /**
     * Verifikator global (lintas-OPD) — role names like 'verifikator global', 'verifikator-global'
     */
    private function isVerifikatorGlobal(User $u): bool
    {
        $roles = array_map('mb_strtolower', $u->getRoleNames()->toArray());
        foreach ($roles as $r) {
            if (str_contains($r, 'verifikator') && (str_contains($r, 'global') || str_contains($r, 'bagor') || str_contains($r, 'lintas'))) {
                return true;
            }
        }
        // also accept explicit names
        return $this->hasAnyRoleInsensitive($u, [
            'verifikator global', 'verifikator-global', 'verifikator_global'
        ]);
    }

    private function isAdminOpd(User $u): bool
    {
        return $this->hasAnyRoleInsensitive($u, [
            'admin opd','admin-opd','admin_opd','Admin OPD',
        ]);
    }

    private function isVerifikatorOpd(User $u): bool
    {
        return $this->hasAnyRoleInsensitive($u, [
            'verifikator opd','verifikator-opd','verifikator_opd',
            'Verifikator OPD','verifikator',
        ]);
    }

    /** Akun level unit (admin unit / verifikator unit yang seharusnya bisa manage nametag) */
    private function isAdminUnit(User $u): bool
    {
        // Include: user_unit_roles dengan role 'admin unit' DAN Spatie role 'opd' DAN 'verifikator_unit' dari SSO sync
        return (method_exists($u, 'unitRoles')
            && $u->unitRoles()->where('role', 'admin unit')->exists())
            || $this->hasAnyRoleInsensitive($u, ['opd', 'verifikator_unit', 'verifikator unit']);
    }

    /** Akun level unit (verifikator unit / admin unit) */
    private function isVerifikatorUnit(User $u): bool
    {
        // Support both user_unit_roles table AND Spatie role 'verifikator_unit' dari SSO
        return (method_exists($u, 'unitRoles')
            && $u->unitRoles()->where('role', 'verifikator unit')->exists())
            || $this->hasAnyRoleInsensitive($u, ['verifikator_unit', 'verifikator unit']);
    }

    private function inSameOpd(User $u, Employee $e): bool
    {
        return (int) $u->opd_id === (int) $e->opd_id;
    }

    private function inManagedUnits(User $u, Employee $e): bool
    {
        if (! $e->opd_unit_id) {
            return false;
        }

        $ids = method_exists($u, 'managedUnitIds')
            ? array_map('intval', (array) $u->managedUnitIds())
            : [];

        return in_array((int) $e->opd_unit_id, $ids, true);
    }

    /* ============================================================
     *  Gates utama
     * ============================================================
     */

    /** List pegawai (index) */
    public function viewAny(User $user): bool
    {
        // Global viewers
        if ($this->isSuper($user) || $this->isOrgAdmin($user) || $this->isAdminBagor($user) || $this->isVerifikatorGlobal($user)) {
            return true;
        }

        // OPD / unit level
        return $this->isAdminOpd($user)
            || $this->isVerifikatorOpd($user)
            || $this->isAdminUnit($user)
            || $this->isVerifikatorUnit($user);
    }

    /** Detail pegawai (show) */
    public function view(User $user, Employee $employee): bool
    {
        // Global bebas semua
        if ($this->isSuper($user) || $this->isOrgAdmin($user) || $this->isAdminBagor($user) || $this->isVerifikatorGlobal($user)) {
            return true;
        }

        // Admin / verifikator OPD → harus 1 OPD
        if ($this->isAdminOpd($user) && $this->inSameOpd($user, $employee)) {
            return true;
        }
        if ($this->isVerifikatorOpd($user) && $this->inSameOpd($user, $employee)) {
            return true;
        }

        // Level unit → harus unit yang dikelola
        if ($this->isAdminUnit($user) && $this->inManagedUnits($user, $employee)) {
            return true;
        }
        if ($this->isVerifikatorUnit($user) && $this->inManagedUnits($user, $employee)) {
            return true;
        }

        return false;
    }

    /** Dipakai untuk tombol "Generate" nametag per pegawai */
    public function generateNametag(User $user, Employee $employee): bool
    {
        // Only users who can edit/update (i.e. non-verifikator roles like admins)
        // are allowed to generate nametags. Verifikator roles may view but
        // are not permitted to trigger generation.
        return $this->update($user, $employee);
    }

    /** Tambah pegawai (create) */
    public function create(User $user): bool
    {
        // admin_bagor & verifikator tidak boleh buat pegawai baru
        return $this->isSuper($user)
            || $this->isOrgAdmin($user)
            || $this->isAdminOpd($user)
            || $this->isAdminUnit($user);
    }

    /** Ubah pegawai (edit/update data) */
    public function update(User $user, Employee $employee): bool
    {
        // Global editor
        if ($this->isSuper($user) || $this->isOrgAdmin($user)) {
            return true;
        }

        // Admin OPD boleh edit data pegawai di OPD-nya (unit boleh null)
        if ($this->isAdminOpd($user) && $this->inSameOpd($user, $employee)) {
            return true;
        }

        // Admin Unit boleh edit data di unit yang dikelola
        if ($this->isAdminUnit($user) && $this->inManagedUnits($user, $employee)) {
            return true;
        }

        // Verifikator (OPD/unit) TIDAK boleh edit data
        return false;
    }

    /** Hapus pegawai */
    public function delete(User $user, Employee $employee): bool
    {
        // Tidak boleh hapus pegawai yang masih AKTIF
        if ($employee->status_aktif === 'AKTIF') {
            return false;
        }

        if ($this->isSuper($user) || $this->isOrgAdmin($user)) {
            return true;
        }

        if ($this->isAdminOpd($user) && $this->inSameOpd($user, $employee)) {
            return true;
        }

        return false;
    }

    /** Restore soft delete */
    public function restore(User $user, Employee $employee): bool
    {
        if ($this->isSuper($user) || $this->isOrgAdmin($user)) {
            return true;
        }

        return $this->isAdminOpd($user) && $this->inSameOpd($user, $employee);
    }

    /** Force delete ORM */
    public function forceDelete(User $user, Employee $employee): bool
    {
        return $this->isSuper($user);
    }

    /**
     * Kelola status AKTIF/NONAKTIF (Aktifkan/Nonaktifkan Data Pegawai).
     * 
     * POLICY: Hanya GLOBAL users (lintas-OPD) yang boleh manage status
     * karena ada proses validasi global.
     * 
     * OPD-level dan Unit-level operators (admin_opd, verifikator_opd, admin_unit, verifikator_unit)
     * boleh CREATE/UPDATE data pegawai, namun TIDAK boleh manage status.
     * 
     * YANG BOLEH manage status:
     * - Superadmin
     * - Org Admin
     * - Admin Bagor
     * - Verifikator Global
     */
    public function manageStatus(User $user, Employee $employee): bool
    {
        // ONLY global users dapat manage status (validasi global requirement)
        return $this->isSuper($user) 
            || $this->isOrgAdmin($user) 
            || $this->isAdminBagor($user) 
            || $this->isVerifikatorGlobal($user);
    }
}
