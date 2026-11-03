<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitMovement;
use App\Models\Building;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Services\InventoryCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MovementController extends Controller
{
    /**
     * Mutasi dilakukan per unit fisik. Daftar unit mendukung pencarian kode
     * dan filter jenis, gedung, lantai, ruangan, kondisi, serta status.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $units = AssetUnit::query()
            ->visibleTo($user)
            ->whereHas('catalog', fn ($query) => $query->approved())
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->with(['catalog.category', 'room.building'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = strtoupper(trim($filters['search']));
                $query->where('inventory_code', 'like', $term.'%');
            })
            ->when($filters['catalog'] !== '', fn ($query) => $query->where('item_catalog_id', (int) $filters['catalog']))
            ->when($filters['building'] !== '', fn ($query) => $query->whereHas(
                'room.building',
                fn ($room) => $room->where('code', strtoupper($filters['building']))
            ))
            ->when($filters['floor'] !== '', fn ($query) => $query->whereHas(
                'room',
                fn ($room) => $room->where('floor', (int) $filters['floor'])
            ))
            ->when($filters['room'] !== '', fn ($query) => $query->where('room_id', (int) $filters['room']))
            ->when($filters['condition'] !== '', fn ($query) => $query->where('condition', $filters['condition']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('inventory_code')
            ->paginate(25)
            ->withQueryString();

        $rooms = Room::query()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
            ->when($user->isOperator(), fn ($query) => $query->whereIn('id', $user->roomScopes()->select('room_id')))
            ->orderBy('code')
            ->get();

        return view('movements.index', [
            'units' => $units,
            'movements' => AssetUnitMovement::with(['unit.catalog', 'fromRoom', 'toRoom', 'mover'])
                ->latest('moved_at')
                ->latest('id')
                ->paginate(10),
            'catalogs' => Item::query()
                ->approved()
                ->visibleTo($user)
                ->orderBy('name')
                ->get(['id', 'name']),
            'buildings' => Building::query()->orderBy('code')->get(),
            'rooms' => $rooms,
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
            'filters' => $filters,
            'selectedRoom' => $filters['room'],
            'unitOptions' => $units->getCollection()->mapWithKeys(fn (AssetUnit $unit): array => [
                (string) $unit->id => [
                    'code' => $unit->inventory_code,
                    'name' => $unit->catalog?->name,
                    'room' => $unit->locationLabel(),
                ],
            ])->all(),
            'roomLabels' => $rooms->mapWithKeys(fn (Room $room): array => [
                (string) $room->id => $room->code.' — '.$room->name,
            ])->all(),
        ]);
    }

    /**
     * Simpan satu transaksi mutasi yang boleh memiliki beberapa lokasi tujuan.
     *
     * Setiap kelompok tujuan berisi unit sendiri; unit yang sama tidak boleh
     * muncul pada dua kelompok. Semua perubahan dilakukan dalam satu transaksi
     * database sehingga kegagalan satu unit membatalkan seluruh mutasi.
     */
    public function store(Request $request, InventoryCodeService $codes): RedirectResponse
    {
        $data = $request->validate([
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['required', 'integer', 'distinct', Rule::exists('asset_units', 'id')],
            'to_room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'destinations' => ['nullable', 'array', 'min:1'],
            'destinations.*.room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'destinations.*.unit_ids' => ['required', 'array', 'min:1'],
            'destinations.*.unit_ids.*' => ['required', 'integer', Rule::exists('asset_units', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'destinations.*.room_id.required' => 'Setiap kelompok tujuan wajib memiliki ruangan tujuan.',
            'destinations.*.unit_ids.required' => 'Setiap kelompok tujuan wajib berisi minimal satu unit.',
            'destinations.*.unit_ids.min' => 'Setiap kelompok tujuan wajib berisi minimal satu unit.',
        ]);

        $groups = $this->destinationGroups($data);
        $allUnitIds = $groups->flatMap(fn (array $group): array => $group['unit_ids'])->values();

        if ($allUnitIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'destinations' => 'Satu unit hanya boleh dimasukkan ke satu lokasi tujuan dalam transaksi ini.',
            ]);
        }

        if ($groups->pluck('room_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'destinations' => 'Satu ruangan tujuan hanya boleh muncul sekali. Gabungkan unitnya dalam kelompok yang sama.',
            ]);
        }

        $user = $request->user();

        $rooms = Room::query()
            ->whereIn('id', $groups->pluck('room_id')->all())
            ->get()
            ->keyBy('id');

        if (! $user->isSuperAdmin() && $rooms->contains(fn (Room $room): bool => ! $room->is_lab)) {
            abort(404);
        }

        $units = AssetUnit::query()
            ->visibleTo($user)
            ->whereIn('id', $allUnitIds->all())
            ->get();

        if ($units->count() !== $allUnitIds->count()) {
            abort(404);
        }

        $movementNo = 'MUT-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
        // Payload lama (satu tujuan) tetap memakai kunci error `unit_ids` agar
        // pesan validasi pada formulir lama tidak berubah.
        $errorKey = filled($request->input('destinations')) ? 'destinations' : 'unit_ids';
        $moved = 0;
        $summary = [];

        DB::transaction(function () use ($groups, $rooms, $allUnitIds, $user, $data, $codes, $movementNo, $errorKey, &$moved, &$summary): void {
            // Kunci seluruh unit lebih dulu agar dua transaksi mutasi tidak
            // memindahkan unit yang sama secara bersamaan.
            $locked = AssetUnit::query()
                ->whereIn('id', $allUnitIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($groups as $group) {
                $destination = $rooms->get($group['room_id']);

                if ($destination === null) {
                    throw ValidationException::withMessages([
                        'destinations' => 'Ruangan tujuan tidak ditemukan.',
                    ]);
                }

                foreach ($group['unit_ids'] as $unitId) {
                    $unit = $locked->get($unitId);

                    if ($unit === null) {
                        throw ValidationException::withMessages([
                            $errorKey => 'Sebagian unit tidak ditemukan lagi. Muat ulang halaman.',
                        ]);
                    }

                    if ($unit->status === UnitStatus::DIHAPUS || $unit->archived_at !== null) {
                        throw ValidationException::withMessages([
                            $errorKey => "Unit {$unit->inventory_code} sudah dihapus dan tidak dapat dipindahkan.",
                        ]);
                    }

                    if ($unit->status === UnitStatus::DIPINJAM || $unit->isCurrentlyOnLoan()) {
                        throw ValidationException::withMessages([
                            $errorKey => "Unit {$unit->inventory_code} sedang dipinjam. Kembalikan dulu sebelum dipindahkan.",
                        ]);
                    }

                    if ((int) $unit->room_id === (int) $destination->id) {
                        throw ValidationException::withMessages([
                            $errorKey => "Unit {$unit->inventory_code} sudah berada di ruangan tujuan {$destination->code}.",
                        ]);
                    }

                    try {
                        $codes->move($unit, $destination, $user, $data['note'] ?? null, $movementNo);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            $errorKey => $unit->inventory_code.': '.$exception->validator->errors()->first(),
                        ]);
                    }

                    $summary[$destination->code][] = $unit->inventory_code;
                    $moved++;
                }
            }

            activity('mutasi')
                ->causedBy($user)
                ->withProperties([
                    'movement_no' => $movementNo,
                    'tujuan' => $summary,
                    'jumlah_unit' => $moved,
                ])
                ->log('Mutasi '.$moved.' unit ke '.count($summary).' lokasi tujuan');
        });

        return redirect()->route('movements.index')
            ->with('success', "{$moved} unit dipindahkan ke ".count($summary)." lokasi tujuan (No. {$movementNo}). Label unit terkait wajib dicetak ulang.");
    }

    /**
     * Gabungkan input multi-tujuan menjadi kelompok yang tervalidasi. Payload
     * lama (satu `to_room_id` + `unit_ids[]`) tetap diterima agar tautan dan
     * klien lama tidak rusak.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{room_id: int, unit_ids: list<int>}>
     */
    private function destinationGroups(array $data): Collection
    {
        $groups = collect($data['destinations'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['room_id'] ?? null) && ! empty($row['unit_ids']))
            ->map(fn (array $row): array => [
                'room_id' => (int) $row['room_id'],
                'unit_ids' => array_values(array_unique(array_map('intval', (array) $row['unit_ids']))),
            ])
            ->values();

        if ($groups->isEmpty()) {
            if (blank($data['to_room_id'] ?? null) || empty($data['unit_ids'])) {
                throw ValidationException::withMessages([
                    'destinations' => 'Tetapkan tujuan untuk unit yang dipilih sebelum menyimpan mutasi.',
                ]);
            }

            $groups = collect([[
                'room_id' => (int) $data['to_room_id'],
                'unit_ids' => array_values(array_unique(array_map('intval', (array) $data['unit_ids']))),
            ]]);
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $read = fn (string $key) => trim((string) $request->query($key, ''));

        return [
            'search' => $read('search'),
            'catalog' => $read('catalog'),
            'building' => $read('building'),
            'floor' => $read('floor'),
            'room' => $read('room'),
            'condition' => $read('condition'),
            'status' => $read('status'),
        ];
    }
}
