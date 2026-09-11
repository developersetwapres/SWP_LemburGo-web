<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LemburController;
use App\Http\Controllers\Admin\PegawaiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::prefix('dashboard')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'can:access-admin-panel'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('lemburs', [LemburController::class, 'index'])->name('lemburs.index');
        Route::get('lemburs/export', [LemburController::class, 'export'])->name('lemburs.export');
        Route::post('lemburs/lock', [LemburController::class, 'bulkLock'])->name('lemburs.bulk-lock');
        Route::delete('lemburs/{lembur:uuid}', [LemburController::class, 'destroy'])->name('lemburs.destroy');
        Route::get('lemburs/{lembur:uuid}', [LemburController::class, 'show'])->name('lemburs.show');
        Route::post('lemburs/{lembur:uuid}/lock', [LemburController::class, 'lock'])->name('lemburs.lock');

        Route::get('pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('pegawai/{pegawai:uuid}', [PegawaiController::class, 'show'])->name('pegawai.show');
        Route::put('pegawai/{pegawai:uuid}', [PegawaiController::class, 'update'])->name('pegawai.update');
    });

require __DIR__ . '/settings.php';
