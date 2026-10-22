<?php

namespace App\Livewire\Catalog;

use App\Models\Building;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Services\InventoryCodeService;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $category = '';

    public string $building = '';

    public string $floor = '';

    public string $room = '';

    public string $availability = '';

    public int $perPage = 12;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => ''],
        'building' => ['except' => ''],
        'floor' => ['except' => ''],
        'room' => ['except' => ''],
        'availability' => ['except' => ''],
    ];

    public function updating($property, $value): void
    {
        if (in_array($property, ['search', 'category', 'building', 'floor', 'room', 'availability'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'building', 'floor', 'room', 'availability']);
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();
        $visibleUnits = fn ($query) => $query->visibleTo($user)->where('status', '!=', 'DIHAPUS');

        $query = Item::query()
            ->approved()
            ->visibleTo($user)
            ->with(['category', 'room'])
            ->withCount(['units as units_count' => $visibleUnits])
            ->withCount(['units as available_count' => fn ($unit) => $unit
                ->visibleTo($user)
                ->where('status', 'AKTIF')
                ->where('condition', 'BAIK'),
            ])
            ->withCount(['units as damaged_count' => fn ($unit) => $unit
                ->visibleTo($user)
                ->where('status', 'AKTIF')
                ->whereIn('condition', ['RUSAK_RINGAN', 'RUSAK_BERAT']),
            ])
            // Sisa pinjaman aktif pada level katalog (kompatibilitas modul peminjaman).
            ->withSum(['loanItems as active_borrowed' => fn ($line) => $line
                ->whereHas('loan', fn ($loan) => $loan->whereIn('status', ['APPROVED', 'DIPINJAM'])),
            ], 'qty_borrowed')
            ->withSum(['loanItems as active_returned' => fn ($line) => $line
                ->whereHas('loan', fn ($loan) => $loan->whereIn('status', ['APPROVED', 'DIPINJAM'])),
            ], 'qty_returned');

        $term = trim($this->search);
        if ($term !== '') {
            $normalized = app(InventoryCodeService::class)->normalize($term);
            $looksLikeCode = preg_match('/^[A-Z0-9-]{1,8}$/', $normalized) === 1;

            $query->where(function ($where) use ($term, $normalized, $looksLikeCode, $user) {
                $like = '%'.$term.'%';
                $where->where('name', 'like', $like)
                    ->orWhere('specification', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model_type', 'like', $like);

                if ($looksLikeCode) {
                    $where->orWhereHas('units', fn ($unit) => $unit
                        ->visibleTo($user)
                        ->where('inventory_code', 'like', $normalized.'%'));
                }
            });
        }

        $query->when($this->category, fn ($q) => $q->where('category_id', $this->category));

        if ($this->building !== '' || $this->floor !== '' || $this->room !== '') {
            $query->whereHas('units', function ($unit) use ($user) {
                $unit->visibleTo($user);

                if ($this->room !== '') {
                    $unit->where('room_id', $this->room);
                } else {
                    $unit->whereHas('room', function ($room) {
                        if ($this->building !== '') {
                            $room->where('building_letter', $this->building);
                        }
                        if ($this->floor !== '') {
                            $room->where('floor', $this->floor);
                        }
                    });
                }
            });
        }

        if ($this->availability === 'available') {
            $query->whereHas('units', fn ($unit) => $unit
                ->visibleTo($user)
                ->where('status', 'AKTIF')
                ->where('condition', 'BAIK'));
        } elseif ($this->availability === 'empty') {
            $query->whereDoesntHave('units', fn ($unit) => $unit
                ->visibleTo($user)
                ->where('status', 'AKTIF')
                ->where('condition', 'BAIK'));
        }

        return view('livewire.catalog.catalog-directory', [
            'catalogs' => $query->orderBy('name')->paginate($this->perPage),
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->when($this->floor !== '', fn ($q) => $q->where('floor', $this->floor))
                ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
                ->get(),
            'buildings' => Building::filterOptions(),
            'floors' => Room::query()
                ->when($this->building !== '', fn ($q) => $q->where('building_letter', $this->building))
                ->select('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'activeFilters' => collect(['search', 'category', 'building', 'floor', 'room', 'availability'])
                ->filter(fn ($field) => $this->$field !== '')->count(),
            'scopeMissing' => $user->isLaboran() && ! $user->roomScopes()->exists(),
        ]);
    }
}
