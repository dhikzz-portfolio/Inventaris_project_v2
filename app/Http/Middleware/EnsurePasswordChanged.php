<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun hasil bootstrap / reset administrator wajib mengganti password awal.
 * Pengguna tetap dapat membuka halaman profil untuk mengganti password.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->mustChangePassword()) {
            if ($request->routeIs(
                'profile.edit',
                'profile.update',
                'password.update',
                'password.confirm',
                'profile.complete',
                'profile.complete.store',
                'logout',
            )) {
                return $next($request);
            }

            return redirect()->route('profile.edit')
                ->with('warning', 'Anda wajib mengganti password awal sebelum menggunakan aplikasi.');
        }

        return $next($request);
    }
}
