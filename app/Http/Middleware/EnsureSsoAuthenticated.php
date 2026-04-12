<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class EnsureSsoAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            session(['sso.intended' => $request->fullUrl()]);
            return redirect()->route('sso.login');
        }

        // refresh ringan dari session payload (kalau ada)
        $sso = session('sso.user');
        if (is_array($sso) && !empty($sso['id'])) {
            /** @var User $u */
            $u = $request->user();

            if ((int)($u->sso_user_id ?? 0) === (int)$sso['id']) {
                $dirty = false;

                foreach ([
                    'user_type_id' => 'user_type_id',
                    'opd_id'       => 'opd_id',
                    'opd_unit_id'  => 'opd_unit_id',
                ] as $col => $key) {
                    $val = $sso[$key] ?? null;
                    if ($u->{$col} != $val) {
                        $u->{$col} = $val;
                        $dirty = true;
                    }
                }

                if (array_key_exists('is_active', $sso)) {
                    $active = (int)$sso['is_active'] === 1 ? 1 : 0;
                    if ((int)$u->is_active !== $active) {
                        $u->is_active = $active;
                        $dirty = true;
                    }
                }

                if ($dirty) $u->save();
            }
        }

        return $next($request);
    }
}
