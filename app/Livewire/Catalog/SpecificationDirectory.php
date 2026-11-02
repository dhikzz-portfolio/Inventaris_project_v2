<?php

namespace App\Livewire\Catalog;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\Building;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class SpecificationDirectory extends Component
{
    use WithPagination;

    public int $catalogId;

    public string $search = '';

    public string $brand = '';

    public string $model = '';

    public string $condition = '';

    public string $status = '';

    public string $building = '';

    public string $room = '';

    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'brand' => ['except' => ''],
        'model' => ['except' => ''],
        'condition' => ['except' => ''],
        'status' => ['except' => ''],
        'building' => ['except' => ''],
        'room' => ['except' => ''],
    ];

    public function mount(int $catalogId): void
    {
        abort_unless(Item::query()->approved()->visibleTo(auth()->user())->whereKey($catalogId)->exists(), 404);

        $this->catalogId = $catalogId;
    }

    public function updating($property, $value): void
    {
        if (in_array($property, ['search', 'brand', 'model', 'condition', 'status', 'building', 'room'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'brand', 'model', 'condition', 'status', 'building', 'room']);
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

        $scopedUnits = fn (Builder $unit) => $unit->visibleTo($user)->where('status', '!=', UnitStatus::DIHAPUS->value);

        $query = Specification::query()
            ->where('item_catalog_id', $catalog->id)
            // Hanya spesifikasi yang punya unit dalam cakupan pengguna.
            ->whereHas('units', $scopedUnits)
            ->withCount(['units as total_units' => $scopedUnits])
            ->withCount(['units as available_units' => fn (Builder $unit) => $unit
                ->visibleTo($user)
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value)
                ->whereDoesntHave('openLoanItemUnit'),
            ])
            ->withCount(['units as damaged_units' => fn (Builder $unit) => $unit
                ->visibleTo($user)
                ->where('status', UnitStatus::AKTIF->value)
                ->whereIn('condition', [Condition::RUSAK_RINGAN->value, Condition::RUSAK_BERAT->value]),
            ]);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn (Builder $where) => $where
                ->where('brand', 'like', $like)
                ->orWhere('model_type', 'like', $like)
                ->orWhere('spec_code', 'like', $like)
                ->orWhere('description', 'like', $like));
        }

        $query->when($this->brand !== '', fn (Builder $q) => $q->where('brand', $this->brand));
        $query->when($this->model !== '', fn (Builder $q) => $q->where('model_type', 'like', '%'.$this->model.'%'));

        // Filter fisik mempersempit spesifikasi yang ditampilkan; angka jumlah
        // tetap agregasi seluruh unit dalam cakupan pengguna.
        if ($this->condition !== '' || $this->status !== '' || $this->building !== '' || $this->room !== '') {
            $query->whereHas('units', function (Builder $unit) use ($user) {
                $unit->visibleTo($user)->where('status', '!=', UnitStatus::DIHAPUS->value);

                if ($this->condition !== '') {
                    $unit->where('condition', $this->condition);
                }
                if ($this->status !== '') {
                    $unit->where('status', $this->status);
                }
                if ($this->room !== '') {
                    $unit->where('room_id', $this->room);
                } elseif ($this->building !== '') {
                    $unit->whereHas('room', fn (Builder $room) => $room->where('building_letter', $this->building));
                }
            });
        }

        return view('livewire.catalog.specification-directory', [
            'catalog' => $catalog,
            'specifications' => $query->orderBy('brand')->orderBy('model_type')->paginate($this->perPage),
            'brands' => Specification::query()
                ->where('item_catalog_id', $catalog->id)
                ->whereHas('units', $scopedUnits)
                ->select('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'buildings' => Building::filterOptions(),
            'rooms' => Room::query()
                ->when($this->building !== '', fn (Builder $q) => $q->where('building_letter', $this->building))
                ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
                ->get(),
            'conditions' => Condition::cases(),
            'statuses' => UnitStatus::cases(),
            'activeFilters' => collect(['search', 'brand', 'model', 'condition', 'status', 'building', 'room'])
                ->filter(fn ($field) => $this->$field !== '')->count(),
        ]);
    }
}
