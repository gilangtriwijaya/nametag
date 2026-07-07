<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\RembgController;
use App\Http\Controllers\Api\StatistikPublikController;

Route::post('/sso/master/export', [MasterDataController::class, 'export'])
    ->middleware('throttle:30,1');

// API endpoint for UI to request rembg cleaning (no CSRF)
Route::post('/rembg/clean-employee', [RembgController::class, 'cleanEmployee'])->middleware('throttle:30,1');
// API endpoint: clean freshly uploaded/cropped image (no CSRF)
Route::post('/rembg/clean-upload', [RembgController::class, 'cleanUpload'])->middleware('throttle:30,1');
Route::get('/rembg/progress', [RembgController::class, 'progress'])->middleware('throttle:60,1');

// Internal API route (stateless) for loopback callers
Route::post('/internal/rembg/clean-employee', [RembgController::class, 'cleanEmployeeInternal'])->middleware('throttle:30,1');

Route::prefix('publik')
    ->middleware(['throttle:publik', 'api.publik.key'])
    ->group(function () {
        Route::get('/statistik', [StatistikPublikController::class, 'show']);
        Route::get('/health', [StatistikPublikController::class, 'health']);
    });
