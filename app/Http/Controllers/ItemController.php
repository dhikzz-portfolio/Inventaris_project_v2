<?php

namespace App\Http\Controllers;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Models\AcquisitionAllocation;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use App\Services\AcquisitionService;
use App\Services\AssetUnitService;
use App\Services\IdGenerator;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use App\Services\LoanService;
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

        $catalogs = Item::query()
            ->whereNull('archived_at')
            ->approved()
            ->with(['category:id,name', 'specifications:id,item_catalog_id,brand,model_type'])
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        $catalogMap = $catalogs->mapWithKeys(fn (Item $catalog): array => [
            $catalog->id => [
                'name' => $catalog->name,
                'category_id' => $catalog->category_id,
                'category_name' => $catalog->category?->name,
                'brands' => $catalog->specifications->pluck('brand')->filter()->unique()->values()->all(),
                'models' => $catalog->specifications
                    ->map(fn (Specification $spec): array => ['brand' => $spec->brand, 'model' => $spec->model_type])
                    ->values()
                    ->all(),
            ],
        ]);

        return view('items.create', [
            'categories' => Category::orderBy('name')->get(),
            'catalogs' => $catalogs,
            'catalogMap' => $catalogMap,
            'brands' => Specification::query()
                ->select('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'rooms' => $rooms,
            'roomOptions' => $roomOptions,
            'buildings' => Building::query()->whereIn('id', $rooms->pluck('building_id')->filter()->unique())
                ->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'required_without:catalog_id', 'string', 'max:255'],
            'catalog_id' => ['nullable', 'integer', 'exists:item_catalogs,id'],
            'category_id' => ['nullable', 'required_without:catalog_id', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_type' => ['nullable', 'string', 'max:100'],
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

        $total = (int) $data['quantity'];
        $allocations = $this->normalizeAllocations($request, $data, $total);
        $this->assertAllocationsValid($allocations, $total);

        $firstRoom = Room::findOrFail($allocations->first()['room_id']);

        $existingCatalog = null;

        if (filled($data['catalog_id'] ?? null)) {
            $existingCatalog = Item::query()->with('category')->whereKey($data['catalog_id'])->first();

            if ($existingCatalog === null || $existingCatalog->archived_at !== null
                || ! $existingCatalog->isRecordedInInventory()) {
                throw ValidationException::withMessages([
                    'catalog_id' => 'Katalog yang dipilih tidak tersedia atau sudah diarsipkan.',
                ]);
            }

            // Katalog terpilih menentukan nama & kategori. Kategori yang
            // berbeda dari katalog tidak boleh diterima dari request manual.
            if (filled($data['category_id'] ?? null)
                && (int) $data['category_id'] !== (int) $existingCatalog->category_id) {
                throw ValidationException::withMessages([
                    'category_id' => 'Kategori tidak boleh berbeda dari kategori katalog yang dipilih.',
                ]);
            }

            $data['name'] = $existingCatalog->name;
            $data['category_id'] = $existingCatalog->category_id;
        } else {
            $existingCatalog = Item::query()
                ->where('category_id', $data['category_id'])
                ->where('name', $data['name'])
                ->first();
        }

        $category = $existingCatalog?->category ?? Category::findOrFail($data['category_id']);

        $createdAllocations = collect();

        DB::transaction(function () use ($data, $category, $firstRoom, $total, $allocations, $request, $existingCatalog, &$createdAllocations) {
            $isWakasek = $request->user()->isApproverLevel2();
            $isNewCatalog = $existingCatalog === null;

            // Pengadaan oleh pihak selain Wakasek selalu dimulai sebagai draft;
            // unit baru hanya terbit setelah barang benar-benar diterima.
            $initialStatus = $isWakasek
                ? AcquisitionStatus::INVENTARIS_TERCATAT->value
                : AcquisitionStatus::DRAFT->value;

            $item = $existingCatalog ?? Item::create([
                'internal_id' => IdGenerator::internalId($firstRoom),
                'location_code' => InstitutionSetting::current()->location_code,
                'register_no' => IdGenerator::register(null)['register_no'],
                'name' => $data['name'],
                'category_id' => $category->id,
                'room_id' => $firstRoom->id,
                'brand' => Specification::normalizeBrand($data['brand'] ?? null) === Specification::BRAND_UNSPECIFIED ? '' : trim((string) $data['brand']),
                'model_type' => Specification::normalizeModel($data['model_type'] ?? null) === Specification::MODEL_UNSPECIFIED ? '' : trim((string) $data['model_type']),
                'specification' => $data['specification'] ?? null,
                'acquisition_year' => $data['acquisition_year'],
                'funding_source' => $data['funding_source'],
                'quantity' => $total,
                'unit' => $data['unit'],
                'is_high_value' => $request->boolean('is_high_value'),
                'status' => ItemStatus::BAIK->value,
                'photo_path' => $request->hasFile('photo') ? $request->file('photo')->store('items', 'public') : null,
                'acquisition_status' => $initialStatus,
                'acquisition_requested_by' => $request->user()->id,
                'acquisition_approved_by' => $isWakasek ? $request->user()->id : null,
                'acquisition_decided_at' => $isWakasek ? now() : null,
                'acquisition_received_by' => $isWakasek ? $request->user()->id : null,
                'acquisition_received_at' => $isWakasek ? now() : null,
                'acquisition_received_quantity' => $isWakasek ? $total : null,
                'acquisition_received_condition' => $isWakasek ? Condition::BAIK->value : null,
            ]);

            // Merek + tipe/model menjadi spesifikasi katalog, bukan sekadar
            // teks pada transaksi pengadaan.
            $specification = Specification::resolveFor(
                $item,
                $data['brand'] ?? null,
                $data['model_type'] ?? null,
                $data['specification'] ?? null,
            );

            foreach ($allocations as $allocation) {
                $createdAllocations->push(AcquisitionAllocation::create([
                    'item_catalog_id' => $item->id,
                    'specification_id' => $specification->id,
                    'room_id' => $allocation['room_id'],
                    'quantity' => $allocation['quantity'],
                ]));
            }

            activity('pengadaan')
                ->performedOn($item)
                ->causedBy($request->user())
                ->withProperties([
                    'alokasi' => $allocations->all(),
                    'merek' => $specification->brand,
                    'tipe_model' => $specification->model_type,
                ])
                ->log($isNewCatalog
                    ? ($isWakasek
                        ? 'Menambah barang via pengadaan multi-lokasi (disetujui langsung oleh Wakasek)'
                        : 'Menyimpan draft pengajuan pengadaan barang baru (belum dikirim)')
                    : 'Menambah stok spesifikasi pada katalog yang sudah ada');

            if ($isWakasek && $item->isRecordedInInventory()) {
                $this->provisionAllocations($item->fresh(), $createdAllocations, $request->user());
            }
        });

        if ($request->user()->isApproverLevel2()) {
            return redirect()->route('catalogs.index')
                ->with('success', 'Barang berhasil ditambahkan. Kode inventaris unit diterbitkan otomatis sesuai alokasi lokasi.');
        }

        return redirect()->route('items.acquisitions')
            ->with('success', 'Pengajuan tersimpan sebagai draft. Kirim pengajuan kepada Ketua Laboran untuk diproses.');
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

    /**
     * Ruang kerja pengadaan. Wakasek melihat seluruh pengajuan, Ketua Laboran
     * seluruh laboratorium, dan Laboran hanya pengajuan pada ruang lingkupnya.
     */
    public function acquisitions(Request $request): View
    {
        $user = $request->user();

        $requests = Item::query()
            ->with(['room', 'category', 'requester', 'confirmer', 'approver', 'receiver'])
            ->whereIn('acquisition_status', AcquisitionStatus::values())
            ->when($user->isLaboran(), fn ($query) => $query->where(function ($scope) use ($user) {
                $scope->where('acquisition_requested_by', $user->id)
                    ->orWhereHas('allocations.room', fn ($room) => $room->whereIn('id', $user->roomScopes()->select('room_id')));
            }))
            ->when(! $user->isSuperAdmin() && ! $user->isLaboran(), fn ($query) => $query->where(function ($scope) {
                $scope->whereHas('room', fn ($room) => $room->where('is_lab', true))
                    ->orWhereHas('allocations.room', fn ($room) => $room->where('is_lab', true));
            }))
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return view('items.acquisitions', [
            'groups' => $requests->groupBy('acquisition_status'),
            'statuses' => AcquisitionStatus::cases(),
            'user' => $user,
        ]);
    }

    /** Tahap 1 — kirim draft kepada Ketua Laboran. */
    public function submitAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);
        $acquisitions->submit($item, $request->user());

        return back()->with('success', "Pengajuan \"{$item->name}\" dikirim ke Ketua Laboran.");
    }

    /** Tahap 2 — konfirmasi Ketua Laboran (bukan persetujuan akhir). */
    public function confirmAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        abort_unless($request->user()->isKetuaLaboran(), 403);

        $acquisitions->confirm($item, $request->user());

        return back()->with('success', "Pengajuan \"{$item->name}\" dikonfirmasi dan diteruskan ke Wakasek Sarpras.");
    }

    /** Tahap 3 — persetujuan akhir Wakasek Sarpras. */
    public function approveAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        abort_unless($request->user()->isApproverLevel2(), 403);

        $acquisitions->approve($item, $request->user());

        return back()->with('success', "Pengadaan \"{$item->name}\" disetujui. Barang belum menjadi inventaris sampai diterima dan dicatat.");
    }

    public function rejectAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        abort_unless($request->user()->isApproverLevel2(), 403);

        $note = (string) $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $acquisitions->reject($item, $request->user(), $note);

        return back()->with('success', "Pengadaan \"{$item->name}\" ditolak.");
    }

    /** Laboran memulai proses pengadaan setelah disetujui. */
    public function startAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);
        $acquisitions->start($item, $request->user());

        return back()->with('success', "Proses pengadaan \"{$item->name}\" dimulai.");
    }

    /** Form penerimaan barang: jumlah aktual, kondisi, dan catatan. */
    public function receiveForm(Request $request, Item $item): View
    {
        $this->ensureVisible($item);

        abort_unless(in_array($item->acquisition_status, [
            AcquisitionStatus::DISETUJUI->value,
            AcquisitionStatus::DALAM_PROSES->value,
        ], true), 404);

        return view('items.receive', [
            'item' => $item->load(['room', 'category', 'allocations.room', 'allocations.specification']),
            'conditions' => Condition::cases(),
        ]);
    }

    public function receiveAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);

        $data = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(array_column(Condition::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'received.required' => 'Isi jumlah aktual barang yang diterima untuk setiap lokasi.',
        ]);

        $acquisitions->receive(
            $item,
            $request->user(),
            array_map('intval', $data['received']),
            $data['condition'],
            $data['note'] ?? null,
        );

        return redirect()->route('items.acquisitions')
            ->with('success', "Penerimaan \"{$item->name}\" dicatat. Lanjutkan dengan pencatatan inventaris.");
    }

    /** Menerbitkan unit inventaris dari barang yang sudah diterima. */
    public function recordInventory(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);

        $result = $acquisitions->recordInventory($item, $request->user());

        return back()->with('success', "Barang \"{$result->name}\" tercatat sebagai inventaris resmi.");
    }

    /**
     * Ruangan yang boleh dipilih: Super Admin semua, Ketua Laboran seluruh
     * laboratorium, Laboran/K3 hanya laboratorium yang menjadi tanggung jawabnya.
     */
    private function availableRooms(?User $user)
    {
        return Room::with('major')
            ->when(! $user?->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
            ->when($user?->isLaboran(), fn ($query) => $query->whereIn('id', $user->roomScopes()->select('room_id')))
            ->orderBy('code')
            ->get();
    }

    private function roomRule(User $user): Exists
    {
        $rule = Rule::exists('rooms', 'id');

        if ($user->isSuperAdmin()) {
            return $rule;
        }

        $rule = $rule->where('is_lab', true);

        if ($user->isLaboran()) {
            $rule = $rule->whereIn('id', $user->roomScopes()->select('room_id'));
        }

        return $rule;
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
     * Terbitkan unit untuk alokasi yang belum diproses. Setiap unit mewarisi
     * spesifikasi alokasinya sehingga stok per merek/tipe tetap terpisah.
     *
     * @param  Collection<int, AcquisitionAllocation>  $allocations
     */
    private function provisionAllocations(Item $item, Collection $allocations, ?User $actor = null): void
    {
        $service = app(AssetUnitService::class);
        $defaultSpecificationId = Specification::resolveFor($item, $item->brand, $item->model_type)->id;

        foreach ($allocations as $allocation) {
            $room = $allocation->room ?: Room::find($allocation->room_id);

            if ($room === null) {
                continue;
            }

            for ($i = 0; $i < (int) $allocation->quantity; $i++) {
                $service->create($item, $room, [
                    'specification_id' => $allocation->specification_id ?: $defaultSpecificationId,
                ], $actor);
            }

            if ($allocation->exists) {
                $allocation->forceFill(['provisioned_at' => now()])->save();
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
