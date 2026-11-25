<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Memastikan tautan notifikasi hanya mengarah ke halaman yang boleh dibuka
 * penerimanya. Notifikasi lama yang penerimanya berubah role (atau datanya
 * sudah tidak ada) tidak boleh menghasilkan halaman "akses ditolak".
 *
 * Pemeriksaan ini hanya lapisan navigasi; otorisasi backend tetap berlaku.
 */
class NotificationLinkGuard
{
    public function isAccessible(User $user, ?string $link): bool
    {
        $link = trim((string) $link);

        if ($link === '') {
            return true;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($link, 'GET'));
        } catch (Throwable) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'inventory.access' && ! $user->hasOperationalInventoryAccess()) {
                return false;
            }

            if (str_starts_with($middleware, 'role:')) {
                $roles = explode('|', substr($middleware, strlen('role:')));

                if (! in_array($user->role, $roles, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
