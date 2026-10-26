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

        return view('movements.index', [
            'units' => $units,
            'movements' => AssetUnitMovement::with(['unit.catalog', 'fromRoom', 'toRoom', 'mover'])
                ->latest('moved_at')
                ->paginate(10),
            'catalogs' => Item::query()
                ->approved()
                ->visibleTo($user)
                ->orderBy('name')
                ->get(['id', 'name']),
            'buildings' => Building::query()->orderBy('code')->get(),
            'rooms' => Room::query()
                ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
                ->orderBy('code')
                ->get(),
            'categories' => Category::orderBy('name')->get(),
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
            'filters' => $filters,
            'selectedRoom' => $filters['room'],
        ]);
    }

    /**
     * Simpan mutasi banyak unit sekaligus secara atomik. Bila satu unit gagal
     * divalidasi, seluruh transaksi dibatalkan sehingga tidak ada mutasi
     * setengah jadi.
     */
    public function store(Request $request, InventoryCodeService $codes): RedirectResponse
    {
        $data = $request->validate([
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['required', 'integer', 'distinct', Rule::exists('asset_units', 'id')],
            'to_room_id' => ['required', 'integer', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'unit_ids.required' => 'Pilih minimal satu unit yang akan dimutasi.',
            'unit_ids.*.distinct' => 'Unit yang sama tidak boleh dipilih dua kali.',
        ]);

        $user = $request->user();
        $destination = Room::findOrFail($data['to_room_id']);

        if (! $user->isSuperAdmin() && ! $destination->is_lab) {
            abort(404);
        }

        $units = AssetUnit::query()
            ->visibleTo($user)
            ->whereIn('id', $data['unit_ids'])
            ->get();

        if ($units->count() !== count($data['unit_ids'])) {
            abort(404);
        }

        $moved = DB::transaction(function () use ($units, $destination, $user, $data, $codes) {
            $results = [];

            foreach ($units as $unit) {
                if ((int) $unit->room_id === (int) $destination->id) {
                    throw ValidationException::withMessages([
                        'unit_ids' => "Unit {$unit->inventory_code} sudah berada di ruangan tujuan.",
                    ]);
                }

                try {
                    $results[] = $codes->move($unit, $destination, $user, $data['note'] ?? null);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'unit_ids' => $unit->inventory_code.': '.$exception->validator->errors()->first(),
                    ]);
                }
            }

            activity('mutasi')
                ->causedBy($user)
                ->withProperties([
                    'unit_ids' => $units->pluck('id')->all(),
                    'to_room_id' => $destination->id,
                    'codes' => collect($results)->map(fn (AssetUnit $unit) => [
                        'unit_id' => $unit->id,
                        'new_code' => $unit->inventory_code,
                    ])->all(),
                ])
                ->log('Mutasi '.count($results).' unit ke '.$destination->code);

            return $results;
        });

        return redirect()->route('movements.index')
            ->with('success', count($moved).' unit dipindahkan ke '.$destination->code.'. Label unit terkait wajib dicetak ulang.');
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
