<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                \Log::warning('[Dashboard] User not authenticated, redirecting to login');
                return redirect()->route('sso.login');
            }

            // ===== Helper: Check if user is global (Super or Admin Organisasi) =====
            $isGlobalUser = false;
            $roleNames = collect($user->getRoleNames())->map(fn($r) => mb_strtolower($r))->all();
            $isGlobalUser = in_array('superadmin', $roleNames, true) 
                || in_array('org_admin', $roleNames, true)
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
            
            $opdLocked  = !$isGlobalUser; // Global users should NOT be locked
            $currentOpd = $isGlobalUser ? session('current_opd_id') : $user?->opd_id;
            $unitLocked = $opdLocked && $user?->opd_unit_id; // terkunci sampai level unit?

            // SSO per-app allowed OPD (apply only for global-type users, not for super/admin-opd/unit types)
            $appCode = (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
            $excludedRoles = ['superadmin', 'org_admin', 'admin_organisasi', 'admin organisasi', 'admin bagor', 'admin opd', 'verifikator opd', 'admin unit', 'verifikator unit', 'org admin', 'org-admin', 'admin-organisasi', 'admin-bagor'];
            $isExcluded = $user ? (bool) array_intersect($user->getRoleNames()->toArray(), $excludedRoles) : false;
            $ssoAllowed = [];
            if ($user && ! $isExcluded) {
                try {
                    $ssoAllowed = $user->ssoAllowedOpdIds($appCode);
                } catch (\Throwable $e) {
                    \Log::warning('[Dashboard] ssoAllowedOpdIds failed: '.$e->getMessage(), ['user_id' => $user->id]);
                    $ssoAllowed = [];
                }
            }

        /* ===== Base scopes (otomatis exclude soft-deleted pada model Eloquent) ===== */
        $emp = Employee::query()
            ->when($opdLocked && $currentOpd, fn ($q) => $q->where('opd_id', $currentOpd))
            ->when($unitLocked, fn ($q) => $q->where('opd_unit_id', $user->opd_unit_id))
            ->when(!empty($ssoAllowed), fn ($q) => $q->whereIn('opd_id', $ssoAllowed));

        $usr = User::query()
            ->when($opdLocked && $currentOpd, fn ($q) => $q->where('opd_id', $currentOpd))
            ->when($unitLocked, fn ($q) => $q->where('opd_unit_id', $user->opd_unit_id))
            ->when(!empty($ssoAllowed), fn ($q) => $q->whereIn('opd_id', $ssoAllowed));

        /* ===== KPI ===== */
        $kpi = [
            'total_opd'    => $opdLocked ? (int) (!!$currentOpd) : Opd::count(),
            'total_units'  => $opdLocked
                                ? OpdUnit::where('opd_id', $currentOpd)->count()
                                : OpdUnit::count(),
            'total_users'  => (clone $usr)->count(),
            'emp_all'      => (clone $emp)->count(),
            'emp_active'   => (clone $emp)->where('status_aktif', 'AKTIF')->count(),
            'emp_inactive' => (clone $emp)->where('status_aktif', 'NONAKTIF')->count(),
            // Pegawai yang punya ≥1 token aktif (distinct per-pegawai)
            'nametag_done' => (clone $emp)->whereHas('qrTokens', fn ($q) => $q->where('status', 'active'))->count(),
        ];

        // Login today vs yesterday
        $today     = Carbon::today();
        $yesterday = (clone $today)->subDay();

        $kpi['login_today']     = (clone $usr)->whereDate('last_login_at', $today)->count();
        $kpi['login_yesterday'] = (clone $usr)->whereDate('last_login_at', $yesterday)->count();

        /* ===== Aktivitas terakhir (dibatasi ke user dalam scope) ===== */
        $scopedUserIds = (clone $usr)->pluck('id');
        $logs = Activity::query()
            ->when($scopedUserIds->isNotEmpty(), fn ($q) => $q->whereIn('causer_id', $scopedUserIds))
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        /* ===== Subquery: pegawai yang memiliki token aktif (distinct) ===== */
        $eqActive = DB::table('employee_qr_tokens')
            ->select('employee_id')
            ->where('status', 'active')
            ->whereNull('deleted_at')     // aman kalau kolom ini ada; kalau tidak ada juga tidak mengganggu
            ->groupBy('employee_id');

        /* ===== Ringkasan OPD/Unit ===== */
        \Log::debug('[Dashboard NEW] Index called', [
            'user_id' => $user->id,
            'isGlobalUser' => $isGlobalUser,
            'isGlobal_roles_check' => collect($user->getRoleNames())->map(fn($r) => mb_strtolower($r))->toArray(),
            'opdLocked' => $opdLocked,
            'currentOpd' => $currentOpd,
        ]);
        
        if (!$opdLocked) {
            // GLOBAL: ringkasan per OPD
            $list = Opd::query()
                ->when(!empty($ssoAllowed), fn($q) => $q->whereIn('opds.id', $ssoAllowed))
                ->select([
                    'opds.id', 'opds.nama',
                    // hanya employees yang belum di-soft-delete
                    DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="AKTIF" THEN 1 ELSE 0 END),0) as aktif'),
                    DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="NONAKTIF" THEN 1 ELSE 0 END),0) as nonaktif'),
                    // nametag: DISTINCT pegawai yang punya token aktif
                    DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN employees.deleted_at IS NULL AND eqx.employee_id IS NOT NULL THEN employees.id END),0) as nametag'),
                ])
                ->leftJoin('employees', 'employees.opd_id', '=', 'opds.id')
                ->leftJoinSub($eqActive, 'eqx', function ($j) {
                    $j->on('eqx.employee_id', '=', 'employees.id');
                })
                ->groupBy('opds.id', 'opds.nama')
                ->orderBy('opds.nama')
                ->get();

            $listTitle = 'Ringkasan per OPD';
        } else {
            // OPD terkunci: biasanya ringkasan per Unit OPD.
            // Namun jika user adalah superadmin, tampilkan seluruh OPD beserta jumlah pegawainya.
            
            \Log::debug('[Dashboard] OPD LOCKED branch', [
                'user_id' => $user->id,
                'user_roles' => $user->getRoleNames()->toArray(),
                'isSuperAdmin' => $user->isSuperAdmin(),
                'currentOpd' => $currentOpd,
                'ssoAllowed' => $ssoAllowed,
                'isEmpty_ssoAllowed' => empty($ssoAllowed),
            ]);
            
            if ($user->isSuperAdmin()) {
                $list = Opd::query()
                    ->select([
                        'opds.id', 'opds.nama',
                        DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="AKTIF" THEN 1 ELSE 0 END),0) as aktif'),
                        DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="NONAKTIF" THEN 1 ELSE 0 END),0) as nonaktif'),
                        DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN employees.deleted_at IS NULL AND eqx.employee_id IS NOT NULL THEN employees.id END),0) as nametag'),
                    ])
                    ->leftJoin('employees', 'employees.opd_id', '=', 'opds.id')
                    ->leftJoinSub($eqActive, 'eqx', function ($j) {
                        $j->on('eqx.employee_id', '=', 'employees.id');
                    })
                    ->groupBy('opds.id', 'opds.nama')
                    ->orderBy('opds.nama')
                    ->get();

                $listTitle = 'Ringkasan per OPD (semua OPD - Superadmin)';
            } else {
                // ringkasan per Unit OPD (atau satu unit jika user-nya terkunci sampai unit)
                // If SSO limits user to a set of OPD and current OPD is not allowed,
                // return empty list so UI shows "Belum ada data".
                
                \Log::debug('[Dashboard] Non-SuperAdmin branch', [
                    'currentOpd' => $currentOpd,
                    'ssoAllowed_count' => count($ssoAllowed),
                    'ssoAllowed' => $ssoAllowed,
                    'ssoCheck: !empty(ssoAllowed) && currentOpd && !in_array' => !empty($ssoAllowed) && $currentOpd && !in_array((int)$currentOpd, $ssoAllowed),
                ]);
                
                if (!empty($ssoAllowed) && $currentOpd && !in_array((int)$currentOpd, $ssoAllowed)) {
                    \Log::debug('[Dashboard] SSO filtering rejected currentOpd');
                    $list = collect();
                } else {
                    \Log::debug('[Dashboard] Querying OpdUnit', ['currentOpd' => $currentOpd, 'unitLocked' => $unitLocked]);
                    
                    $list = OpdUnit::query()
                ->select([
                    'opd_units.id', 'opd_units.nama',
                    DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="AKTIF" THEN 1 ELSE 0 END),0) as aktif'),
                    DB::raw('COALESCE(SUM(CASE WHEN employees.deleted_at IS NULL AND employees.status_aktif="NONAKTIF" THEN 1 ELSE 0 END),0) as nonaktif'),
                    DB::raw('COALESCE(COUNT(DISTINCT CASE WHEN employees.deleted_at IS NULL AND eqx.employee_id IS NOT NULL THEN employees.id END),0) as nametag'),
                ])
                ->leftJoin('employees', 'employees.opd_unit_id', '=', 'opd_units.id')
                ->leftJoinSub($eqActive, 'eqx', function ($j) {
                    $j->on('eqx.employee_id', '=', 'employees.id');
                })
                ->where('opd_units.opd_id', $currentOpd)
                ->when($unitLocked, fn ($q) => $q->where('opd_units.id', $user->opd_unit_id));
                    
                    $list = $list->groupBy('opd_units.id', 'opd_units.nama')
                        ->orderBy('opd_units.nama')
                        ->get();
                        
                    \Log::debug('[Dashboard] OpdUnit query result count: ' . $list->count());
                }

            }

            $listTitle = $unitLocked ? 'Unit OPD Anda' : 'Ringkasan per Unit OPD (OPD Anda)';
        }

        /* ===== Chart: tren pegawai 12 bulan (created_at) ===== */
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $empMonthly = (clone $emp)
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                DB::raw('COUNT(*) as c'),
            ])
            ->where('created_at', '>=', $start)
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c', 'ym');

        $chartEmployees = ['labels' => [], 'series' => []];
        for ($i = 0; $i < 12; $i++) {
            $point = (clone $start)->addMonths($i);
            $ym    = $point->format('Y-m');
            $chartEmployees['labels'][] = $point->isoFormat('MMM YY');
            $chartEmployees['series'][] = (int) ($empMonthly[$ym] ?? 0);
        }

        /* ===== Chart: login 7 hari terakhir ===== */
        $loginStart = Carbon::today()->subDays(6);

        $loginDaily = (clone $usr)
            ->select([
                DB::raw("DATE(last_login_at) as d"),
                DB::raw("COUNT(*) as c"),
            ])
            ->whereDate('last_login_at', '>=', $loginStart)
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd');

        $chartLogins = ['labels' => [], 'series' => []];
        for ($i = 0; $i < 7; $i++) {
            $d = (clone $loginStart)->addDays($i)->toDateString();
            $chartLogins['labels'][] = Carbon::parse($d)->isoFormat('DD MMM');
            $chartLogins['series'][] = (int) ($loginDaily[$d] ?? 0);
        }

            return view('dashboard', [
                'kpi'            => $kpi,
                'logs'           => $logs,
                'list'           => $list,
                'listTitle'      => $listTitle,
                'isGlobal'       => !$opdLocked,
                'chartEmployees' => $chartEmployees,
                'chartLogins'    => $chartLogins,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[Dashboard] Unhandled exception: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth()->id(),
            ]);
            // Fallback: logout user and redirect to login if dashboard breaks
            auth()->logout();
            return redirect()->route('sso.login')->with('error', 'Sesi Anda telah berakhir. Silakan login kembali.');
        }
    }
}
