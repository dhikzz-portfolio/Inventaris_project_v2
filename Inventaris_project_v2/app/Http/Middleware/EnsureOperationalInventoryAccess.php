<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin hanya mengelola akun dan pengaturan. Seluruh transaksi operasional
 * inventaris (katalog, scan, peminjaman, kegiatan) ditolak di backend agar
 * pembatasan tidak bergantung pada penyembunyian menu.
 */
class EnsureOperationalInventoryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            abort(403, 'Role Super Admin tidak memiliki akses operasional inventaris.');
        }

        return $next($request);
    }
}
