<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Enums\NotificationType;
use App\Models\AcquisitionAllocation;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\IdGenerator;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use App\Services\LoanService;
use App\Services\NotifyApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ItemController extends Controller
{
    /** Katalog dicari/difilter lewat komponen Livewire. */
    public function index(): View
    {
        return view('items.index');
    }

    public function show(Item $item): View
    {
        $this->ensureVisible($item);

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

    /**
     * Form pengadaan multi-lokasi.
     *
     * Bagian A: katalog & jumlah pengadaan. Bagian B: alokasi beberapa
     * ruangan sekaligus. Pratinjau kode inventaris dihitung backend dan
     * ditampilkan baca-saja; pengguna tidak pernah mengetik kode.
     */
    public function create(Request $request): View
    {
        $rooms = $this->availableRooms($request->user());
        $codes = app(InventoryCodeService::class);

        $roomOptions = $rooms->map(function (Room $room) use ($codes): array {
            $preview = null;

            try {
                $preview = $codes->preview($room, null, null);
            } catch (Throwable) {
                $preview = null;
            }

            return [
                'id' => $room->id,
                'building_id' => $room->building_id,
                'building' => $room->buildingCode(),
                'building_name' => $room->building?->name ?: 'Gedung '.$room->buildingCode(),
                'floor' => (int) $room->floor,
                'room_no' => (int) $room->room_no,
                'name' => $room->name,
                'code' => $room->code,
                'preview' => $preview,
            ];
        })->values();

        return view('items.create', [
            'categories' => Category::orderBy('name')->get(),
            'rooms' => $rooms,
            'roomOptions' => $roomOptions,
            'buildings' => Building::query()->whereIn('id', $rooms->pluck('building_id')->filter()->unique())
                ->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'specification' => ['nullable', 'string'],
            'acquisition_year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'funding_source' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:30'],
            'is_high_value' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
            // Kompatibilitas form lama satu ruangan.
            'room_id' => ['nullable', $this->roomRule($request->user())],
            'allocations' => ['nullable', 'array'],
            'allocations.*.room_id' => ['required_with:allocations', 'integer', 'distinct', $this->roomRule($request->user())],
            'allocations.*.quantity' => ['required_with:allocations', 'integer', 'min:1'],
        ], [
            'allocations.*.room_id.distinct' => 'Satu ruangan hanya boleh muncul sekali pada daftar alokasi.',
            'allocations.*.quantity.min' => 'Jumlah alokasi setiap lokasi minimal 1 unit.',
        ]);

        $category = Category::findOrFail($data['category_id']);
        $total = (int) $data['quantity'];
        $allocations = $this->normalizeAllocations($request, $data, $total);
        $this->assertAllocationsValid($allocations, $total);

        $firstRoom = Room::findOrFail($allocations->first()['room_id']);

        DB::transaction(function () use ($data, $category, $firstRoom, $total, $allocations, $request) {
            $register = IdGenerator::register(null);
            $isWakasek = $request->user()->isApproverLevel2();

            $item = Item::create([
                'internal_id' => IdGenerator::internalId($firstRoom),
                'location_code' => InstitutionSetting::current()->location_code,
                'register_no' => $register['register_no'],
                'name' => $data['name'],
                'category_id' => $category->id,
                'room_id' => $firstRoom->id,
                'specification' => $data['specification'] ?? null,
                'acquisition_year' => $data['acquisition_year'],
                'funding_source' => $data['funding_source'],
                'quantity' => $total,
                'unit' => $data['unit'],
                'is_high_value' => $request->boolean('is_high_value'),
                'status' => ItemStatus::BAIK->value,
                'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('items', 'public') : null,
                'acquisition_status' => $isWakasek ? 'APPROVED' : 'PENDING',
                'acquisition_requested_by' => $request->user()->id,
                'acquisition_approved_by' => $isWakasek ? $request->user()->id : null,
                'acquisition_decided_at' => $isWakasek ? now() : null,
            ]);

            foreach ($allocations as $allocation) {
                AcquisitionAllocation::create([
                    'item_catalog_id' => $item->id,
                    'room_id' => $allocation['room_id'],
                    'quantity' => $allocation['quantity'],
                ]);
            }

            activity('pengadaan')
                ->performedOn($item)
                ->causedBy($request->user())
                ->withProperties(['alokasi' => $allocations->all()])
                ->log($isWakasek
                    ? 'Menambah barang via pengadaan multi-lokasi (disetujui langsung oleh Wakasek)'
                    : 'Mengajukan pengadaan barang baru (menunggu approval Wakasek)');

            if ($isWakasek) {
                $this->provisionUnits($item, $request->user());
            } else {
                NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGADAAN,
                    "Pengadaan baru: \"{$item->name}\" ({$total} {$item->unit}) menunggu persetujuan.",
                    route('items.acquisitions'));
            }
        });

        return redirect()->route('catalogs.index')
            ->with('success', 'Barang berhasil ditambahkan.'.($request->user()->isApproverLevel2()
                ? ' Kode inventaris unit diterbitkan otomatis sesuai alokasi lokasi.'
                : ' Menunggu persetujuan Wakasek Sarpras sebelum kode unit diterbitkan.'));
    }

    /**
     * Ubah input alokasi (banyak baris) menjadi koleksi unik per ruangan.
     * Form lama tanpa alokasi diperlakukan sebagai satu alokasi.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{room_id: int, quantity: int}>
     */
    private function normalizeAllocations(Request $request, array $data, int $total): Collection
    {
        $rows = collect($request->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && filled($row['room_id'] ?? null) && filled($row['quantity'] ?? null))
            ->map(fn (array $row) => [
                'room_id' => (int) $row['room_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values();

        if ($rows->isEmpty() && filled($data['room_id'] ?? null)) {
            $rows = collect([['room_id' => (int) $data['room_id'], 'quantity' => $total]]);
        }

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tambahkan minimal satu lokasi alokasi untuk unit yang dibeli.',
            ]);
        }

        return $rows;
    }

    /**
     * Validasi backend (bukan hanya frontend): total alokasi harus tepat sama
     * dengan jumlah pengadaan dan setiap ruangan harus mampu menampung nomor
     * unit baru tanpa melewati batas 99.
     *
     * @param  Collection<int, array{room_id: int, quantity: int}>  $allocations
     */
    private function assertAllocationsValid(Collection $allocations, int $total): void
    {
        $sum = (int) $allocations->sum('quantity');

        if ($sum !== $total) {
            throw ValidationException::withMessages([
                'allocations' => "Total alokasi ({$sum} unit) harus sama dengan jumlah pengadaan ({$total} unit). Sisa yang belum dialokasikan: ".($total - $sum).' unit.',
            ]);
        }

        $codes = app(InventoryCodeService::class);

        foreach ($allocations as $index => $allocation) {
            $room = Room::find($allocation['room_id']);

            if ($room === null) {
                continue;
            }

            try {
                $next = $codes->nextUnitNo($room);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.quantity" => $exception->validator->errors()->first(),
                ]);
            }

            $availableSlots = InventoryCodeService::MAX_UNIT_NO - $next + 1;

            if ($allocation['quantity'] > $availableSlots) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.quantity" => "Ruangan {$room->code} hanya memiliki {$availableSlots} nomor unit tersisa (maksimal 99 seumur hidup).",
                ]);
            }
        }
    }

    public function edit(Request $request, Item $item): View
    {
        $this->ensureVisible($item);

        return view('items.edit', [
            'item' => $item,
            'categories' => Category::orderBy('name')->get(),
            'rooms' => $this->availableRooms($request->user()),
        ]);
    }

    public function update(Request $request, Item $item, LoanService $loanService): RedirectResponse
    {
        $this->ensureVisible($item);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'room_id' => ['required', $this->roomRule($request->user())],
            'specification' => ['nullable', 'string'],
            'acquisition_year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'funding_source' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
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

        return redirect()->route('catalogs.show', $item)->with('success', 'Data katalog diperbarui.');
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

        DB::transaction(function () use ($request, $item) {
            // Kunci katalog agar dua approval bersamaan tidak menggandakan unit.
            $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            abort_unless($item->isAcquisitionPending(), 404);

            $item->update([
                'acquisition_status' => 'APPROVED',
                'acquisition_approved_by' => $request->user()->id,
                'acquisition_decided_at' => now(),
            ]);

            // Unit fisik baru diterbitkan setelah pengadaan disetujui agar kode
            // inventaris tidak terpakai untuk pengajuan yang ditolak.
            $this->provisionUnits($item, $request->user());
        });

        activity('pengadaan')->performedOn($item)->causedBy($request->user())->log('Menyetujui pengadaan barang');

        if ($request->user()->id !== (int) $item->acquisition_requested_by) {
            NotifyApp::toRoles(['KETUA_LABORAN'], NotificationType::PENGADAAN,
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

        NotifyApp::toRoles(['KETUA_LABORAN'], NotificationType::PENGADAAN,
            "Pengadaan \"{$item->name}\" DITOLAK".($note ? " — {$note}" : ''), route('items.acquisitions'));

        return back()->with('success', "Pengadaan \"{$item->name}\" ditolak.");
    }

    public function specificationOptions(Request $request)
    {
        $categoryId = $request->query('category_id');
        $specifications = Specification::when($categoryId, fn ($q) => $q->where('item_catalog_id', function ($query) use ($categoryId) {
            $query->from('item_catalogs')
                ->whereRaw("id IN (SELECT item_catalog_id FROM items WHERE category_id = $categoryId)")
                ->limit(1);
        }))
            ->when(! $categoryId, fn ($q) => $q->latest())
            ->get(['id', 'brand', 'model_type', 'spec_code', 'description']);

        return response()->json($specifications);
    }

    /** Ruangan yang boleh dipilih: Super Admin semua, Ketua Laboran hanya lab. */
    private function availableRooms(?User $user)
    {
        return Room::with('major')
            ->when(! $user?->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
            ->orderBy('code')
            ->get();
    }

    private function roomRule(User $user): Exists
    {
        $rule = Rule::exists('rooms', 'id');

        return $user->isSuperAdmin() ? $rule : $rule->where('is_lab', true);
    }

    /** Katalog di luar cakupan tidak boleh dibuka lewat URL langsung. */
    private function ensureVisible(Item $item): void
    {
        abort_unless(
            Item::query()->visibleTo(auth()->user())->whereKey($item->id)->exists(),
            404
        );
    }

    /**
     * Terbitkan unit fisik sesuai alokasi lokasi. Dipanggil di dalam
     * transaksi approval/pengadaan langsung. Jumlah unit yang dibuat harus
     * sama dengan total alokasi; kegagalan satu ruangan membatalkan semuanya.
     */
    private function provisionUnits(Item $item, ?User $actor = null): void
    {
        if ($item->units()->exists()) {
            return;
        }

        $allocations = $item->allocations()->with('room')->get();

        // Katalog legacy tanpa alokasi tetap memakai ruangan katalognya.
        if ($allocations->isEmpty()) {
            $allocations = collect([
                new AcquisitionAllocation([
                    'room_id' => $item->room_id,
                    'quantity' => (int) $item->quantity,
                ]),
            ]);
        }

        $service = app(AssetUnitService::class);

        foreach ($allocations as $allocation) {
            $room = $allocation->room ?: Room::find($allocation->room_id);

            if ($room === null) {
                continue;
            }

            for ($i = 0; $i < (int) $allocation->quantity; $i++) {
                $service->create($item, $room, [], $actor);
            }
        }

        // Samakan kolom kuantitas legacy dengan jumlah unit yang benar-benar ada.
        $service->syncCatalogCounters($item->refresh());
    }

    /** Cetak label satuan / re-print dari halaman detail barang. */
    public function printLabel(LabelService $labels, Item $item)
    {
        $this->ensureVisible($item);

        return $labels->printSingle($item)->stream('label-'.$item->internal_id.'.pdf');
    }
}
