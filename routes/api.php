<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\LemburController as AdminLemburController;
use App\Http\Controllers\Api\Admin\PegawaiController as AdminPegawaiController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\FrontendContextController;
use App\Http\Controllers\Api\LemburController;
use App\Http\Controllers\Api\Settings\ProfileController;
use App\Http\Controllers\Api\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to the API LemburGo',
        'status' => 'success',
    ]);
});

// AUTH--------------------------
Route::get('frontend-context', FrontendContextController::class)->name('api.frontend-context');

Route::prefix('settings')->name('api.settings.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->middleware('verified')->name('profile.destroy');
    Route::get('security', [SecurityController::class, 'show'])
        ->middleware(['auth:web', 'verified', 'password.confirm'])->name('security.show');
    Route::put('password', [SecurityController::class, 'update'])
        ->middleware(['verified', 'throttle:6,1'])->name('password.update');
});

Route::prefix('/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('/logout', [AuthController::class, 'logout']);
    });
});

// LEMBUR --------------------------
Route::prefix('/lemburs')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [LemburController::class, 'index']);
        Route::post('/', [LemburController::class, 'store']);
        Route::get('/export', [LemburController::class, 'export']);
        Route::get('/{lembur:uuid}/upah', [LemburController::class, 'hitungUpah']);
        Route::get('/detail/{lembur:uuid}', [LemburController::class, 'show']);
        Route::delete('/delete/{lembur:uuid}', [LemburController::class, 'destroy']);
        Route::put('/{lembur:uuid}', [LemburController::class, 'update']);
        Route::get('/draft', [LemburController::class, 'draft']);
        Route::get('/kalender', [LemburController::class, 'kalender']);
        Route::get('/total-upah', [LemburController::class, 'totalUpahLembur']);
    });

Route::prefix('admin')
    ->name('api.admin.')
    ->middleware(['auth:sanctum', 'verified', 'can:access-admin-panel'])
    ->group(function (): void {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::get('lemburs', [AdminLemburController::class, 'index'])->name('lemburs.index');
        Route::get('lemburs/export', [AdminLemburController::class, 'export'])->name('lemburs.export');
        Route::post('lemburs/bulk-lock', [AdminLemburController::class, 'bulkLock'])->name('lemburs.bulk-lock');
        Route::get('lemburs/{lembur:uuid}', [AdminLemburController::class, 'show'])->name('lemburs.show');
        Route::delete('lemburs/{lembur:uuid}', [AdminLemburController::class, 'destroy'])->name('lemburs.destroy');
        Route::post('lemburs/{lembur:uuid}/lock', [AdminLemburController::class, 'lock'])->name('lemburs.lock');

        Route::get('pegawai', [AdminPegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('pegawai/{pegawai:uuid}', [AdminPegawaiController::class, 'show'])->name('pegawai.show');
        Route::put('pegawai/{pegawai:uuid}', [AdminPegawaiController::class, 'update'])->name('pegawai.update');
        Route::get('pegawai/{pegawai:uuid}/lemburs', [AdminPegawaiController::class, 'lemburs'])->name('pegawai.lemburs.index');
    });
