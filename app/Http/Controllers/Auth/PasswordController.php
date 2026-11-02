<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Sesi aktif tetap boleh melanjutkan; hash baru disimpan agar
        // AuthenticateSession tidak memutus sesi yang sedang dipakai.
        $request->session()->put(
            'password_hash_'.config('auth.defaults.guard', 'web'),
            $user->getAuthPassword()
        );
        $request->session()->regenerate();

        return back()->with('status', 'password-updated');
    }
}
