<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RembgController;

// SSO Auth (baru)
use App\Http\Controllers\SsoLoginController;

// Core
use App\Http\Controllers\ContextController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\OpdController;
use App\Http\Controllers\OpdUnitController;
use App\Http\Controllers\UserController;


// UI endpoint: clean an employee photo via rembg and return cleaned URL
Route::post('/rembg/clean-employee', [RembgController::class, 'cleanEmployee']);
// UI endpoint: clean freshly uploaded/cropped image (no saved source yet)
Route::post('/rembg/clean-upload', [RembgController::class, 'cleanUpload']);
// Poll progress for a running rembg operation
Route::get('/rembg/progress', [RembgController::class, 'progress']);

// Internal loopback endpoint to call rembg without CSRF (only localhost)
Route::post('/internal/rembg/clean-employee', [RembgController::class, 'cleanEmployeeInternal']);
// QR & Scan
use App\Http\Controllers\QrController;
use App\Http\Controllers\QrPublicController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanLogController;

// Nametag & Foto
use App\Http\Controllers\NametagBatchController;
use App\Http\Controllers\NametagController;
use App\Http\Controllers\PhotoBgBatchController;

// Log & Dashboard
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Health / Home (Public)
|--------------------------------------------------------------------------
*/

Route::get('/ping', static fn () => 'pong')->name('ping');

Route::get('/', static function () {
    // kalau sudah login lokal (hasil SSO callback) -> dashboard
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('sso.login');
})->name('home');

/*
|--------------------------------------------------------------------------
| QR & Scan (Public)
|--------------------------------------------------------------------------
*/

// Halaman profil / hasil scan QR (public)
Route::get('/t/{token}', [ScanController::class, 'show'])
    ->name('qr.scan')
    ->middleware('throttle:60,1');

// Gambar QR SVG public
Route::get('/qr/{token}.svg', [QrPublicController::class, 'svgByToken'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('qr.svg.public');

// Gambar QR PNG public
Route::get('/qr/png/{token}', [QrPublicController::class, 'pngByToken'])
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('qr.png.public');

/*
|--------------------------------------------------------------------------
| SSO Entry & Callback (PUBLIC)
|--------------------------------------------------------------------------
| Ini satu-satunya pintu login.
*/

Route::get('/sso/login', [SsoLoginController::class, 'redirectToSso'])->name('sso.login');
Route::get('/sso/callback', [SsoLoginController::class, 'callback'])->name('sso.callback');

// Agar route lama /login tidak dipakai lagi
Route::get('/login', fn () => redirect()->route('sso.login'))->name('login');

// Logout & back ke SSO home
Route::post('/logout', [SsoLoginController::class, 'backToSso'])->name('logout');
Route::post('/sso/back', [SsoLoginController::class, 'backToSso'])->name('sso.back');

/*
|--------------------------------------------------------------------------
| PROTECTED (SSO authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware(['sso.auth'])->group(function () {

    // Debugging helper: show current user, roles, session and gate checks
    Route::get('/debug/whoami', function (\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['user' => null], 200);
        }

        $employee = \App\Models\Employee::where('status_aktif','AKTIF')->first();

        $g = \Illuminate\Support\Facades\Gate::forUser($user);

        $appConn = config('database.default');
        try {
            $db = \DB::connection($appConn);
            $sessionRow = $db->table(config('session.table'))->where('id', $request->session()->getId())->first();
        } catch (\Throwable $e) {
            $sessionRow = null;
        }

        try {
            $db = \DB::connection($appConn);
            $modelRoles = $db->table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->get();
        } catch (\Throwable $e) {
            $modelRoles = null;
        }

        return response()->json([
            'id' => $user->id,
            'sso_user_id' => $user->sso_user_id ?? null,
            'username' => $user->username ?? null,
            'roles' => $user->getRoleNames(),
            'hasRole_verifikator_global' => $user->hasRole('verifikator global'),
            'can_viewAny' => $g->allows('viewAny', \App\Models\Employee::class),
            'can_generateNametag' => $employee ? $g->allows('generateNametag', $employee) : null,
            'can_manageStatus' => $employee ? $g->allows('manageStatus', $employee) : null,
            'session_id' => $request->session()->getId(),
            'session_row' => $sessionRow,
            'model_has_roles' => $modelRoles,
            'note' => 'If model_has_roles is null, the app DB connection or table may be missing; check config/database.php and ensure default connection points to app DB.',
        ]);
    })->name('debug.whoami');

    // Temporary open debug endpoint (no auth) to inspect a local user by SSO id.
    // Usage: /debug/whoami-open?sso_user_id=42
    Route::get('/debug/whoami-open', function (\Illuminate\Http\Request $request) {
        $ssoId = (int) $request->query('sso_user_id', 0);
        if ($ssoId <= 0) {
            return response()->json(['error' => 'missing sso_user_id'], 400);
        }

        $db = \DB::connection(config('database.default'));
        $user = \App\Models\User::where('sso_user_id', $ssoId)->first();
        if (! $user) {
            return response()->json(['found' => false], 200);
        }

        try {
            $pivot = $db->table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->get();
        } catch (\Throwable $e) {
            $pivot = null;
        }

        return response()->json([
            'found' => true,
            'local_id' => $user->id,
            'sso_user_id' => $user->sso_user_id,
            'username' => $user->username,
            'roles' => $user->getRoleNames(),
            'pivot' => $pivot,
        ]);
    })->name('debug.whoami.open');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['session.health', 'detect.loop'])
        ->name('dashboard');

    // Employee import (preview -> confirm)
    Route::middleware('role:superadmin')->group(function () {
        // Template download for import
        Route::get('employees/import/template', [\App\Http\Controllers\EmployeeImportController::class, 'downloadTemplate'])
            ->name('employees.import.template');

        Route::get('employees/import', [\App\Http\Controllers\EmployeeImportController::class, 'show'])
            ->name('employees.import.show');
        Route::post('employees/import/preview', [\App\Http\Controllers\EmployeeImportController::class, 'preview'])
            ->name('employees.import.preview');
        Route::post('employees/import/upload', [\App\Http\Controllers\EmployeeImportController::class, 'uploadAjax'])
            ->name('employees.import.upload');
        Route::get('employees/import/preview/{id}', [\App\Http\Controllers\EmployeeImportController::class, 'previewFromId'])
            ->name('employees.import.preview.view');
        Route::post('employees/import/preview/{id}/rerun', [\App\Http\Controllers\EmployeeImportController::class, 'rerunPreview'])
            ->name('employees.import.preview.rerun');
        Route::get('employees/import/job-status/{jobId}', [\App\Http\Controllers\EmployeeImportController::class, 'jobStatus'])
            ->name('employees.import.job.status');
        Route::post('employees/import/confirm', [\App\Http\Controllers\EmployeeImportController::class, 'confirm'])
            ->name('employees.import.confirm');
        Route::get('employees/import/errors/{file}', [\App\Http\Controllers\EmployeeImportController::class, 'downloadErrors'])
            ->name('employees.import.errors');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin maintenance (superadmin only)
    |--------------------------------------------------------------------------
    */
    Route::post('/admin/maintenance/flush', static function () {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('event:clear');
        Artisan::call('optimize:clear');

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return back()->with('ok', 'Cache & OPcache dibersihkan.');
    })->middleware('role:superadmin')->name('admin.flush');

    /*
    |--------------------------------------------------------------------------
    | Context OPD / Unit
    |--------------------------------------------------------------------------
    */
    Route::post('/context/opd', [ContextController::class, 'setOpd'])->name('context.set-opd');
    Route::post('/context/unit', [ContextController::class, 'setUnit'])->name('context.set-unit');

    /*
    |--------------------------------------------------------------------------
    | Employees
    |--------------------------------------------------------------------------
    */
    Route::resource('employees', EmployeeController::class);

    Route::get('employees/data', [EmployeeController::class, 'data'])
        ->name('employees.data');

    // Select all filtered employees (store in session)
    Route::post('employees/select-all-filtered', [EmployeeController::class, 'selectAllFiltered'])
        ->name('employees.select_all_filtered');

    Route::post('employees/clear-select-all', [EmployeeController::class, 'clearSelectAllFiltered'])
        ->name('employees.clear_select_all');

    // Reset stored filters (server-side) and AJAX endpoint for OPD units
    Route::get('employees/filters/reset', [EmployeeController::class, 'resetFilters'])
        ->name('employees.filters.reset');

    Route::get('employees/opd-units/{opd}', [EmployeeController::class, 'opdUnits'])
        ->name('employees.opd_units');

    Route::post('employees/{employee}/activate', [EmployeeController::class, 'activate'])
        ->name('employees.activate');

    Route::post('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])
        ->name('employees.deactivate');

    Route::post('employees/{employee}/sk-upload', [EmployeeController::class, 'uploadSk'])
        ->name('employees.sk.upload');

    // Generate QR untuk 1 pegawai (single-active token)
    Route::post('employees/{employee}/qr', [QrController::class, 'store'])
        ->name('qr.store');

    // Force delete (permanent) employee + related data
    Route::post('employees/{employee}/force-delete', [EmployeeController::class, 'forceDestroy'])
        ->name('employees.force_delete');

    /*
    |--------------------------------------------------------------------------
    | Batch Foto (Photo BG)
    |--------------------------------------------------------------------------
    */
    Route::prefix('/admin/employees/photo-bg')->name('employees.photo_bg.')->group(function () {
        Route::get('/', [PhotoBgBatchController::class, 'index'])->name('index');
        Route::post('/run', [PhotoBgBatchController::class, 'run'])->name('run');
    });

    /*
    |--------------------------------------------------------------------------
    | Nametag (individu + batch)
    |--------------------------------------------------------------------------
    */

    // Batch: legacy
    Route::prefix('/admin/nametag')->name('employees.nametag.')->group(function () {
        Route::get('/', [NametagController::class, 'index'])->name('index');
        Route::post('/run', [NametagController::class, 'run'])->name('run');
    });

    // Generate nametag untuk 1 pegawai
    Route::post('employees/{employee}/nametag', [NametagController::class, 'store'])
        ->name('employees.nametag.store');

    // Batch baru (queue)
    Route::prefix('/nametag/batch')->name('nametag.batch.')->group(function () {
        Route::get('/', [NametagBatchController::class, 'index'])->name('index');
        Route::get('/data', [NametagBatchController::class, 'data'])->name('data');

        Route::post('/dispatch', [NametagBatchController::class, 'dispatch'])
            ->middleware('throttle:5,1')
            ->name('dispatch');

        Route::post('/download', [NametagBatchController::class, 'download'])->name('download');
        Route::post('/archive', [NametagBatchController::class, 'archive'])->name('archive');
        Route::get('/archive/{id}/download', [NametagBatchController::class, 'downloadArchive'])->name('archive.download');
        Route::get('/archive/{id}/status', [NametagBatchController::class, 'archiveStatus'])->name('archive.status');
        Route::match(['get','post'], '/employee-status', [NametagBatchController::class, 'employeeStatus'])->name('employee.status');
        Route::get('/progress/{batchId}', [NametagBatchController::class, 'progress'])->name('progress');
        Route::get('/queued', [NametagBatchController::class, 'queued'])->name('queued');
        Route::post('/retry-failed/{batchId}', [NametagBatchController::class, 'retryFailed'])->name('retry_failed');
    });

    /*
    |--------------------------------------------------------------------------
    | OPD Units
    |--------------------------------------------------------------------------
    */
    Route::prefix('/opd-units')->name('opd-units.')->group(function () {
        Route::get('/', [OpdUnitController::class, 'index'])->name('index');
        Route::get('/create', [OpdUnitController::class, 'create'])->name('create');
        Route::post('/', [OpdUnitController::class, 'store'])->name('store');
        Route::get('/{opdUnit}/edit', [OpdUnitController::class, 'edit'])->name('edit');

        Route::match(['put', 'patch'], '/{opdUnit}', [OpdUnitController::class, 'update'])
            ->name('update');

        Route::delete('/{opdUnit}', [OpdUnitController::class, 'destroy'])->name('destroy');
        Route::patch('/{opdUnit}/toggle', [OpdUnitController::class, 'toggleActive'])->name('toggle');
    });

    /*
    |--------------------------------------------------------------------------
    | Activity Logs & Scan Logs
    |--------------------------------------------------------------------------
    */
    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/{activity}', [ActivityLogController::class, 'show'])->name('logs.show');

    Route::get('/scan-logs', [ScanLogController::class, 'index'])->name('scan-logs.index');
    Route::get('/scan-logs/{id}', [ScanLogController::class, 'show'])->name('scan-logs.show');

    /*
    |--------------------------------------------------------------------------
    | OPD & Users (superadmin only)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin')->group(function () {

        Route::resource('opd', OpdController::class)
            ->names([
                'index'   => 'opd.index',
                'create'  => 'opd.create',
                'store'   => 'opd.store',
                'edit'    => 'opd.edit',
                'update'  => 'opd.update',
                'destroy' => 'opd.destroy',
                'show'    => 'opd.show',
            ])
            ->except(['show']);

        Route::resource('users', UserController::class)
            ->names('users')
            ->except(['show']);

        // Unit Kerja (normalized table)
        Route::resource('unit-kerja', \App\Http\Controllers\UnitKerjaController::class)
            ->names('unit-kerja')
            ->except(['show']);

        // Impersonate START
        Route::post('/impersonate/{user}', [ImpersonateController::class, 'start'])
            ->whereNumber('user')
            ->name('impersonate.start');
    });

    // Impersonate STOP (siapa pun yang sedang menyamar)
    Route::post('/impersonate/stop', [ImpersonateController::class, 'stop'])
        ->name('impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/
Route::fallback(static fn () => redirect()->route('home'));
