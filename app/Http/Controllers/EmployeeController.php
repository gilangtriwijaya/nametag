<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeStoreRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Services\EmployeeOrchestrator;
use App\Services\EmployeeQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeQueryService  $query,
        protected EmployeeOrchestrator $orchestrator,
    ) {
    }

    /* ==========================================================
       INDEX
       ========================================================== */

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        // Server-side filter persistence: if user submits any filter, save to session.
        // If no filters present but session has saved filters, redirect to apply them.
        $saved = session('employees_filters', []);
        $hasAnyFilter = $request->hasAny(['q', 'status', 'opd_id', 'opd_unit_id', 'unit_kerja_id', 'opd_parent_only']);

        if ($hasAnyFilter) {
            session(['employees_filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'opd_id' => $request->query('opd_id'),
                'opd_unit_id' => $request->query('opd_unit_id'),
                'unit_kerja_id' => $request->query('unit_kerja_id'),
                'opd_parent_only' => (int) $request->query('opd_parent_only', 0),
            ]]);
        } elseif (!empty($saved)) {
            // Apply saved filters by redirecting to index with query string
            $qs = array_filter($saved, fn($v) => $v !== null && $v !== '');
            if (!empty($qs)) {
                return redirect()->route('employees.index', $qs);
            }
        }

        $builder = $this->query->queryIndex($request, $user);

        $employees = $builder
            ->paginate(20)
            // jaga semua filter di query string (q, status, opd_id, opd_unit_id, dst)
            ->appends($request->query());

        // Sisipkan info QR terakhir per pegawai
        $this->query->attachLatestQrTokens($employees);

        // daftar OPD + unit untuk dropdown filter
        $opds = Opd::orderBy('nama')->get();
        
        // Get all OpdUnits first
        $allOpdUnits = OpdUnit::orderBy('nama')->get();
        
        // Apply SSO filtering if needed (consistent with employee query service)
        try {
            $appCode = (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
            $ssoAllowed = $user->ssoAllowedOpdIds($appCode);
            
            // Only filter OpdUnits if user has limited SSO access
            // Admin organisasi should NOT be filtered by SSO (they're global)
            $isOrgAdmin = $user->hasAnyRole(['org admin', 'org_admin', 'org-admin', 'admin_organisasi', 'admin organisasi', 'admin bagian organisasi']);
            
            \Log::debug('EmployeeController dropdown filter', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_roles' => $user->getRoleNames()->toArray(),
                'isOrgAdmin' => $isOrgAdmin,
                'ssoAllowed' => $ssoAllowed,
                'before_filter_opd_count' => $allOpdUnits->count(),
                'before_filter_opd_ids' => $allOpdUnits->pluck('opd_id')->unique()->sort()->values()->toArray(),
            ]);
            
            if (!empty($ssoAllowed) && !$user->hasRole('superadmin') && !$isOrgAdmin) {
                $allOpdUnits = $allOpdUnits->filter(fn($u) => in_array($u->opd_id, $ssoAllowed));
                
                \Log::debug('EmployeeController after SSO filter', [
                    'after_filter_opd_count' => $allOpdUnits->count(),
                    'after_filter_opd_ids' => $allOpdUnits->pluck('opd_id')->unique()->sort()->values()->toArray(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('EmployeeController SSO filter error: ' . $e->getMessage());
        }
        
        $opdUnits = $allOpdUnits->groupBy('opd_id')->map(function ($group) {
            return $group->map(function ($u) {
                return ['id' => (int) $u->id, 'nama' => $u->nama];
            })->values();
        })->toArray();
        // Convert integer keys to strings
        $opdUnits = array_combine(array_map('strval', array_keys($opdUnits)), array_values($opdUnits));

        // Unit Kerja (normalized) map for filter dropdown (grouped by opd_id)
        $unitKerjas = \App\Models\UnitKerja::orderBy('nama')->get()->groupBy('opd_id')->map(function ($group) {
            return $group->map(function ($u) {
                return ['id' => (int) $u->id, 'nama' => $u->nama];
            })->values();
        })->toArray();
        // Convert integer keys to strings
        $unitKerjas = array_combine(array_map('strval', array_keys($unitKerjas)), array_values($unitKerjas));

        // sso scope info for badge
        $ssoScope = ['mode' => 'GLOBAL', 'ids' => [], 'names' => []];
        try {
            $appCode = (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
            $allowed = $user->ssoAllowedOpdIds($appCode);
            if (!empty($allowed)) {
                // If allowed covers all OPDs, treat as GLOBAL (no need to list many names)
                $totalOpds = $opds->count();
                if (count($allowed) < $totalOpds) {
                    $ssoScope['mode'] = 'TERBATAS';
                    $ssoScope['ids'] = $allowed;

                    $opdModels = Opd::whereIn('id', $allowed)->orderBy('nama')->get();
                    $ssoScope['names'] = $opdModels->map(function ($o) {
                        // Prefer stored abbreviation (`singkatan`) if available
                        if (!empty($o->singkatan)) return trim((string) $o->singkatan);

                        // Otherwise build initials from words, skipping the word 'dan'
                        $words = preg_split('/\s+/', $o->nama) ?: [];
                        $words = array_filter($words, fn($w) => mb_strtolower(trim($w)) !== 'dan');
                        $initials = array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), $words ?: []);
                        return implode('', $initials) ?: $o->nama;
                    })->all();
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return view('employees.index', [
            'employees'      => $employees,
            'q'              => $request->query('q'),
            'status'         => $request->query('status'),
            'opd_id'         => $request->query('opd_id'),
            'opd_unit_id'    => $request->query('opd_unit_id'),
            'unit_kerja_id'  => $request->query('unit_kerja_id'),
            'opd_parent_only' => (int) $request->query('opd_parent_only', 0),
            'current_opd_id' => session('current_opd_id'),
            'opd_locked'     => session('opd_locked', false),
            'opds'           => $opds,
            'opdUnits'       => $opdUnits,
            'unitKerjas'     => $unitKerjas,
            'sso_scope'      => $ssoScope,
        ]);
    }

    /* ==========================================================
       CREATE
       ========================================================== */

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create', [
            'current_opd_id' => session('current_opd_id'),
            'opd_locked'     => session('opd_locked', false),
        ]);
    }

    /**
     * AJAX endpoint: return rendered rows + pagination for current filters/page.
     */
    public function data(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        // persist filters server-side when requested
        $hasAnyFilter = $request->hasAny(['q', 'status', 'opd_id', 'opd_unit_id']);
        if ($hasAnyFilter) {
            session(['employees_filters' => [
                'q' => (string) $request->query('q', ''),
                'status' => (string) $request->query('status', ''),
                'opd_id' => $request->query('opd_id'),
                'opd_unit_id' => $request->query('opd_unit_id'),
            ]]);
        }

        $builder = $this->query->queryIndex($request, $user);

        $employees = $builder->paginate(20)->appends($request->query());
        $this->query->attachLatestQrTokens($employees);

        // Map employees to simple JSON structure (lightweight)
        $rows = $employees->map(function ($emp) {
            // Helper: Apply simple title case with quote preservation
            $applySimpleTitleCase = function(?string $text): string {
                $s = (string)($text ?? '');
                if ($s === '') return $s;
                
                // Extract and preserve content inside double quotes (WITHOUT the quotes)
                $preservedMap = [];
                $markerStart = chr(0);
                $markerEnd = chr(1);
                
                $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                    $idx = count($preservedMap);
                    $key = $markerStart . 'Q' . $idx . $markerEnd;
                    $preservedMap[$key] = $matches[1];
                    return $key;
                }, $s);
                
                $gelarDepan = '';
                $namePart = $s;
                
                if (preg_match('/^((?:[A-Za-z]{1,3}\.\s+|[A-Z][a-z]+\.\s+)+)(.*)$/u', $namePart, $m)) {
                    $gelarDepan = $m[1];
                    $namePart = $m[2];
                }
                
                $namePart = mb_convert_case($namePart, MB_CASE_TITLE, 'UTF-8');
                
                $result = $gelarDepan . $namePart;
                
                foreach ($preservedMap as $key => $value) {
                    $result = str_replace($key, $value, $result);
                }
                
                return $result;
            };
            
            $frontPath = public_path("nametag/front/{$emp->id}.png");
            $backPath  = public_path("nametag/back/{$emp->id}.png");

            $frontOk = is_file($frontPath);
            $backOk = is_file($backPath);

            // Server-driven status reconciliation: if any generated file exists,
            // filesystem wins over stale DB status. Also update DB to prevent stuck status.
            $serverStatus = $emp->nametag_status ?? 'none';
            if (($frontOk || $backOk) && $serverStatus !== 'ready') {
                $serverStatus = 'ready';
                // Also update DB non-blocking (don't fail if update fails)
                try {
                    $emp->update(['nametag_status' => 'ready', 'nametag_error' => null]);
                } catch (\Throwable $_) {
                    // Ignore update failure - UI still shows correct status from files
                }
            }

            return [
                'id' => $emp->id,
                'nama' => $emp->nama_lengkap ?? $emp->nama,
                'nip' => $emp->nip,
                'jabatan' => $applySimpleTitleCase($emp->jabatan ?? ''),
                'opd' => $emp->opd->nama ?? null,
                'opd_unit' => $emp->opdUnit->nama ?? null,
                'status_aktif' => $emp->status_aktif ?? null,
                'latest_qr_token' => $emp->latest_qr_token ?? null,
                'latest_qr_status' => $emp->latest_qr_status ?? null,
                'latest_qr_created_at' => $emp->latest_qr_created_at ? (string) $emp->latest_qr_created_at : null,
                'nametag_status' => $serverStatus,
                'nametag_generated_at' => $emp->nametag_generated_at ? (string) $emp->nametag_generated_at : null,
                'nametag_error' => $emp->nametag_error ?? null,
                'has_front' => $frontOk,
                'front_url' => $frontOk ? asset("nametag/front/{$emp->id}.png") . '?v=' . @filemtime($frontPath) : null,
                'has_back' => $backOk,
                'back_url' => $backOk ? asset("nametag/back/{$emp->id}.png") . '?v=' . @filemtime($backPath) : null,
                'can_update' => (bool) auth()->user()?->can('update', $emp),
                'can_force_delete' => (bool) auth()->user()?->can('forceDelete', $emp),
            ];
        })->all();

        return response()->json([
            'employees' => $rows,
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    /**
     * AJAX endpoint: Store "select all filtered" state in session.
     * Called when user clicks "Select All" checkbox to select all filtered employees.
     */
    public function selectAllFiltered(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        // Extract filter parameters from request
        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'opd_id' => $request->query('opd_id'),
            'opd_unit_id' => $request->query('opd_unit_id'),
            'unit_kerja_id' => $request->query('unit_kerja_id'),
        ];

        // Build query using same logic as index/data
        $builder = $this->query->queryIndex($request, $user);
        
        // Get total count of matching records (without pagination)
        $totalCount = $builder->count();
        
        // Get IDs if count is reasonable (up to 10k)
        $allIds = [];
        if ($totalCount > 0 && $totalCount <= 10000) {
            $allIds = $builder->pluck('employees.id')
                ->map(fn($v) => (int)$v)
                ->values()
                ->all();
        }

        // Store in session: select all filtered state
        session([
            'employees_select_all_filtered' => [
                'enabled' => true,
                'filters' => $filters,
                'total_count' => $totalCount,
                'employee_ids' => $allIds,  // Cache IDs for immediate use
                'created_at' => now()->toIso8601String(),
            ]
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Dipilih {$totalCount} pegawai sesuai filter aktif.",
            'total_count' => $totalCount,
            'ids' => $allIds,  // Return for immediate use in batch processing
        ]);
    }

    /**
     * AJAX endpoint: Clear "select all filtered" state from session.
     */
    public function clearSelectAllFiltered(Request $request)
    {
        session()->forget('employees_select_all_filtered');

        return response()->json([
            'ok' => true,
            'message' => 'Selection cleared.',
        ]);
    }

    /* ===========================================================
       STORE
       ========================================================== */

    public function store(EmployeeStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $result = $this->orchestrator->createWithMedia($request);

        if (! $result['success']) {
            // Menjaga perilaku lama: error ditempel di field NIP
            return back()
                ->withInput()
                ->withErrors(['nip' => $result['error'] ?? $result['message']]);
        }

        return redirect()
            ->route('employees.index')
            ->with('ok', $result['message']);
    }

    /* ==========================================================
       SHOW & EDIT
       ========================================================== */

    public function show(Request $request, Employee $employee): View
    {
        $this->authorize('view', $employee);

        $employee->load([
            'opd',
            'opdUnit',
            'unitKerja',
            'creator',
            'updater',
        ]);

        // QR terakhir untuk detail
        $latestQr = $employee->qrTokens()
            ->orderByDesc('id')
            ->first();

        // SATU-SATUNYA sumber kebenaran: policy manageStatus
        $user            = $request->user();
        $canToggleStatus = $user ? $user->can('manageStatus', $employee) : false;

        // ===== Helper 1: Apply case transformation untuk NAMA (dengan normalizeGelarPublic untuk quote semantics) =====
        $applyTitleCaseWithGelarForName = function(?string $text): string {
            $s = (string)($text ?? '');
            if ($s === '') return $s;
            
            // Logic dari NametagTextLayout::applyCase('title')
            // Step 1: Extract gelar_belakang (setelah koma)
            $gelarDepan = '';
            $namePart = $s;
            $gelarBelakang = '';
            
            if (strpos($s, ',') !== false) {
                [$namePart, $gelarBelakang] = explode(',', $s, 2);
                // Apply normalizeGelarPublic untuk preserve quote semantics dan dot capitalization
                $gelarBelakang = ', ' . \App\Support\NametagData::normalizeGelarPublic(trim($gelarBelakang));
            }
            
            // Step 2: Extract gelar_depan (leading abbreviations with REQUIRED dot)
            if (preg_match('/^((?:[A-Za-z]{1,3}\.\s+|[A-Z][a-z]+\.\s+)+)(.*)$/u', $namePart, $m)) {
                $gelarDepan = $m[1];
                $namePart = $m[2];
            }
            
            // Step 3: Title case nama part
            $namePart = mb_convert_case($namePart, MB_CASE_TITLE, 'UTF-8');
            
            return $gelarDepan . $namePart . $gelarBelakang;
        };

        // ===== Helper 2: Apply simple title case untuk JABATAN dan UNIT (with quote semantics) =====
        $applySimpleTitleCase = function(?string $text): string {
            $s = (string)($text ?? '');
            if ($s === '') return $s;
            
            // Step 0: Extract and preserve content inside double quotes (WITHOUT the quotes)
            // This allows us to preserve case of quoted content while removing the quote marks
            $preservedMap = [];
            $markerStart = chr(0);  // null byte as boundary
            $markerEnd = chr(1);    // SOH as boundary
            
            $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                $idx = count($preservedMap);
                $key = $markerStart . 'Q' . $idx . $markerEnd;  // Safe placeholder
                $preservedMap[$key] = $matches[1];  // Store content WITHOUT the quote marks
                return $key;  // Replace with placeholder temporarily
            }, $s);
            
            // Extract gelar_depan untuk preserve abbreviation case
            $gelarDepan = '';
            $namePart = $s;
            
            // Step 1: Extract gelar_depan (leading abbreviations with REQUIRED dot)
            if (preg_match('/^((?:[A-Za-z]{1,3}\.\s+|[A-Z][a-z]+\.\s+)+)(.*)$/u', $namePart, $m)) {
                $gelarDepan = $m[1];
                $namePart = $m[2];
            }
            
            // Step 2: Simple title case untuk semua text (including after comma)
            $namePart = mb_convert_case($namePart, MB_CASE_TITLE, 'UTF-8');
            
            $result = $gelarDepan . $namePart;
            
            // Step 3: Restore preserved (quoted) content WITHOUT the quote marks
            // This preserves the original case while removing visual quotes
            foreach ($preservedMap as $key => $value) {
                $result = str_replace($key, $value, $result);
            }
            
            return $result;
        };

        // Siapkan normalized data sesuai nametag rules
        $displayData = [
            'nama_display' => $applyTitleCaseWithGelarForName(
                trim(implode(' ', array_filter([
                    $employee->gelar_depan,
                    $employee->nama,
                ]))) . (trim($employee->gelar_belakang_input ?? $employee->gelar_belakang ?? '') ? ', ' . ($employee->gelar_belakang_input ?? $employee->gelar_belakang) : '')
            ),
            'gelar_depan_display' => \App\Support\NametagData::normalizeGelarPublic($employee->gelar_depan ?? ''),
            'gelar_belakang_display' => \App\Support\NametagData::normalizeGelarPublic($employee->gelar_belakang_input ?? $employee->gelar_belakang ?? ''),
            'jabatan_display' => $applySimpleTitleCase($employee->jabatan ?? ''),
            'unit_kerja_display' => $applySimpleTitleCase($employee->unitKerja?->nama ?? $employee->nama_unit_opd ?? ''),
            'gol_darah_display' => mb_strtoupper($employee->gol_darah ?? '', 'UTF-8'),
        ];

        return view('employees.show', [
            'employee'        => $employee,
            'latestQr'        => $latestQr,
            'canToggleStatus' => $canToggleStatus,
            'displayData'     => $displayData,
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', [
            'employee'       => $employee->load(['opd', 'opdUnit']),
            'current_opd_id' => session('current_opd_id'),
            'opd_locked'     => session('opd_locked', false),
        ]);
    }

    /* ==========================================================
       UPDATE
       ========================================================== */

    public function update(EmployeeUpdateRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $result = $this->orchestrator->updateWithMedia($request, $employee);

        if (! $result['success']) {
            return back()
                ->withInput()
                ->withErrors(['nip' => $result['error'] ?? $result['message']]);
        }

        return redirect()
            ->route('employees.index')
            ->with('ok', $result['message']);
    }

    /**
     * Hapus filter yang tersimpan di session dan kembalikan ke index tanpa filter.
     */
    public function resetFilters(Request $request)
    {
        session()->forget('employees_filters');
        
        // AJAX request: return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Filters reset']);
        }
        
        // Regular request: redirect
        return redirect()->route('employees.index');
    }

    /**
     * Kembalikan daftar unit untuk OPD tertentu (dipakai AJAX).
     */
    public function opdUnits($opdId)
    {
        $units = OpdUnit::where('opd_id', (int) $opdId)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return response()->json($units);
    }

    /* ==========================================================
       DELETE / ACTIVATE / DEACTIVATE
       ========================================================== */

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $result = $this->orchestrator->delete($employee);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('ok', $result['message']);
    }

    public function activate(Employee $employee): RedirectResponse
    {
        $this->authorize('manageStatus', $employee);

        $result = $this->orchestrator->activate($employee);

        $key = $result['success'] ? 'ok' : 'error';

        return back()->with($key, $result['message']);
    }

    public function deactivate(Employee $employee): RedirectResponse
    {
        $this->authorize('manageStatus', $employee);

        $result = $this->orchestrator->deactivate($employee);

        $key = $result['success'] ? 'ok' : 'error';

        return back()->with($key, $result['message']);
    }

    /**
     * Permanently delete employee and related data (hard delete).
     */
    public function forceDestroy(Request $request, Employee $employee)
    {
        $this->authorize('forceDelete', $employee);

        $result = $this->orchestrator->forceDelete($employee);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result ? $result : ['success' => false, 'message' => 'Unknown']);
        }

        $key = $result['success'] ? 'ok' : 'error';

        return back()->with($key, $result['message']);
    }

    /* ==========================================================
       UPLOAD SK (PDF) – form terpisah di halaman detail
       ========================================================== */

    public function uploadSk(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        // Validasi tetap di controller agar pesan custom terjaga
        $request->validate([
            'sk_file' => 'required|file|mimes:pdf|max:5120', // max 5 MB
        ], [
            'sk_file.required' => 'Silakan pilih file SK (PDF).',
            'sk_file.mimes'    => 'File SK harus berformat PDF.',
            'sk_file.max'      => 'Ukuran file SK maksimal 5 MB.',
        ]);

        $result = $this->orchestrator->uploadSk($request, $employee);

        $key = $result['success'] ? 'ok' : 'error';

        return back()->with($key, $result['message']);
    }
}
