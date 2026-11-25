<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileCompletionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Registrasi mandiri dinonaktifkan. Akun hanya dibuat oleh Super Admin
    // melalui halaman administrasi akun; tidak ada endpoint alternatif.
});

Route::middleware('auth')->group(function () {
    Route::get('profile/complete', [ProfileCompletionController::class, 'show'])->name('profile.complete');
    Route::post('profile/complete', [ProfileCompletionController::class, 'store'])->name('profile.complete.store');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Verifikasi password lama dibatasi agar tidak dapat ditebak berulang kali.
    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
