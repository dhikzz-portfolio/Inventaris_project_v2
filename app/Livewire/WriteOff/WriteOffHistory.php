<?php

namespace App\Livewire\WriteOff;

use App\Models\WriteOff;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Riwayat penghapusan inventaris yang memperbarui dirinya sendiri sehingga
 * pemantau tidak perlu memuat ulang halaman saat pengguna lain mengajukan
 * atau menyetujui penghapusan.
 */
class WriteOffHistory extends Component
{
    use WithPagination;

    /**
     * Nama halaman paginasi dipisahkan dari daftar unit pada halaman yang sama
     * agar tautan riwayat tidak menimpa posisi halaman unit.
     */
    public const PAGE_NAME = 'history_page';

    public int $perPage = 10;

    public function render(): View
    {
        return view('livewire.write-off.write-off-history', [
            'history' => WriteOff::with(['item', 'requester', 'approver', 'units.catalog:id,name'])
                ->withCount('units')
                ->latest()
                ->paginate($this->perPage, ['*'], self::PAGE_NAME),
        ]);
    }
}
