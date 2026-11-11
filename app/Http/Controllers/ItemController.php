<?php

namespace App\Http\Controllers;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
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

        $item->load([
            'room.major', 'category', 'movements.mover', 'movements.fromRoom', 'movements.toRoom',
            'allocations.room', 'allocations.specification',
            'requester', 'confirmer', 'approver',
        ]);

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

        // Pemilihan katalog lama hanya untuk pengelola (Wakasek/Admin).
        // Pengajuan Operator selalu berupa barang baru agar masuk antrean
        // validasi; katalog yang sudah tercatat tidak boleh dipilih.
        $canSelectExistingCatalog = $request->user()->isApproverLevel2();

        $catalogs = $canSelectExistingCatalog
            ? Item::query()
                ->whereNull('archived_at')
                ->approved()
                ->with(['category:id,name', 'specifications:id,item_catalog_id,brand,model_type'])
                ->orderBy('name')
                ->get(['id', 'name', 'category_id'])
            : collect();

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
            'conditions' => Condition::cases(),
            'statuses' => UnitStatus::cases(),
            'catalogs' => $catalogs,
            'catalogMap' => $catalogMap,
            'brands' => Specification::query()
                ->select('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'rooms' => $rooms,
            'roomOptions' => $roomOptions,
            'buildings' => Building::query()->whereIn('id', $rooms->pluck('building_id')->filter()->unique())
                ->orderBy('code')->get(),
            'canSelectExistingCatalog' => $canSelectExistingCatalog,
        ]);
    }

    public function store(Request $request, AcquisitionService $acquisitions): RedirectResponse
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
            'allocations.*.room_id' => ['required_with:allocations', 'integer', $this->roomRule($request->user())],
            'allocations.*.quantity' => ['required_with:allocations', 'integer', 'min:1'],
            'allocations.*.condition' => ['nullable', Rule::in(array_column(Condition::cases(), 'value'))],
            'allocations.*.status' => ['nullable', Rule::in(array_column(UnitStatus::cases(), 'value'))],
            'allocations.*.note' => ['nullable', 'string', 'max:500'],
            // Kelompok spesifikasi: satu katalog dapat berisi beberapa merek/tipe
            // dengan tahun, sumber dana, dan jumlah masing-masing.
            'spec_groups' => ['nullable', 'array'],
            'spec_groups.*.brand' => ['nullable', 'string', 'max:100'],
            'spec_groups.*.model_type' => ['nullable', 'string', 'max:100'],
            'spec_groups.*.acquisition_year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'spec_groups.*.funding_source' => ['nullable', 'string', 'max:100'],
            'spec_groups.*.quantity' => ['required_with:spec_groups', 'integer', 'min:1'],
            'spec_groups.*.allocations' => ['required_with:spec_groups', 'array', 'min:1'],
            'spec_groups.*.allocations.*.room_id' => ['required', 'integer', $this->roomRule($request->user())],
            'spec_groups.*.allocations.*.quantity' => ['required', 'integer', 'min:1'],
            'spec_groups.*.allocations.*.condition' => ['nullable', Rule::in(array_column(Condition::cases(), 'value'))],
            'spec_groups.*.allocations.*.status' => ['nullable', Rule::in(array_column(UnitStatus::cases(), 'value'))],
            'spec_groups.*.allocations.*.note' => ['nullable', 'string', 'max:500'],
        ], [
            'allocations.*.quantity.min' => 'Jumlah alokasi setiap lokasi minimal 1 unit.',
        ]);

        $total = (int) $data['quantity'];

        // Nama katalog harus unik lintas kategori. Tanpa pengecekan ini barang
        // yang sama dapat tercatat dua kali dengan kategori berbeda.
        if (blank($data['catalog_id'] ?? null) && filled($data['name'] ?? null)) {
            $duplicateName = Item::query()
                ->with('category')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $data['name']))])
                ->first();

            if ($duplicateName !== null && (int) $duplicateName->category_id !== (int) $data['category_id']) {
                throw ValidationException::withMessages([
                    'name' => "Katalog \"{$duplicateName->name}\" sudah ada dengan kategori \"".($duplicateName->category?->name ?? '—').'". Pilih katalog tersebut dari daftar agar tidak terjadi penggandaan data dengan kategori berbeda.',
                ]);
            }
        }

        if ($total < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah pengadaan minimal 1 unit.',
            ]);
        }

        // Mode stok (bawaan revisi V9): pengadaan hanya mencatat katalog dan
        // jumlah. Spesifikasi dan ruangan ditentukan saat distribusi.
        // Mode alokasi tetap didukung untuk data dan pengadaan yang sudah ada.
        $requestedMode = $request->input('procurement_mode');
        $usesAllocations = $requestedMode === 'ALOKASI'
            || ($requestedMode === null && (filled($request->input('spec_groups'))
                || filled($request->input('allocations'))
                || filled($request->input('room_id'))));

        $hasSpecGroups = $usesAllocations && filled($request->input('spec_groups'));
        $groups = $usesAllocations ? $this->normalizeSpecGroups($request, $data, $total) : collect();

        if ($usesAllocations) {
            $this->assertAllocationsValid($groups, $total, $hasSpecGroups);
        }

        $firstRoom = $usesAllocations
            ? Room::findOrFail($groups->first()['allocations'][0]['room_id'])
            : null;

        $existingCatalog = null;
        $requesterIsApprover = $request->user()->isApproverLevel2();

        if (filled($data['catalog_id'] ?? null)) {
            $existingCatalog = Item::query()->with('category')->whereKey($data['catalog_id'])->first();

            if ($existingCatalog === null || $existingCatalog->archived_at !== null
                || ! $existingCatalog->isRecordedInInventory()) {
                throw ValidationException::withMessages([
                    'catalog_id' => 'Katalog yang dipilih tidak tersedia atau sudah diarsipkan.',
                ]);
            }

            // Operator tidak dapat menumpang pengajuan pada katalog yang sudah
            // tercatat: status katalog tidak boleh kembali menunggu validasi dan
            // penambahan stoknya hanya sah lewat penerimaan + distribusi oleh
            // pengelola. Sebelumnya jalur ini hanya menulis alokasi tanpa unit
            // dan menampilkan pesan sukses palsu.
            if (! $requesterIsApprover) {
                throw ValidationException::withMessages([
                    'catalog_id' => "Barang \"{$existingCatalog->name}\" sudah tercatat di katalog. Pengajuan oleh Operator hanya untuk barang baru; penambahan stok katalog yang sudah ada dicatat oleh Admin melalui penerimaan dan distribusi.",
                ]);
            }

            // Satu baris katalog masih menjadi induk satu pengadaan. Menambah
            // stok ke katalog yang sudah tercatat akan menghilangkan jumlah
            // pengadaan baru (status katalog sudah inventaris tercatat), jadi
            // permintaan tersebut ditolak alih-alih disimpan sebagian.
            if (! $usesAllocations) {
                throw ValidationException::withMessages([
                    'catalog_id' => 'Katalog ini sudah tercatat sebagai inventaris. Pengadaan stok baru memerlukan katalog pengadaan tersendiri; gunakan mode alokasi ruangan untuk menambah stok pada katalog yang sudah ada.',
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
        } elseif ($requesterIsApprover) {
            $existingCatalog = Item::query()
                ->where('category_id', $data['category_id'])
                ->where('name', $data['name'])
                ->first();
        } else {
            // Nama yang sudah terdaftar tidak boleh diam-diam menempel ke
            // katalog lama. Tanpa pengecekan ini pengajuan Operator berakhir
            // sebagai alokasi tanpa unit dan tidak pernah masuk antrean validasi.
            $duplicate = Item::query()
                ->where('category_id', $data['category_id'])
                ->where('name', $data['name'])
                ->first();

            if ($duplicate !== null) {
                throw ValidationException::withMessages([
                    'name' => "Barang \"{$duplicate->name}\" sudah ada di katalog. Pengajuan oleh Operator hanya untuk barang baru; gunakan nama barang yang berbeda atau minta Admin mencatat penambahan stok melalui penerimaan dan distribusi.",
                ]);
            }
        }

        $category = $existingCatalog?->category ?? Category::findOrFail($data['category_id']);

        $createdAllocations = collect();
        $createdItem = null;

        DB::transaction(function () use ($data, $category, $firstRoom, $total, $groups, $request, $existingCatalog, $usesAllocations, &$createdAllocations, &$createdItem) {
            $isWakasek = $request->user()->isApproverLevel2();
            $isNewCatalog = $existingCatalog === null;

            // Pengadaan oleh pihak selain Wakasek selalu dimulai sebagai draft.
            // Pengadaan mode stok yang dibuat Wakasek langsung disetujui, tetapi
            // unit baru terbit setelah penerimaan dan distribusi yang sah.
            $recordsImmediately = $isWakasek && $usesAllocations;

            $initialStatus = $isWakasek
                ? ($usesAllocations
                    ? AcquisitionStatus::INVENTARIS_TERCATAT->value
                    : AcquisitionStatus::DISETUJUI->value)
                : AcquisitionStatus::DRAFT->value;

            $item = $existingCatalog ?? Item::create([
                'internal_id' => $firstRoom !== null
                    ? IdGenerator::internalId($firstRoom)
                    : IdGenerator::stockInternalId(),
                'location_code' => InstitutionSetting::current()->location_code,
                'register_no' => IdGenerator::register(null)['register_no'],
                'name' => $data['name'],
                'category_id' => $category->id,
                'room_id' => $firstRoom?->id,
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
                'acquisition_received_by' => $recordsImmediately ? $request->user()->id : null,
                'acquisition_received_at' => $recordsImmediately ? now() : null,
                'acquisition_received_quantity' => $recordsImmediately ? $total : null,
                'acquisition_received_condition' => $recordsImmediately ? Condition::BAIK->value : null,
            ]);

            // Setiap kelompok spesifikasi memiliki merek/tipe, tahun, sumber
            // dana, dan jumlahnya sendiri. Katalog tetap satu jenis barang.
            $groupSummary = [];

            foreach ($groups as $group) {
                $specification = Specification::resolveFor(
                    $item,
                    $group['brand'] ?? null,
                    $group['model_type'] ?? null,
                    $data['specification'] ?? null,
                );

                $groupSummary[] = [
                    'spesifikasi' => $specification->spec_code,
                    'merek' => $specification->brand,
                    'tipe_model' => $specification->model_type,
                    'tahun' => $group['acquisition_year'],
                    'sumber_dana' => $group['funding_source'],
                    'jumlah' => $group['quantity'],
                    'alokasi' => $group['allocations'],
                ];

                foreach ($group['allocations'] as $allocation) {
                    $createdAllocations->push(AcquisitionAllocation::create([
                        'item_catalog_id' => $item->id,
                        'specification_id' => $specification->id,
                        'room_id' => $allocation['room_id'],
                        'quantity' => $allocation['quantity'],
                        'condition' => $allocation['condition'] ?? Condition::BAIK->value,
                        'status' => $allocation['status'] ?? UnitStatus::AKTIF->value,
                        'note' => $allocation['note'] ?? null,
                        'acquisition_year' => $group['acquisition_year'] ?? $data['acquisition_year'],
                        'funding_source' => $group['funding_source'] ?? $data['funding_source'],
                    ]));
                }
            }

            activity('pengadaan')
                ->performedOn($item)
                ->causedBy($request->user())
                ->withProperties([
                    'kelompok_spesifikasi' => $groupSummary,
                ])
                ->log($isNewCatalog
                    ? ($isWakasek
                        ? 'Menambah barang via pengadaan multi-lokasi (disetujui langsung oleh Wakasek)'
                        : 'Menyimpan draft pengajuan pengadaan barang baru (belum dikirim)')
                    : 'Menambah stok spesifikasi pada katalog yang sudah ada');

            if ($recordsImmediately && $item->isRecordedInInventory()) {
                $this->provisionAllocations($item->fresh(), $createdAllocations, $request->user());
            }

            $createdItem = $item;
        });

        // Pengadaan baru dari Operator langsung diajukan ke Editor/Validator.
        // Tanpa langkah ini pengajuan tetap berstatus draft dan tidak pernah
        // muncul di antrean validasi.
        $submitted = false;

        if ($existingCatalog === null && ! $request->user()->isApproverLevel2() && $createdItem !== null) {
            $acquisitions->submit($createdItem, $request->user());
            $submitted = true;
        }

        if ($request->user()->isApproverLevel2()) {
            if (! $usesAllocations) {
                return redirect()->route('items.acquisitions')
                    ->with('success', 'Pengadaan disimpan sebagai stok. Catat penerimaan setelah barang datang, lalu distribusikan untuk menentukan spesifikasi dan lokasi unit.');
            }

            return redirect()->route('catalogs.index')
                ->with('success', 'Barang berhasil ditambahkan. Kode inventaris unit diterbitkan otomatis sesuai alokasi lokasi.');
        }

        if ($submitted) {
            return redirect()->route('items.acquisitions')
                ->with('success', 'Pengajuan pengadaan berhasil diajukan dan menunggu validasi Editor/Validator. Status terbaru dapat dilihat pada daftar pengadaan.');
        }

        return redirect()->route('items.acquisitions')
            ->with('success', 'Stok pengadaan berhasil ditambahkan pada katalog yang sudah ada.');
    }

    /**
     * Ubah input alokasi (banyak baris) menjadi koleksi unik per ruangan.
     * Form lama tanpa alokasi diperlakukan sebagai satu alokasi.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{room_id: int, quantity: int, condition: string, status: string, note: ?string}>
     */
    private function normalizeAllocations(Request $request, array $data, int $total): Collection
    {
        $rows = collect($request->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && filled($row['room_id'] ?? null) && filled($row['quantity'] ?? null))
            ->map(fn (array $row) => [
                'room_id' => (int) $row['room_id'],
                'quantity' => (int) $row['quantity'],
                'condition' => (string) ($row['condition'] ?? Condition::BAIK->value),
                'status' => (string) ($row['status'] ?? UnitStatus::AKTIF->value),
                'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
            ])
            ->values();

        if ($rows->isEmpty() && filled($data['room_id'] ?? null)) {
            $rows = collect([[
                'room_id' => (int) $data['room_id'],
                'quantity' => $total,
                'condition' => Condition::BAIK->value,
                'status' => UnitStatus::AKTIF->value,
                'note' => null,
            ]]);
        }

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tambahkan minimal satu lokasi alokasi untuk unit yang dibeli.',
            ]);
        }

        return $rows;
    }

    /**
     * Bentuk kelompok spesifikasi dari request. Form baru mengirim
     * `spec_groups`; form lama tetap didukung sebagai satu kelompok.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{brand: ?string, model_type: ?string, acquisition_year: int, funding_source: ?string, quantity: int, allocations: list<array{room_id: int, quantity: int, condition: string, status: string, note: ?string}>}>
     */
    private function normalizeSpecGroups(Request $request, array $data, int $total): Collection
    {
        $rows = collect($request->input('spec_groups', []))
            ->filter(fn ($group) => is_array($group) && filled($group['quantity'] ?? null))
            ->values();

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (array $group): array => [
                'brand' => $group['brand'] ?? null,
                'model_type' => $group['model_type'] ?? null,
                'acquisition_year' => filled($group['acquisition_year'] ?? null)
                    ? (int) $group['acquisition_year']
                    : (int) $data['acquisition_year'],
                'funding_source' => $group['funding_source'] ?? $data['funding_source'],
                'quantity' => (int) $group['quantity'],
                'allocations' => collect($group['allocations'] ?? [])
                    ->filter(fn ($row) => is_array($row) && filled($row['room_id'] ?? null) && filled($row['quantity'] ?? null))
                    ->map(fn (array $row): array => [
                        'room_id' => (int) $row['room_id'],
                        'quantity' => (int) $row['quantity'],
                        'condition' => (string) ($row['condition'] ?? Condition::BAIK->value),
                        'status' => (string) ($row['status'] ?? UnitStatus::AKTIF->value),
                        'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
                    ])
                    ->values()
                    ->all(),
            ])->values();
        }

        return collect([[
            'brand' => $data['brand'] ?? null,
            'model_type' => $data['model_type'] ?? null,
            'acquisition_year' => (int) $data['acquisition_year'],
            'funding_source' => $data['funding_source'],
            'quantity' => $total,
            'allocations' => $this->normalizeAllocations($request, $data, $total)->all(),
        ]]);
    }

    /**
     * Validasi backend (bukan hanya frontend): jumlah alokasi setiap kelompok
     * harus tepat sama dengan jumlah kelompok tersebut, dan total seluruh
     * kelompok harus sama dengan jumlah pengadaan.
     *
     * @param  Collection<int, array{quantity: int, allocations: list<array{room_id: int, quantity: int, condition: string, note: ?string}>}>  $groups
     */
    private function assertAllocationsValid(Collection $groups, int $total, bool $hasSpecGroups = false): void
    {
        $sum = 0;

        foreach ($groups as $index => $group) {
            $groupSum = (int) collect($group['allocations'])->sum('quantity');
            $sum += $groupSum;

            // Ruangan yang sama boleh muncul beberapa kali dalam satu kelompok
            // selama kondisi atau keterangannya berbeda. Kombinasi ruangan +
            // kondisi + keterangan tidak boleh duplikat, dan ruangan tetap boleh
            // dipakai kelompok spesifikasi lain.
            $duplicateRooms = collect($group['allocations'])
                ->groupBy(fn (array $allocation): string => implode('|', [
                    $allocation['room_id'],
                    $allocation['condition'],
                    mb_strtolower(trim((string) ($allocation['note'] ?? ''))),
                ]))
                ->contains(fn (Collection $rows): bool => $rows->count() > 1);

            if ($duplicateRooms) {
                $key = $hasSpecGroups ? "spec_groups.{$index}.allocations" : 'allocations';

                throw ValidationException::withMessages([
                    $key => 'Ruangan yang sama boleh muncul lebih dari sekali dalam satu kelompok, tetapi kondisi atau keterangannya harus berbeda.',
                ]);
            }

            if ($groupSum !== (int) $group['quantity']) {
                $key = $hasSpecGroups ? "spec_groups.{$index}.allocations" : 'allocations';
                $label = $hasSpecGroups ? 'kelompok '.($index + 1) : 'pengadaan';

                throw ValidationException::withMessages([
                    $key => "Total alokasi {$label} ({$groupSum} unit) harus sama dengan jumlah yang ditetapkan ("
                        .(int) $group['quantity'].' unit).',
                ]);
            }
        }

        if ($sum !== $total) {
            throw ValidationException::withMessages([
                $hasSpecGroups ? 'quantity' : 'allocations' => "Total jumlah pengadaan ({$total} unit) harus sama dengan jumlah seluruh alokasi ({$sum} unit). Sisa yang belum dialokasikan: "
                    .($total - $sum).' unit.',
            ]);
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
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AcquisitionStatus::class)],
        ]);
        $search = trim($validated['search'] ?? '');
        $statusFilter = $validated['status'] ?? null;

        $requests = Item::query()
            ->with(['room', 'category', 'requester', 'confirmer', 'approver', 'receiver', 'allocations'])
            ->whereIn('acquisition_status', AcquisitionStatus::values())
            ->when($statusFilter !== null, fn ($query) => $query->where('acquisition_status', $statusFilter))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $like = "%{$search}%";

                $query->where('name', 'like', $like)
                    ->orWhere('internal_id', 'like', $like)
                    ->orWhere('funding_source', 'like', $like)
                    ->orWhere('acquisition_note', 'like', $like)
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like))
                    ->orWhereHas('room', fn ($room) => $room->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('allocations.room', fn ($room) => $room->where('code', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('requester', fn ($requester) => $requester->where('name', 'like', $like));
            }))
            ->when($user->isOperator(), fn ($query) => $query->where(function ($scope) use ($user) {
                $scope->where('acquisition_requested_by', $user->id)
                    ->orWhereHas('allocations.room', fn ($room) => $room->whereIn('id', $user->roomScopes()->select('room_id')));
            }))
            ->when(! $user->isAdmin() && ! $user->isOperator(), fn ($query) => $query->where(function ($scope) {
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
            'search' => $search,
            'statusFilter' => $statusFilter,
            'pendingValidationCount' => $this->pendingValidationCount($user),
        ]);
    }

    /** Jumlah pengajuan yang menunggu validasi dalam cakupan pengguna. */
    private function pendingValidationCount(User $user): int
    {
        return Item::query()
            ->where('acquisition_status', AcquisitionStatus::MENUNGGU_KONFIRMASI->value)
            ->visibleTo($user)
            ->count();
    }

    /** Tahap 1 — kirim draft kepada Editor/Validator. */
    public function submitAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);
        $acquisitions->submit($item, $request->user());

        return back()->with('success', "Pengajuan \"{$item->name}\" dikirim ke Editor/Validator.");
    }

    /** Tahap 2 — validasi Editor/Validator (bukan persetujuan akhir). */
    public function confirmAcquisition(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        abort_unless($request->user()->isEditorValidator(), 403);

        $acquisitions->confirm($item, $request->user());

        return back()->with('success', "Pengajuan \"{$item->name}\" dikonfirmasi dan diteruskan ke Admin.");
    }

    /** Tahap 2 — Editor/Validator menolak pengajuan sebelum diteruskan ke Admin. */
    public function rejectAcquisitionByValidator(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        abort_unless($request->user()->isEditorValidator(), 403);

        $note = (string) $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $acquisitions->rejectByValidator($item, $request->user(), $note);

        return back()->with('success', "Pengajuan \"{$item->name}\" ditolak dan dikembalikan ke pengaju.");
    }

    /** Tahap 3 — persetujuan akhir Admin. */
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
            'received' => ['nullable', 'array'],
            'received.*.quantity' => ['nullable', 'integer', 'min:0'],
            'received.*.condition' => ['nullable', Rule::in(array_column(Condition::cases(), 'value'))],
            'received.*.note' => ['nullable', 'string', 'max:500'],
            'received_total' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', Rule::in(array_column(Condition::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Kunci penerimaan: id alokasi pada form baru; id ruangan tetap
        // diterima untuk kompatibilitas request lama.
        $receipts = [];

        // `validated()` tidak mengembalikan parent array ketika aturan wildcard
        // anak dipakai, sehingga input yang sudah tervalidasi dibaca langsung.
        foreach ($request->input('received', []) as $key => $row) {
            $receipts[(int) $key] = is_array($row) ? $row : ['quantity' => (int) $row];
        }

        $acquisitions->receive(
            $item,
            $request->user(),
            $receipts,
            $data['condition'] ?? null,
            $data['note'] ?? null,
            filled($data['received_total'] ?? null) ? (int) $data['received_total'] : null,
        );

        $message = $item->allocations()->exists()
            ? 'Lanjutkan dengan pencatatan inventaris.'
            : 'Barang tersimpan sebagai stok diterima. Lanjutkan dengan distribusi untuk menentukan merek, model, ruangan, kondisi, dan status unit.';

        return redirect()->route('items.acquisitions')
            ->with('success', "Penerimaan \"{$item->name}\" dicatat. {$message}");
    }

    /** Menerbitkan unit inventaris dari barang yang sudah diterima. */
    public function recordInventory(Request $request, Item $item, AcquisitionService $acquisitions): RedirectResponse
    {
        $this->ensureVisible($item);

        if (! $item->allocations()->exists() && $item->receipts()->exists()) {
            return redirect()->route('distributions.index')
                ->with('success', "Barang \"{$item->name}\" sudah tercatat sebagai stok diterima. Tentukan spesifikasi dan lokasi unit melalui formulir distribusi.");
        }

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
            ->when(! $user?->isAdmin(), fn ($query) => $query->where('is_lab', true))
            ->when($user?->isOperator(), fn ($query) => $query->whereIn('id', $user->roomScopes()->select('room_id')))
            ->orderBy('code')
            ->get();
    }

    private function roomRule(User $user): Exists
    {
        $rule = Rule::exists('rooms', 'id');

        if ($user->isAdmin()) {
            return $rule;
        }

        $rule = $rule->where('is_lab', true);

        if ($user->isOperator()) {
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
        // Spesifikasi cadangan hanya dibuat saat benar-benar diperlukan agar
        // katalog dengan kelompok spesifikasi tidak memiliki spesifikasi hantu.
        $defaultSpecificationId = null;

        foreach ($allocations as $allocation) {
            $room = $allocation->room ?: Room::find($allocation->room_id);

            if ($room === null) {
                continue;
            }

            for ($i = 0; $i < (int) $allocation->quantity; $i++) {
                $service->create($item, $room, [
                    'specification_id' => $allocation->specification_id
                        ?: ($defaultSpecificationId ??= Specification::resolveFor($item, $item->brand, $item->model_type)->id),
                    'condition' => $allocation->conditionEnum(),
                    'status' => $allocation->statusEnum(),
                    'acquisition_year' => $allocation->acquisition_year,
                    'funding_source' => $allocation->funding_source,
                    'notes' => $allocation->note,
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
