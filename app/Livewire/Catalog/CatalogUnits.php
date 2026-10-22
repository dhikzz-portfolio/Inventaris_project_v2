<?php

namespace App\Livewire\Catalog;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Room;
use App\Services\InventoryCodeService;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogUnits extends Component
{
    use WithPagination;

    public int $catalogId;

    public string $search = '';

    public string $building = '';

    public string $floor = '';

    public string $room = '';

    public string $condition = '';

    public string $status = '';

    public string $year = '';

    public string $codeError = '';

    public int $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'building' => ['except' => ''],
        'floor' => ['except' => ''],
        'room' => ['except' => ''],
        'condition' => ['except' => ''],
        'status' => ['except' => ''],
        'year' => ['except' => ''],
    ];

    public function mount(int $catalogId): void
    {
        abort_unless(Item::query()->approved()->visibleTo(auth()->user())->whereKey($catalogId)->exists(), 404);
        $this->catalogId = $catalogId;
    }

    public function updating($property, $value): void
    {
        if (in_array($property, ['search', 'building', 'floor', 'room', 'condition', 'status', 'year'], true)) {
            $this->codeError = '';
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'building', 'floor', 'room', 'condition', 'status', 'year', 'codeError']);
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $catalog = Item::query()
            ->approved()
            ->visibleTo($user)
            ->with('category')
            ->findOrFail($this->catalogId);

        $query = AssetUnit::query()
            ->visibleTo($user)
            ->where('item_catalog_id', $catalog->id)
            ->with('room');

        $term = trim($this->search);
        if ($term !== '') {
            $code = app(InventoryCodeService::class)->normalize($term);

            if (! $this->isCodeLike($code)) {
                $this->codeError = 'Format kode tidak valid. Gunakan kode inventaris, contoh G1-02-12.';
                $query->whereRaw('1 = 0');
            } elseif (strlen($code) === 8 && app(InventoryCodeService::class)->isValidFormat($code)) {
                $resolved = app(InventoryCodeService::class)->resolve($code);
                $resolved !== null
                    ? $query->whereKey($resolved->id)
                    : $query->whereRaw('1 = 0');
            } else {
                $query->where('inventory_code', 'like', $code.'%');
            }
        }

        if ($this->room !== '') {
            $query->where('room_id', $this->room);
        } else {
            if ($this->building !== '') {
                $query->whereHas('room', fn ($room) => $room->where('building_letter', $this->building));
            }
            if ($this->floor !== '') {
                $query->whereHas('room', fn ($room) => $room->where('floor', $this->floor));
            }
        }

        $query->when($this->condition, fn ($q) => $q->where('condition', $this->condition));
        $query->when($this->status, fn ($q) => $q->where('status', $this->status));
        $query->when($this->year, fn ($q) => $q->where('acquisition_year', $this->year));

        $allUnits = AssetUnit::query()
            ->visibleTo($user)
            ->where('item_catalog_id', $catalog->id);

        return view('livewire.catalog.catalog-units', [
            'catalog' => $catalog,
            'units' => $query->orderBy('inventory_code')->paginate($this->perPage),
            'totalUnits' => (clone $allUnits)->where('status', '!=', UnitStatus::DIHAPUS->value)->count(),
            'availableUnits' => max(0,
                (clone $allUnits)->where('status', UnitStatus::AKTIF->value)->where('condition', Condition::BAIK->value)->count()
                - $catalog->activeLoanRemainingQuantity()
            ),
            'rooms' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->when($this->floor !== '', fn ($q) => $q->where('floor', $this->floor))
                ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
                ->get(),
            'buildings' => Room::query()->select('building_letter')->distinct()->orderBy('building_letter')->pluck('building_letter'),
            'floors' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->select('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'conditions' => Condition::cases(),
            'statuses' => UnitStatus::cases(),
            'activeFilters' => collect(['search', 'building', 'floor', 'room', 'condition', 'status', 'year'])
                ->filter(fn ($field) => $this->$field !== '')->count(),
        ]);
    }

    /**
     * Potongan kode yang sah: G, G1, G1-0, G1-02, G1-02-1, G1-02-12.
     */
    private function isCodeLike(string $code): bool
    {
        return preg_match('/^[A-Z](?:[1-9](?:-(?:[0-9]{1,2}(?:-[0-9]{1,2})?)?)?)?$/', $code) === 1
            || app(InventoryCodeService::class)->isValidFormat($code);
    }
}
