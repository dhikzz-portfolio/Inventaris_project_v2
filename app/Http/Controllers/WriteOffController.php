<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\NotificationType;
use App\Enums\UnitStatus;
use App\Models\Item;
use App\Models\WriteOff;
use App\Services\AssetUnitService;
use App\Services\NotifyApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WriteOffController extends Controller
{
    public function index(Request $request): View
    {
        return view('write-offs.index', [
            'items' => Item::approved()
                ->notArchived()
                ->visibleTo($request->user())
                ->where('status', '!=', ItemStatus::DIHAPUS->value)
                ->with('room')
                ->orderBy('name')
                ->get(),
            'history' => WriteOff::with(['item', 'requester', 'approver'])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:item_catalogs,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'berita_acara' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $item = Item::findOrFail($data['item_id']);

        // Penghapusan hanya untuk katalog dalam cakupan akses pengaju.
        abort_unless(
            Item::query()->visibleTo($request->user())->whereKey($item->id)->exists(),
            404
        );

        // Unit yang sedang dipinjam tidak boleh dihapus, dan permohonan pending
        // sebelumnya juga mengurangi jatah agar tidak terjadi penghapusan ganda.
        $pendingQty = WriteOff::where('item_id', $item->id)->where('status', 'PENDING')->sum('quantity');
        $removable = $item->quantity - $item->activeLoanRemainingQuantity() - $pendingQty;
        abort_if((int) $data['quantity'] > $removable, 422,
            "Jumlah melebihi unit yang dapat dihapus (tersedia {$removable}, termasuk permohonan yang masih menunggu).");

        DB::transaction(function () use ($data, $item, $request) {
            $writeOff = WriteOff::create([
                'item_id' => $item->id,
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'berita_acara_path' => $request->file('berita_acara')->store('write-offs', 'local'),
                'status' => 'PENDING',
                'requested_by' => $request->user()->id,
            ]);

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($request->user())
                ->log("Mengajukan penghapusan {$item->name} ({$data['quantity']} {$item->unit})");

            NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGHAPUSAN,
                "Permohonan inventaris keluar: \"{$item->name}\" ({$data['quantity']} {$item->unit}) menunggu persetujuan Wakasek.",
                route('write-offs.pending'));
        });

        return redirect()->route('write-offs.index')
            ->with('success', 'Permohonan inventaris keluar diajukan — menunggu persetujuan Wakasek Sarpras.');
    }

    /** Daftar penghapusan menunggu approval (Wakasek). */
    public function pending(): View
    {
        return view('write-offs.pending', [
            'pending' => WriteOff::with(['item.room', 'requester'])
                ->where('status', 'PENDING')
                ->latest()
                ->get(),
            'decided' => WriteOff::with(['item', 'requester', 'approver'])
                ->where('status', '!=', 'PENDING')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function approve(Request $request, WriteOff $writeOff): RedirectResponse
    {
        abort_unless($writeOff->isPending(), 404);

        DB::transaction(function () use ($request, $writeOff) {
            $item = $writeOff->item;
            $units = app(AssetUnitService::class);

            $writeOff->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Penghapusan berlaku pada unit fisik: arsipkan unit satu per satu
            // (hilang → rusak berat → rusak ringan → baik, nomor tertinggi dulu),
            // lalu hitung ulang counter katalog dari sisa unit agar angka katalog,
            // dashboard, laporan, dan daftar unit tetap konsisten.
            $candidates = $item->units()
                ->where('status', '!=', UnitStatus::DIHAPUS->value)
                ->get()
                ->sort(function ($a, $b) {
                    $rank = fn ($unit) => match (true) {
                        $unit->status === UnitStatus::HILANG => 0,
                        $unit->condition === Condition::RUSAK_BERAT => 1,
                        $unit->condition === Condition::RUSAK_RINGAN => 2,
                        default => 3,
                    };

                    return [$rank($a), -$a->unit_no] <=> [$rank($b), -$b->unit_no];
                })
                ->take($writeOff->quantity);

            foreach ($candidates as $unit) {
                $units->archive($unit);
            }

            $item->refresh();
            $units->syncCatalogCounters($item);

            $remaining = $item->units()->where('status', '!=', UnitStatus::DIHAPUS->value)->count();

            if ($remaining === 0) {
                $item->update(['status' => ItemStatus::DIHAPUS]);
            }

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($request->user())
                ->log("Menyetujui penghapusan {$item->name} ({$candidates->count()} unit diarsipkan, sisa unit: {$remaining})");

            NotifyApp::to($writeOff->requester, NotificationType::PENGHAPUSAN,
                "Penghapusan \"{$item->name}\" DISETUJUI Wakasek Sarpras.", route('items.show', $item));
        });

        return back()->with('success', 'Penghapusan disetujui — stok barang dikurangi & status diperbarui.');
    }

    public function reject(Request $request, WriteOff $writeOff): RedirectResponse
    {
        abort_unless($writeOff->isPending(), 404);

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']]);

        $writeOff->update([
            'status' => 'REJECTED',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        activity('penghapusan')
            ->performedOn($writeOff)
            ->causedBy($request->user())
            ->log('Menolak penghapusan barang');

        NotifyApp::to($writeOff->requester, NotificationType::PENGHAPUSAN,
            'Penghapusan "'.$writeOff->item->name.'" DITOLAK'.($data['decision_note'] ? ' — '.$data['decision_note'] : ''),
            route('write-offs.index'));

        return back()->with('success', 'Permohonan penghapusan ditolak.');
    }
}
