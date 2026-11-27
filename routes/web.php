<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RecapController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', fn () => redirect()->route('dashboard'));
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/rekap/pegawai', [RecapController::class, 'pegawai'])->name('rekap.pegawai');
    Route::get('/rekap/capeg-honor-pkwt-kontrak', [RecapController::class, 'nonTetap'])->name('rekap.non-tetap');
    Route::get('/rekap/{category}/detail/{nik}', [RecapController::class, 'detail'])->name('rekap.detail');
    Route::get('/rekap/{category}/export', [RecapController::class, 'exportCsv'])->name('rekap.export');
});
