<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\NotificationType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Services\IdGenerator;
use App\Services\LabelService;
use App\Services\LoanService;
use App\Services\NotifyApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItemController extends Controller
{
    /** Katalog dicari/difilter lewat komponen Livewire. */
    public function index(): View
    {
        return view('items.index');
    }

    public function show(Item $item): View
    {
        $item->load(['room.major', 'category', 'movements.mover', 'movements.fromRoom', 'movements.toRoom']);

        $loanHistory = $item->loanItems()
            ->with(['loan.borrower', 'loan.event'])
            ->latest()
            ->limit(20)
            ->get();

        return view('items.show', [
            'item' => $item,
            'loanHistory' => $loanHistory,
            'lastBorrower' => $item->lastBorrower(),
            'upcoming' => $item->loanItems()
                ->with('loan')
                ->whereHas('loan', fn ($q) => $q->whereIn('status', ['PENDING', 'APPROVED', 'DIPINJAM']))
                ->get()
                ->map(fn ($li) => $li->loan)
                ->unique('id')
                ->values(),
        ]);
    }

    /** Form pengadaan (tambah barang baru). */
    public function create(): View
    {
        return view('items.create', [
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::with('major')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'specification' => ['nullable', 'string'],
            'acquisition_year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'funding_source' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_high_value' => ['nullable', 'boolean'],
            'location_code' => ['required', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $category = Category::findOrFail($data['category_id']);
        $room = Room::findOrFail($data['room_id']);

        DB::transaction(function () use ($data, $category, $room, $request) {
            $register = IdGenerator::register($category->code);

            $isWakasek = $request->user()->isApproverLevel2();

            $item = Item::create([
                'internal_id' => IdGenerator::internalId($data['name'], $room->code),
                'location_code' => $data['location_code'],
                'asset_code' => $register['asset_code'],
                'register_no' => $register['register_no'],
                'name' => $data['name'],
                'category_id' => $category->id,
                'room_id' => $room->id,
                'specification' => $data['specification'] ?? null,
                'acquisition_year' => $data['acquisition_year'],
                'funding_source' => $data['funding_source'],
                'quantity' => $data['quantity'],
                'unit' => $data['unit'],
                'min_stock' => $data['min_stock'] ?? null,
                'is_high_value' => $request->boolean('is_high_value'),
                'status' => ItemStatus::BAIK->value,
                'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('items', 'public') : null,
                'acquisition_status' => $isWakasek ? 'APPROVED' : 'PENDING',
                'acquisition_requested_by' => $request->user()->id,
                'acquisition_approved_by' => $isWakasek ? $request->user()->id : null,
                'acquisition_decided_at' => $isWakasek ? now() : null,
            ]);

            activity('pengadaan')
                ->performedOn($item)
                ->causedBy($request->user())
                ->log($isWakasek
                    ? 'Menambah barang via pengadaan (disetujui langsung oleh Wakasek)'
                    : 'Mengajukan pengadaan barang baru (menunggu approval Wakasek)');

            if (! $isWakasek) {
                NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGADAAN,
                    "Pengadaan baru: \"{$item->name}\" ({$item->quantity} {$item->unit}) menunggu persetujuan.",
                    route('items.acquisitions'));
            }
        });

        return redirect()->route('items.index')
            ->with('success', 'Barang berhasil ditambahkan.'.($request->user()->isApproverLevel2() ? '' : ' Menunggu persetujuan Wakasek Sarpras.'));
    }

    public function edit(Item $item): View
    {
        return view('items.edit', [
            'item' => $item,
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::with('major')->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Item $item, LoanService $loanService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'room_id' => ['required', 'exists:rooms,id'],
            'specification' => ['nullable', 'string'],
            'acquisition_year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'funding_source' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_high_value' => ['nullable', 'boolean'],
            'location_code' => ['required', 'string', 'max:50'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $roomChanged = (int) $data['room_id'] !== (int) $item->room_id;
        $newRoom = Room::findOrFail($data['room_id']);
        $oldPhoto = $item->photo_path;

        DB::transaction(function () use ($data, $item, $request, $roomChanged, $newRoom, $oldPhoto, $loanService) {
            $photo = $request->hasFile('photo') ? $request->file('photo')->store('items', 'public') : $item->photo_path;

            // Ruangan sengaja tidak diubah langsung: setiap perpindahan wajib tercatat
            // sebagai mutasi resmi agar riwayat lokasi dan ID spasial tetap konsisten.
            $item->update(array_merge(
                collect($data)->except(['room_id'])->all(),
                ['photo_path' => $photo]
            ));

            if ($roomChanged) {
                $loanService->recordMovement(
                    $item->fresh('room'),
                    $newRoom,
                    $request->user(),
                    'Perubahan ruangan melalui formulir edit barang'
                );
            }

            if ($request->hasFile('photo') && $oldPhoto) {
                \Storage::disk('public')->delete($oldPhoto);
            }

            activity('barang')
                ->performedOn($item)
                ->causedBy($request->user())
                ->log('Mengubah data barang'.($roomChanged ? ' (pindah ruangan — ID Internal diperbarui)' : ''));
        });

        return redirect()->route('items.show', $item)->with('success', 'Data barang diperbarui.');
    }

    /** Approval pengadaan (Wakasek). */
    public function acquisitions(): View
    {
        return view('items.acquisitions', [
            'pending' => Item::with(['room', 'category', 'requester'])
                ->where('acquisition_status', 'PENDING')
                ->latest()
                ->get(),
            'decided' => Item::with(['room', 'requester', 'approver'])
                ->whereIn('acquisition_status', ['APPROVED', 'REJECTED'])
                ->whereNotNull('acquisition_decided_at')
                ->latest('acquisition_decided_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function approveAcquisition(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->isAcquisitionPending(), 404);

        $item->update([
            'acquisition_status' => 'APPROVED',
            'acquisition_approved_by' => $request->user()->id,
            'acquisition_decided_at' => now(),
        ]);

        activity('pengadaan')->performedOn($item)->causedBy($request->user())->log('Menyetujui pengadaan barang');

        if ($request->user()->id !== (int) $item->acquisition_requested_by) {
            NotifyApp::toRoles(['ADMIN_SARPRAS'], NotificationType::PENGADAAN,
                "Pengadaan \"{$item->name}\" DISETUJUI Wakasek Sarpras.", route('items.show', $item));
        }

        return back()->with('success', "Pengadaan \"{$item->name}\" disetujui dan masuk katalog.");
    }

    public function rejectAcquisition(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->isAcquisitionPending(), 404);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        $item->update([
            'acquisition_status' => 'REJECTED',
            'acquisition_approved_by' => $request->user()->id,
            'acquisition_decided_at' => now(),
            'acquisition_note' => $note,
        ]);

        activity('pengadaan')->performedOn($item)->causedBy($request->user())->log('Menolak pengadaan barang'.($note ? ": {$note}" : ''));

        NotifyApp::toRoles(['ADMIN_SARPRAS'], NotificationType::PENGADAAN,
            "Pengadaan \"{$item->name}\" DITOLAK".($note ? " — {$note}" : ''), route('items.acquisitions'));

        return back()->with('success', "Pengadaan \"{$item->name}\" ditolak.");
    }

    /** Cetak label satuan / re-print dari halaman detail barang. */
    public function printLabel(LabelService $labels, Item $item)
    {
        return $labels->printSingle($item)->stream('label-'.$item->internal_id.'.pdf');
    }
}
