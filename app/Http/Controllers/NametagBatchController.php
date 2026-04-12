<?php

namespace App\Http\Controllers;

use App\Jobs\RenderNametagBatchJob;
use App\Models\Employee;
use App\Services\EmployeeQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;
use App\Models\NametagArchive;
use App\Jobs\CreateNametagArchiveJob;

class NametagBatchController extends Controller
{
    public function index()
    {
        return view('nametag.batch');
    }

    /**
     * Data pegawai untuk batch nametag + daftar unit.
     * (endpoint JSON)
     */
    public function data(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $isSuperadmin = $user->hasRole('superadmin');
        $opdId        = $user->opd_id;

        $limit      = (int) max(1, min(1000, (int) $request->input('limit', 200)));
        $unitFilter = trim((string) $request->input('unit', ''));

        // Deteksi tabel OPD & kolom nama
        [$hasOpd, $opdNameCol] = $this->detectOpd();

        // === daftar pegawai (hanya aktif) ===
        $qEmployees = Employee::query()->from('employees');

        if ($hasOpd && Schema::hasColumn('employees', 'opd_id')) {
            $qEmployees->leftJoin('opds', 'opds.id', '=', 'employees.opd_id');
        }

        // scope OPD
        if (!$isSuperadmin && $opdId) {
            $qEmployees->where('employees.opd_id', (int) $opdId);
        }

        // hanya pegawai aktif
        $this->applyActiveFilters($qEmployees);

        // filter unit jika dipilih
        if ($unitFilter !== '' && Schema::hasColumn('employees', 'nama_unit_opd')) {
            $qEmployees->whereRaw(
                'TRIM(UPPER(employees.nama_unit_opd)) = ?',
                [mb_strtoupper($unitFilter)]
            );
        }

        // SELECT kolom adaptif
        $select = [
            'employees.id',
            'employees.nama',
        ];
        if (Schema::hasColumn('employees', 'nip')) {
            $select[] = 'employees.nip';
        }
        if (Schema::hasColumn('employees', 'nama_unit_opd')) {
            $select[] = 'employees.nama_unit_opd';
        }
        if (Schema::hasColumn('employees', 'opd_id')) {
            $select[] = 'employees.opd_id';
        }

        if ($hasOpd && $opdNameCol) {
            $select[] = DB::raw("opds.`{$opdNameCol}` as opd_nama");
        }

        $qEmployees->select($select)
            ->orderBy('employees.nama')
            ->limit($limit);

        // === daftar unit (hanya dari pegawai aktif) ===
        $qUnits = Employee::query()->from('employees');

        if (!$isSuperadmin && $opdId) {
            $qUnits->where('employees.opd_id', (int) $opdId);
        }
        $this->applyActiveFilters($qUnits);

        if (Schema::hasColumn('employees', 'nama_unit_opd')) {
            $units = $qUnits
                ->whereNotNull('employees.nama_unit_opd')
                ->selectRaw('DISTINCT TRIM(employees.nama_unit_opd) AS nama_unit_opd')
                ->orderBy('nama_unit_opd')
                ->pluck('nama_unit_opd')
                ->values();
        } else {
            $units = collect();
        }

        $employees = $qEmployees->get();

        if ($employees->isEmpty()) {
            Log::info('[nametag.batch] data kosong (filter aktif)', [
                'user_id'       => $user->id,
                'opd_id'        => $opdId,
                'is_superadmin' => $isSuperadmin,
                'unit_filter'   => $unitFilter,
            ]);
        }

        // heuristik konservatif 0.6 detik/item
        $eta = (int) round($employees->count() * 0.6);

        return response()->json([
            'ok'            => true,
            'employees'     => $employees,
            'units'         => $units,
            'limit'         => $limit,
            'eta'           => $eta,
            'selected_unit' => $unitFilter,
        ]);
    }

    /**
     * Jalankan render batch nametag.
     * - Kalau request HTML normal: redirect back dengan flash message.
     * - Kalau expectsJson / AJAX: balas JSON (seperti sebelumnya).
     */
    public function dispatch(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondError($request, 'Unauthorized', 401);
        }

        // Only users with create permission (non-verifikator roles) may dispatch batch generation
        $this->authorize('create', \App\Models\Employee::class);

        $isSuperadmin = $user->hasRole('superadmin');
        $opdId        = $user->opd_id;

        // Check if this is a "use filtered session" request
        $useFilteredSession = (bool) $request->input('use_filtered_session', false);
        $sessionState = session('employees_select_all_filtered', []);

        // Determine IDs to use
        if ($useFilteredSession && !empty($sessionState) && $sessionState['enabled'] ?? false) {
            // Re-query using saved filters from session to get all matching employees
            $filters = $sessionState['filters'] ?? [];
            $filterRequest = Request::create(
                '?q=' . urlencode($filters['q'] ?? '') .
                '&status=' . urlencode($filters['status'] ?? '') .
                '&opd_id=' . urlencode($filters['opd_id'] ?? '') .
                '&opd_unit_id=' . urlencode($filters['opd_unit_id'] ?? '') .
                '&unit_kerja_id=' . urlencode($filters['unit_kerja_id'] ?? ''),
                'GET'
            );

            // Build query using same logic as index/data endpoints
            $query = app(EmployeeQueryService::class)->queryIndex($filterRequest, $user);
            $ids = $query->pluck('employees.id')
                ->map(fn($v) => (int)$v)
                ->values()
                ->all();

            Log::info('nametag: dispatch using filtered session', [
                'user_id' => (int)$user->id,
                'filters_count' => count($sessionState['filters']),
                'result_ids_count' => count($ids),
            ]);
        } else {
            // Use IDs from request (original behavior)
            $ids = array_values(array_unique(array_map(
                'intval',
                (array) $request->input('ids', [])
            )));
        }

        if (empty($ids)) {
            return $this->respondError($request, 'Tidak ada pegawai yang dipilih.', 422);
        }

        $onlyFront = (bool) $request->boolean('only_front', false);
        $onlyBack  = (bool) $request->boolean('only_back', false);

        if ($onlyFront && $onlyBack) {
            return $this->respondError($request, 'only_front dan only_back tidak boleh bersamaan', 422);
        }

        // Refilter ID: hanya AKTIF + scope OPD
        $q = Employee::query()
            ->from('employees')
            ->whereIn('employees.id', $ids);

        if (!$isSuperadmin && $opdId) {
            $q->where('employees.opd_id', (int) $opdId);
        }
        $this->applyActiveFilters($q);

        $validIds = $q->pluck('employees.id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if (empty($validIds)) {
            return $this->respondError(
                $request,
                'Tidak ada pegawai AKTIF pada pilihan tersebut.',
                422
            );
        }

        if (count($validIds) < count($ids)) {
            Log::info('nametag: dispatch filtered inactive', [
                'user_id'   => (int) $user->id,
                'requested' => count($ids),
                'valid'     => count($validIds),
            ]);
        }

        $userId = (int) $user->id;
        $batch  = (string) Str::uuid();

        // Persist batch in DB so UI and API read authoritative state
        try {
            $nb = \App\Models\NametagBatch::create([
                'id' => $batch,
                'user_id' => $userId,
                'opd_id' => $user->opd_id ?? null,
                'opd_unit_id' => null,
                'employee_ids' => $validIds,
                'total' => count($validIds),
                'done' => 0,
                'fail' => 0,
                'skipped' => 0,
                'status' => 'queued',
                'started_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('nametag: failed to persist batch', ['err' => $e->getMessage(), 'batch' => $batch]);
        }

        // inisialisasi progress in cache (for fast ETA UI) — read-only fallback
        $estimatedPerItem = config('nametag.estimated_seconds_per_item', 0.6);
        $initialEta = (int) round(count($validIds) * $estimatedPerItem);
        Cache::put(
            RenderNametagBatchJob::progressKey($userId, $batch),
            [
                'total'       => count($validIds),
                'done'        => 0,
                'fail'        => 0,
                'skipped'     => 0,
                'eta'         => $initialEta,
                'started_at'  => now()->toIso8601String(),
                'finished_at' => null,
                'status'      => 'queued',
            ],
            now()->addHours(2)
        );

        // Register active batch immediately so the UI can display queued batches
        try {
            $key = RenderNametagBatchJob::progressKey($userId, $batch);
            Cache::put($key . ':employees', $validIds, now()->addHours(2));
            $activeKey = 'nametag:active_batches';
            $list = Cache::get($activeKey, []);
            $list[$batch] = [
                'batch' => $batch,
                'user'  => $userId,
                'total' => count($validIds),
                'created_at' => now()->toIso8601String(),
            ];
            Cache::put($activeKey, $list, now()->addHours(4));
        } catch (\Throwable $_) {
            // ignore cache failures
        }

        // Idempotensi ringan (15 detik)
        $idemKey = 'nametag:dispatch:' . $userId . ':' .
            md5(json_encode($validIds) . (int) $onlyFront . (int) $onlyBack);

        if (!Cache::add($idemKey, 1, 15)) {
            return $this->respondError($request, 'Terlalu sering, coba lagi sebentar.', 429);
        }

        try {
            // Dispatch batch job (dispatcher will enqueue per-employee jobs)
            RenderNametagBatchJob::dispatch($validIds, $userId, $batch, [
                'only_front' => $onlyFront,
                'only_back'  => $onlyBack,
            ])->onQueue('nametag');
            // mark only employees that actually need generation as queued in DB
            try {
                $toQueue = [];
                foreach ($validIds as $id) {
                    $frontPath = public_path("nametag/front/{$id}.png");
                    $backPath  = public_path("nametag/back/{$id}.png");
                    $hasFront = is_file($frontPath);
                    $hasBack  = is_file($backPath);

                    // Determine if this id needs queued work based on requested sides
                    $needsFront = !$hasFront && empty($onlyBack);
                    $needsBack  = !$hasBack && empty($onlyFront);

                    if ($needsFront || $needsBack) {
                        $toQueue[] = $id;
                    }
                }

                if (!empty($toQueue)) {
                    \App\Models\Employee::whereIn('id', $toQueue)
                        ->update(['nametag_status' => 'queued', 'nametag_error' => null]);
                }
            } catch (\Throwable $_) {
                // ignore DB update failures here
            }
            // Best-effort: try to start a short-lived worker to pick up queued jobs
            // This is non-fatal; if the environment doesn't allow spawning processes
            // the error will be logged and processing can rely on external supervisor.
            try {
                $this->tryStartWorker();
            } catch (\Throwable $e) {
                Log::warning('nametag: auto-start worker failed', ['err' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('nametag: dispatch batch failed', [
                'batch' => $batch,
                'err'   => $e->getMessage(),
            ]);
        }

        $payload = [
            'ok'       => true,
            'batch_id' => $batch,
            'mode'     => 'queued',
            'count'    => count($validIds),
        ];

        // Kalau diminta JSON (AJAX / API)
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        // Jika tidak AJAX (form submit dari index), balik ke halaman sebelumnya (index)
        // dan sertakan batch id di flash supaya index bisa mulai polling.
        if (!($request->expectsJson() || $request->wantsJson() || $request->ajax())) {
            return redirect()
                ->back()
                ->with('ok', "Generate batch untuk {$payload['count']} pegawai dikirim ke antrian.")
                ->with('batch_id', $batch);
        }

        // Default JSON response for AJAX callers
        return response()->json($payload);
    }

    /**
     * Best-effort spawn of a short-lived background worker to process newly queued nametag jobs.
     *
     * Notes:
     * - This attempts to start `php artisan queue:work database --queue=nametag --once` in background.
     * - It is intentionally non-blocking and best-effort; production deployments should use
     *   a proper supervisor (systemd / supervisord) to run persistent workers.
     */
    private function tryStartWorker(): void
    {
        // allow disabling auto-start via config
        if (!config('nametag.auto_start_worker', true)) {
            return;
        }

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $cmd = sprintf('%s %s queue:work database --queue=nametag --once --tries=1 -vvv', escapeshellcmd($php), escapeshellarg($artisan));

        // run in project cwd and detach; capture PID if possible
        $full = sprintf('cd %s && nohup %s > /dev/null 2>&1 & echo $!', escapeshellarg(base_path()), $cmd);
        $output = [];
        $ret = 1;
        @exec($full, $output, $ret);

        if ($ret === 0 && !empty($output) && is_numeric($output[0])) {
            Log::info('nametag: auto-started worker', ['cmd' => $cmd, 'pid' => trim($output[0])]);
        } else {
            Log::warning('nametag: auto-start worker did not return pid', ['cmd' => $cmd, 'ret' => $ret, 'out' => $output]);
        }
    }

    /**
     * Download batch nametag (ZIP) dari pegawai yang dipilih.
     */
    public function download(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        // Restrict downloads of batch ZIPs to non-verifikator users
        $this->authorize('create', \App\Models\Employee::class);

        $isSuperadmin = $user->hasRole('superadmin');
        $opdId        = $user->opd_id;

        $ids = array_values(array_unique(array_map(
            'intval',
            (array) $request->input('ids', [])
        )));

        if (empty($ids)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Tidak ada pegawai yang dipilih untuk diunduh nametagnya.');
        }

        // filter sama seperti dispatch()
        $q = Employee::query()
            ->from('employees')
            ->whereIn('employees.id', $ids);

        if (!$isSuperadmin && $opdId) {
            $q->where('employees.opd_id', (int) $opdId);
        }
        $this->applyActiveFilters($q);

        $validIds = $q->pluck('employees.id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if (empty($validIds)) {
            // Fallback: mungkin nametag sudah digenerate namun pegawai tidak lagi "AKTIF"
            // atau ter-filter oleh OPD. Periksa filesystem untuk ID yang diminta
            // dan gunakan ID yang memang punya file nametag di disk.
            $fileIds = [];
            foreach ($ids as $idReq) {
                foreach (['front', 'back'] as $side) {
                    if (is_file(public_path("nametag/{$side}/{$idReq}.png"))) {
                        $fileIds[] = (int) $idReq;
                        break;
                    }
                }
            }
            $fileIds = array_values(array_unique($fileIds));

            if (empty($fileIds)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Tidak ada pegawai AKTIF pada pilihan tersebut.');
            }

            // gunakan file-backed ids untuk pembuatan ZIP
            $validIds = $fileIds;
        }

        // Siapkan ZIP sementara
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipName = 'nametag_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $tmpDir . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Gagal membuat arsip ZIP.');
        }

        $added = 0;

        // Load employee dengan relations untuk struktur folder hirarkis
        $employees = Employee::whereIn('id', $validIds)
            ->with(['opd:id,nama', 'opdUnit:id,nama', 'unitKerja:id,nama'])
            ->get(['id','nama','nip','opd_id','opd_unit_id','unit_kerja_id'])
            ->keyBy('id');

        foreach ($validIds as $id) {
            $emp = $employees->get($id);
            
            // Build hierarchical path: OPD > Unit > Unit Kerja > Employee
            $pathParts = [];
            
            if ($emp) {
                // Add OPD
                if ($emp->opd && $emp->opd->nama) {
                    $pathParts[] = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', trim($emp->opd->nama));
                }
                
                // Add Unit (OPD Unit)
                if ($emp->opdUnit && $emp->opdUnit->nama) {
                    $pathParts[] = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', trim($emp->opdUnit->nama));
                }
                
                // Add Unit Kerja
                if ($emp->unitKerja && $emp->unitKerja->nama) {
                    $pathParts[] = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', trim($emp->unitKerja->nama));
                }
                
                // Add Employee folder (nama_nip)
                $namePart = trim($emp->nama ?: 'pegawai_' . $id);
                $nipPart  = trim((string) ($emp->nip ?? '')) ?: (string)$id;
                $combined = $namePart . '_' . $nipPart;
                $empDirName = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $combined);
                $empDirName = trim($empDirName);
                $empDirName = str_replace(' ', '_', $empDirName);
                if ($empDirName === '') {
                    $empDirName = (string)$id;
                }
                $pathParts[] = $empDirName;
            } else {
                $pathParts[] = (string)$id;
            }
            
            // Clean path parts and join
            $pathParts = array_filter($pathParts, fn($p) => !empty(trim($p)));
            $dirPath = implode('/', $pathParts);
            
            if (empty($dirPath)) {
                $dirPath = (string)$id;
            }

            foreach (['front', 'back'] as $side) {
                $filePath = public_path("nametag/{$side}/{$id}.png");
                if (!is_file($filePath)) {
                    continue;
                }
                $localName = "{$dirPath}/{$side}.png";
                // Add file to archive and set method to STORE (no compression)
                // so output bytes equal original files — good for print precision
                $zip->addFile($filePath, $localName);
                // setCompressionName may not exist on very old PHP builds; check first
                if (method_exists($zip, 'setCompressionName')) {
                    try {
                        $zip->setCompressionName($localName, ZipArchive::CM_STORE);
                    } catch (\Throwable $e) {
                        // ignore if not supported
                    }
                }
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);

            return redirect()
                ->back()
                ->with('error', 'Tidak ada file nametag yang bisa diunduh. Pastikan sudah digenerate terlebih dahulu.');
        }

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * Return active queued nametag batches and their (visible) employee items.
     * JSON endpoint used by the index UI.
     */
    public function queued(Request $request)
    {
        // Debug: log incoming queued requests for troubleshooting client 404s
        try {
            Log::info('[nametag.queued] request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'headers' => [
                    'host' => $request->header('host'),
                    'x-requested-with' => $request->header('X-Requested-With'),
                    'accept' => $request->header('accept'),
                ],
                'user_id' => Auth::id(),
            ]);
        } catch (\Throwable $_) {
            // non-fatal
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $isSuperadmin = $user->hasRole('superadmin');
        $opdId = $user->opd_id;

        // Read authoritative batches from DB, filter by role/opd
        $q = \App\Models\NametagBatch::query()->whereIn('status', ['queued', 'running'])->orderBy('created_at', 'desc')->limit(20);
        if (!$isSuperadmin && $opdId) {
            $q->where('opd_id', (int) $opdId);
        }

        $batches = $q->get();
        $out = [];
        foreach ($batches as $b) {
            $empIds = (array) ($b->employee_ids ?? []);
            if (empty($empIds)) continue;

            $empQ = Employee::query()->from('employees')->whereIn('employees.id', $empIds);
            if (!$isSuperadmin && $opdId) $empQ->where('employees.opd_id', (int) $opdId);
            $this->applyActiveFilters($empQ);

            $select = ['employees.id','employees.nama'];
            if (Schema::hasColumn('employees','nip')) $select[] = 'employees.nip';
            if (Schema::hasColumn('employees','nama_unit_opd')) $select[] = 'employees.nama_unit_opd';
            if (Schema::hasColumn('employees','opd_id')) $select[] = 'employees.opd_id';

            $visibleEmployees = $empQ->select($select)->orderBy('employees.nama')->limit(10)->get();

            // Try to read cache for more detailed progress info (eta, last_item_ms, status)
            $cacheKey = RenderNametagBatchJob::progressKey((int)$b->user_id, (string)$b->id);
            $cache = Cache::get($cacheKey, []);

            $done = (int) ($b->done ?? ($cache['done'] ?? 0));
            $fail = (int) ($b->fail ?? ($cache['fail'] ?? 0));
            $skipped = (int) ($b->skipped ?? ($cache['skipped'] ?? 0));
            $total = (int) ($b->total ?? ($cache['total'] ?? 0));
            $processed = $done + $fail + $skipped;
            $percent = $total > 0 ? (int) round(($processed / $total) * 100) : 0;
            $status = $b->status ?? ($cache['status'] ?? 'queued');
            $running = $status === 'running';
            $eta = isset($cache['eta']) ? (int) $cache['eta'] : null;
            $startedAt = $b->started_at?->toIso8601String() ?? ($cache['started_at'] ?? null);

            $userName = null;
            try {
                $u = \App\Models\User::find($b->user_id);
                if ($u) {
                    $userName = $u->name ?? $u->username ?? null;
                }
            } catch (\Throwable $_) {
                // ignore
            }

            $out[] = [
                'batch' => $b->id,
                'meta' => [
                    'user_id' => $b->user_id,
                    'user_name' => $userName,
                    'total' => $total,
                    'created_at' => $b->created_at?->toIso8601String(),
                    'status' => $status,
                ],
                'progress' => [
                    'total' => $total,
                    'done' => $done,
                    'fail' => $fail,
                    'skipped' => $skipped,
                    'processed' => $processed,
                    'percent' => $percent,
                    'running' => $running,
                    'eta' => $eta,
                    'started_at' => $startedAt,
                ],
                'employees' => $visibleEmployees,
            ];
        }

        return response()->json(['ok' => true, 'batches' => $out]);
    }

    /**
     * Enqueue creation of a nametag archive (background ZIP) for selected employees.
     * Returns JSON with archive id.
     */
    public function archive(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondError($request, 'Unauthorized', 401);
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        if (empty($ids)) {
            return $this->respondError($request, 'Tidak ada pegawai yang dipilih.', 422);
        }

        $name = trim((string) $request->input('name', '')); // optional archive name

        $archive = NametagArchive::create([
            'user_id' => $user->id,
            'name'    => $name ?: null,
            'count'   => count($ids),
            'status'  => 'queued',
        ]);

        // Dispatch background job
        CreateNametagArchiveJob::dispatch($ids, (int) $user->id, (int) $archive->id);

        return response()->json([
            'ok'         => true,
            'archive_id' => $archive->id,
            'message'    => 'Arsip sedang diproses di latar belakang.',
        ]);
    }

    /**
     * Download a previously created archive by id (background job must have status 'ready').
     */
    public function downloadArchive(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $archive = NametagArchive::find($id);
        if (!$archive) {
            return redirect()->back()->with('error', 'Arsip tidak ditemukan.');
        }

        // Permission: owner or superadmin
        if ($archive->user_id && $archive->user_id !== $user->id && ! $user->hasRole('superadmin')) {
            return redirect()->back()->with('error', 'Unauthorized to download this archive.');
        }

        if ($archive->status !== 'ready' || ! $archive->path) {
            return redirect()->back()->with('error', 'Arsip belum siap untuk diunduh.');
        }

        $full = storage_path('app/' . ltrim($archive->path, '/'));
        if (!is_file($full)) {
            return redirect()->back()->with('error', 'File arsip tidak ditemukan di server.');
        }

        $name = basename($full);
        return response()->download($full, $name);
    }

    /**
     * Return JSON status for an archive job.
     */
    public function archiveStatus(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $archive = NametagArchive::find($id);
        if (!$archive) {
            return response()->json(['ok' => false, 'message' => 'not_found'], 404);
        }

        // permission: owner or superadmin
        if ($archive->user_id && $archive->user_id !== $user->id && ! $user->hasRole('superadmin')) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $downloadUrl = null;
        if ($archive->status === 'ready' && $archive->path) {
            $downloadUrl = url('/nametag/batch/archive/' . $archive->id . '/download');
        }

        return response()->json([
            'ok' => true,
            'id' => $archive->id,
            'status' => $archive->status,
            'count' => $archive->count,
            'notes' => $archive->notes,
            'download_url' => $downloadUrl,
            'created_at' => $archive->created_at?->toIso8601String(),
        ]);
    }

    /**
     * API: check readiness (front/back) for given employee ids (JSON).
     */
    public function employeeStatus(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        if (empty($ids)) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $result = [];
        foreach ($ids as $id) {
            $frontPath = public_path("nametag/front/{$id}.png");
            $backPath  = public_path("nametag/back/{$id}.png");
            $frontOk = is_file($frontPath);
            $backOk  = is_file($backPath);

            // If files exist but DB still shows queued/processing, reconcile to 'ready'
            try {
                $e = \App\Models\Employee::find($id);
                if ($e && ($frontOk || $backOk)) {
                    $cur = $e->nametag_status ?? 'none';
                    if ($cur !== 'ready') {
                        $e->nametag_status = 'ready';
                        if (empty($e->nametag_generated_at)) $e->nametag_generated_at = now();
                        $e->nametag_error = null;
                        $e->save();
                        \Log::info('nametag: reconciled employee to ready', ['employee' => $id, 'from' => $cur]);
                    }
                }
            } catch (\Throwable $_) {
                // ignore DB failures — endpoint should be safe
            }

            $result[$id] = [
                'id' => $id,
                'has_front' => $frontOk,
                'front_url' => $frontOk ? asset("nametag/front/{$id}.png") . '?v=' . @filemtime($frontPath) : null,
                'has_back' => $backOk,
                'back_url' => $backOk ? asset("nametag/back/{$id}.png") . '?v=' . @filemtime($backPath) : null,
            ];
        }

        return response()->json(['ok' => true, 'data' => $result]);
    }

    /**
     * Cek progress batch oleh user yang sama (JSON).
     */
    public function progress(string $batchId)
    {
        $userId = (int) Auth::id();
        if (!$userId) {
            return response()->json([
                'ok'      => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Allow superadmin to query other users' batches; otherwise restrict to own batches
        $user = Auth::user();
        $isSuperadmin = $user->hasRole('superadmin');

        // Try cache first (fast path)
        $cacheKey = RenderNametagBatchJob::progressKey($userId, $batchId);
        $data = Cache::get($cacheKey);

        // If cache missing, fall back to DB authoritative record
        $batch = null;
        if (!$data) {
            $batch = \App\Models\NametagBatch::find($batchId);
            if (!$batch) {
                return response()->json(['ok' => false, 'message' => 'batch not found'], 404);
            }

            if (!$isSuperadmin && (int)$batch->user_id !== $userId) {
                return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
            }

            $data = [
                'total' => (int) ($batch->total ?? 0),
                'done' => (int) ($batch->done ?? 0),
                'fail' => (int) ($batch->fail ?? 0),
                'skipped' => (int) ($batch->skipped ?? 0),
                'eta' => null,
                'started_at' => $batch->started_at?->toIso8601String(),
                'finished_at' => $batch->finished_at?->toIso8601String(),
                'status' => $batch->status ?? 'queued',
            ];
        }

        $total = (int) ($data['total'] ?? 0);
        $done = (int) ($data['done'] ?? 0);
        $fail = (int) ($data['fail'] ?? 0);
        $skipped = (int) ($data['skipped'] ?? 0);
        $processed = $done + $fail + $skipped;
        $percent = $total > 0 ? (int) round(($processed / $total) * 100) : 0;
        $status = $data['status'] ?? 'queued';
        $running = $status === 'running';
        $eta = isset($data['eta']) ? (is_numeric($data['eta']) ? (int)$data['eta'] : null) : null;
        $startedAt = $data['started_at'] ?? null;
        $lastItemMs = $data['last_item_ms'] ?? null;

        $resp = [
            'ok' => true,
            'batch' => $batchId,
            'status' => $status,
            'total' => $total,
            'done' => $done,
            'fail' => $fail,
            'skipped' => $skipped,
            'processed' => $processed,
            'percent' => $percent,
            'running' => $running,
            'eta' => $eta,
            'started_at' => $startedAt,
            'last_item_ms' => $lastItemMs,
        ];

        return response()->json($resp);
    }

    /**
     * Retry failed employees for a given batch (re-dispatch single jobs).
     */
    public function retryFailed(Request $request, string $batchId)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->respondError($request, 'Unauthorized', 401);
        }

        $key = RenderNametagBatchJob::progressKey((int) $user->id, $batchId);
        $failedKey = $key . ':failed_ids';
        $failed = Cache::get($failedKey, []);

        if (empty($failed)) {
            return $this->respondError($request, 'No failed items to retry.', 422);
        }

        // lightweight idempotency
        $idem = $key . ':retry:' . md5(json_encode($failed));
        if (!Cache::add($idem, 1, 10)) {
            return $this->respondError($request, 'Retry already triggered recently.', 429);
        }

        foreach ($failed as $id) {
            \App\Jobs\RenderSingleNametagJob::dispatch((int)$id, (int)$user->id, $batchId, [])->onQueue('nametag');
        }

        return response()->json(['ok' => true, 'requeued' => count($failed)]);
    }

    /* ================= Helpers ================= */

    /**
     * Hanya pegawai aktif.
     */
    private function applyActiveFilters($query): void
    {
        $query->where(function ($q) {
            if (Schema::hasColumn('employees', 'status_aktif')) {
                $q->orWhere('employees.status_aktif', 'AKTIF');
            }
            if (Schema::hasColumn('employees', 'is_active')) {
                $q->orWhere('employees.is_active', 1);
            }
            if (Schema::hasColumn('employees', 'status')) {
                $q->orWhereRaw('UPPER(employees.status) = "AKTIF"')
                  ->orWhereRaw('UPPER(employees.status) LIKE "AKTIF%"');
            }
            foreach (['kedudukan', 'kedudukan_kepegawaian', 'status_kepegawaian'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $q->orWhereRaw("UPPER(employees.$col) LIKE '%AKTIF%'");
                }
            }
        });
    }

    /**
     * Deteksi ketersediaan tabel/kolom OPD.
     *
     * @return array{0: bool, 1: string|null}
     */
    private function detectOpd(): array
    {
        if (!Schema::hasTable('opds')) {
            return [false, null];
        }

        $nameCol = null;
        if (Schema::hasColumn('opds', 'nama')) {
            $nameCol = 'nama';
        } elseif (Schema::hasColumn('opds', 'name')) {
            $nameCol = 'name';
        }

        return [true, $nameCol];
    }

    /**
     * Helper balasan error: JSON untuk AJAX, redirect+flash untuk HTML.
     */
    private function respondError(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => false,
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $message);
    }
}
