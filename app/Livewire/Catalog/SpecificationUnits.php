<?php

namespace App\Livewire\Catalog;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Building;
use App\Models\Room;
use App\Models\Specification;
use App\Services\InventoryCodeService;
use Livewire\Component;
use Livewire\WithPagination;

class SpecificationUnits extends Component
{
    use WithPagination;

    public int $specificationId;

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

    public function mount(int $specificationId): void
    {
        $user = auth()->user();

        abort_unless(
            Specification::query()->visibleTo($user)->whereKey($specificationId)->exists(),
            404
        );

        $this->specificationId = $specificationId;
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

        $specification = Specification::query()
            ->visibleTo($user)
            ->with('itemCatalog.category')
            ->findOrFail($this->specificationId);

        $catalog = $specification->itemCatalog;

        $query = AssetUnit::query()
            ->visibleTo($user)
            ->where('specification_id', $specification->id)
            ->with('room');

        $term = trim($this->search);
        if ($term !== '') {
            $code = app(InventoryCodeService::class)->normalize($term);
            $looksLikeCode = preg_match('/^[A-Z0-9-]{1,18}$/', $code) === 1;
            $isFullCode = app(InventoryCodeService::class)->isValidFormat($code);

            $query->where(function ($where) use ($term, $code, $looksLikeCode, $isFullCode) {
                $like = '%'.$term.'%';

                $where->where('inventory_code', 'like', $like)
                    ->orWhereHas('catalog', fn ($catalog) => $catalog
                        ->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model_type', 'like', $like));

                if ($isFullCode) {
                    $resolved = app(InventoryCodeService::class)->resolve($code);
                    $resolved !== null
                        ? $where->orWhereKey($resolved->id)
                        : $where->whereRaw('1 = 0');
                } elseif ($looksLikeCode && preg_match('/^[A-Z][0-9]/', $code) === 1) {
                    $where->orWhere('inventory_code', 'like', $code.'%');
                }
            });
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
            ->where('specification_id', $specification->id);

        return view('livewire.catalog.specification-units', [
            'catalog' => $catalog,
            'specification' => $specification,
            'units' => $query->orderBy('inventory_code')->paginate($this->perPage),
            'totalUnits' => (clone $allUnits)->where('status', '!=', UnitStatus::DIHAPUS->value)->count(),
            'availableUnits' => (clone $allUnits)
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value)
                ->whereDoesntHave('openLoanItemUnit')
                ->count(),
            'damagedUnits' => (clone $allUnits)
                ->where('status', UnitStatus::AKTIF->value)
                ->whereIn('condition', [Condition::RUSAK_RINGAN->value, Condition::RUSAK_BERAT->value])
                ->count(),
            'rooms' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->when($this->floor !== '', fn ($q) => $q->where('floor', $this->floor))
                ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
                ->get(),
            'buildings' => Building::filterOptions(),
            'floors' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->select('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'conditions' => Condition::cases(),
            'statuses' => UnitStatus::cases(),
            'activeFilters' => collect(['search', 'building', 'floor', 'room', 'condition', 'status', 'year'])
                ->filter(fn ($field) => $this->$field !== '')->count(),
        ]);
    }
}
