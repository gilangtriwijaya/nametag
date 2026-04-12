<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApplyOrgContext
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        // default
        $opdLocked = false;
        $unitLocked = false;
        $currentOpd = session('current_opd_id');
        $currentUnit = session('current_opd_unit_id');

        if ($u) {
            if ($u->hasRole('superadmin') || $u->hasAnyRole(['org_admin','org-admin','org admin'])) {
                // bebas: gunakan session jika ada; tidak locked
                $opdLocked = false; $unitLocked = false;
            } else {
                // admin/verifikator OPD: kunci ke opd_id user
                if ($u->opd_id) {
                    $currentOpd = $u->opd_id;
                    $opdLocked = true;
                }
                // admin/ver unit: kunci ke unit + opd
                if ($u->opd_unit_id) {
                    $currentUnit = $u->opd_unit_id;
                    $unitLocked = true;
                }
            }
        }

        session([
            'current_opd_id'       => $currentOpd,
            'current_opd_unit_id'  => $currentUnit,
            'opd_locked'           => $opdLocked,
            'opd_unit_locked'      => $unitLocked,
        ]);

        return $next($request);
    }
}
