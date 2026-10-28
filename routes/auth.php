<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileCompletionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Start registration via button - server side session/token
    Route::post('register/start', function (Request $request) {
        $token = Str::random(64);
        $request->session()->put('registration_token', $token);
        $request->session()->put('registration_token_expires_at', now()->addMinutes(10)->timestamp);

        return redirect()->route('register', ['token' => $token]);
    })->middleware('throttle:5,1')->name('register.start');

    // Both endpoints require the server-created session flow token.
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
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
