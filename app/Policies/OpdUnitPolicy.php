<?php

namespace App\Policies;

use App\Models\OpdUnit;
use App\Models\User;

class OpdUnitPolicy
{
    private function isSuper(User $u): bool { return $u->hasRole('superadmin'); }
    private function isOrgAdmin(User $u): bool { return $u->hasAnyRole(['org_admin','org-admin','org admin']); }
    private function isAdminOpd(User $u): bool { return $u->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']); }
    private function isVerOpd(User $u): bool   { return $u->hasAnyRole(['Verifikator OPD','verifikator opd','verifikator-opd','verifikator_opd']); }
    private function isAdminUnit(User $u): bool{ return $u->hasAnyRole(['Admin Unit','admin unit','admin-unit','admin_unit']); }
    private function isVerUnit(User $u): bool  { return $u->hasAnyRole(['Verifikator Unit','verifikator unit','verifikator-unit','verifikator_unit']); }

    public function viewAny(User $u): bool
    {
        return $this->isSuper($u) || $this->isOrgAdmin($u) || $this->isAdminOpd($u) || $this->isVerOpd($u) || $this->isAdminUnit($u) || $this->isVerUnit($u);
    }

    public function view(User $u, OpdUnit $m): bool
    {
        if ($this->isSuper($u) || $this->isOrgAdmin($u)) return true;

        if (($this->isAdminUnit($u) || $this->isVerUnit($u)) && $u->opd_unit_id === $m->id) return true;

        if (($this->isAdminOpd($u) || $this->isVerOpd($u)) && (int)$u->opd_id === (int)$m->opd_id) return true;

        return false;
    }

    public function create(User $u): bool
    {
        return $this->isSuper($u) || $this->isOrgAdmin($u) || $this->isAdminOpd($u);
    }

    public function update(User $u, OpdUnit $m): bool
    {
        if ($this->isSuper($u) || $this->isOrgAdmin($u)) return true;
        return $this->isAdminOpd($u) && (int)$u->opd_id === (int)$m->opd_id;
    }

    public function delete(User $u, OpdUnit $m): bool
    {
        if ($this->isSuper($u) || $this->isOrgAdmin($u)) return true;
        return $this->isAdminOpd($u) && (int)$u->opd_id === (int)$m->opd_id;
    }

    public function restore(User $u, OpdUnit $m): bool { return $this->update($u, $m); }
    public function forceDelete(User $u, OpdUnit $m): bool { return $this->isSuper($u); }
}
