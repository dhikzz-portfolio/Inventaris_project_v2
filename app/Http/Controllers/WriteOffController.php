<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\NotificationType;
use App\Models\Item;
use App\Models\WriteOff;
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
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'berita_acara' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $item = Item::findOrFail($data['item_id']);

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

            $writeOff->update([
                'status' => 'APPROVED',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $newQty = max(0, $item->quantity - $writeOff->quantity);

            // Kurangi counter kondisi agar unit yang dihapus tidak dihitung dua kali
            // (sekali sebagai rusak/hilang, sekali sebagai stok yang sudah dikurangi).
            $cut = $writeOff->quantity;
            $lostCut = min($item->qty_lost, $cut);
            $item->qty_lost -= $lostCut;
            $cut -= $lostCut;
            $damagedCut = min($item->qty_damaged, $cut);
            $item->qty_damaged -= $damagedCut;

            $item->quantity = $newQty;
            if ($newQty === 0) {
                $item->status = ItemStatus::DIHAPUS;
            }
            $item->save();

            activity('penghapusan')
                ->performedOn($writeOff)
                ->causedBy($request->user())
                ->log("Menyetujui penghapusan {$item->name} (sisa stok: {$newQty})");

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
