<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileCompletionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Start registration via button - server side session/token
    Route::post('register/start', function (Request $request) {
        $token = \Illuminate\Support\Str::random(40);
        $request->session()->put('registration_token', $token);
        $request->session()->put('registration_token_expires_at', now()->addMinutes(10)->timestamp);
        return redirect()->route('register');
    })->name('register.start');

    // Registrasi mandiri: role selalu ditetapkan backend sebagai PEMINJAM.
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('profile/complete', [ProfileCompletionController::class, 'show'])->name('profile.complete');
    Route::post('profile/complete', [ProfileCompletionController::class, 'store'])->name('profile.complete.store');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
