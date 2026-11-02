<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('catalogs.index');
    }

    public function show(Item $catalog): View
    {
        abort_unless(
            Item::query()->approved()->visibleTo(auth()->user())->whereKey($catalog->id)->exists(),
            404
        );

        // Katalog yang diarsipkan hanya boleh dibuka pengelola untuk keperluan
        // audit; peminjam tidak boleh melihatnya sebagai barang yang tersedia.
        if ($catalog->isArchived() && ! auth()->user()->managesInventory()) {
            abort(404);
        }

        $catalog->load('category');

        $scopedUnits = fn ($query) => $query->visibleTo(auth()->user())->where('status', '!=', 'DIHAPUS');

        $unitCount = $catalog->units()->where($scopedUnits)->count();
        $specificationCount = $catalog->specifications()
            ->whereHas('units', $scopedUnits)
            ->count();
        $loanItemCount = $catalog->loanItems()->count();

        return view('catalogs.show', [
            'catalog' => $catalog,
            'unitCount' => $unitCount,
            'specificationCount' => $specificationCount,
            'loanItemCount' => $loanItemCount,
            'canDeletePermanently' => $catalog->canBeDeletedPermanently(),
            'canDelete' => auth()->user()->managesInventory()
                && Item::query()->visibleTo(auth()->user())->whereKey($catalog->id)->exists(),
        ]);
    }

    /**
     * Hapus katalog sesuai aturan aman:
     * - tanpa unit/transaksi → hapus permanen;
     * - memiliki unit atau riwayat → arsipkan (nonaktif) agar audit tetap utuh.
     */
    public function destroy(Request $request, Item $catalog): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->managesInventory(), 403);
        abort_unless(Item::query()->visibleTo($user)->whereKey($catalog->id)->exists(), 404);

        $name = $catalog->name;

        if ($catalog->canBeDeletedPermanently()) {
            activity('barang')
                ->performedOn($catalog)
                ->causedBy($user)
                ->withProperties(['name' => $name])
                ->log('Menghapus katalog tanpa unit/transaksi terkait');

            $catalog->delete();

            return redirect()->route('catalogs.index')
                ->with('success', "Katalog \"{$name}\" dihapus permanen karena belum memiliki unit atau riwayat transaksi.");
        }

        if ($catalog->isArchived()) {
            return redirect()->route('catalogs.show', $catalog)
                ->with('success', "Katalog \"{$name}\" sudah dinonaktifkan sebelumnya.");
        }

        $catalog->update([
            'archived_at' => now(),
            'archive_reason' => 'Dinonaktifkan dari katalog aktif; memiliki unit atau riwayat transaksi sehingga tidak dihapus permanen.',
        ]);

        activity('barang')
            ->performedOn($catalog)
            ->causedBy($user)
            ->withProperties([
                'units' => $catalog->units()->count(),
                'loan_items' => $catalog->loanItems()->count(),
            ])
            ->log('Menonaktifkan (arsip) katalog yang memiliki unit/riwayat transaksi');

        return redirect()->route('catalogs.index')
            ->with('success', "Katalog \"{$name}\" dinonaktifkan dan tidak lagi ditawarkan untuk peminjaman baru. Unit dan riwayat transaksinya tetap tersimpan.");
    }
}
