<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveOpdContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            session([
                'current_opd_id'  => null,
                'current_unit_id' => null,
                'opd_locked'      => false,
            ]);
            return $next($request);
        }

        $roleNames = collect($user->getRoleNames())->map(fn($r) => mb_strtolower($r))->all();
        $isSuper = in_array('superadmin', $roleNames, true);
        $isGlobal = in_array('org_admin', $roleNames, true) 
            || in_array('org admin', $roleNames, true)
            || in_array('org-admin', $roleNames, true)
            || in_array('admin_organisasi', $roleNames, true)
            || in_array('admin organisasi', $roleNames, true)
            || in_array('admin-organisasi', $roleNames, true)
            || in_array('admin bagor', $roleNames, true)
            || in_array('admin-bagor', $roleNames, true)
            || in_array('verifikator global', $roleNames, true)
            || in_array('verifikator-global', $roleNames, true)
            || in_array('verifikator_global', $roleNames, true);

        $locked = session('opd_locked');

        // default: role non-global harus locked
        if ($locked === null) {
            $locked = (!$isSuper && !$isGlobal);
            session(['opd_locked' => $locked]);
        }

        if ($locked || (!$isSuper && !$isGlobal)) {
            // paksa sesuai user
            session([
                'current_opd_id'  => $user->opd_id,
                'current_unit_id' => $user->opd_unit_id,
                'opd_locked'      => true,
            ]);
        } else {
            // global: kalau belum ada context, set default ke OPD user
            if (!session()->has('current_opd_id')) {
                session([
                    'current_opd_id'  => $user->opd_id,
                    'current_unit_id' => $user->opd_unit_id,
                    'opd_locked'      => false,
                ]);
            }
        }

        return $next($request);
    }
}
