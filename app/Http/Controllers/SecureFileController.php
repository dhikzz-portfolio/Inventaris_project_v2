<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Menyajikan file internal dari disk privat (storage/app/private) hanya untuk
 * pengguna terautentikasi yang berwenang. Path tidak pernah diekspos langsung
 * sebagai URL publik.
 */
class SecureFileController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = trim((string) $request->query('path', ''));

        abort_if($path === '' || str_contains($path, '..') || str_starts_with($path, '/'), 404);

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $user = $request->user();
        abort_unless($this->allowed($path, $user), 403);

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function allowed(string $path, $user): bool
    {
        if (str_starts_with($path, 'reports/')) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Arsip laporan hanya untuk pembuatnya; laporan seluruh sekolah oleh
            // scheduler tidak boleh dibuka Ketua Laboran.
            return Report::query()
                ->where('generated_by', $user->id)
                ->where(fn ($q) => $q->where('pdf_path', $path)->orWhere('excel_path', $path))
                ->exists();
        }

        if (str_starts_with($path, 'write-offs/')) {
            return $user->managesInventory();
        }

        if (str_starts_with($path, 'damage/') || str_starts_with($path, 'signatures/')) {
            if (! $user->canApproveLoans()) {
                return false;
            }

            // Foto bukti & TTD terikat pada pinjaman — laboran hanya untuk jurusannya.
            if (preg_match('#^(?:damage/loan-(\d+)/|signatures/loan-(\d+)-)#', $path, $m)) {
                $loan = Loan::find((int) ($m[1] ?: $m[2]));

                return $loan !== null && $loan->canBeViewedBy($user);
            }

            return $user->managesInventory();
        }

        if (str_starts_with($path, 'bast/')) {
            return $user->managesInventory();
        }

        return false;
    }
}
