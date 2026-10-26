<?php

namespace App\Livewire\Catalog;

use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use Livewire\Component;
use Livewire\WithPagination;

class ItemCatalog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $category = '';

    public string $room = '';

    public int $perPage = 12;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'category' => ['except' => ''],
        'room' => ['except' => ''],
    ];

    public function updating($property, $value): void
    {
        if (in_array($property, ['search', 'status', 'category', 'room'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'room']);
        $this->resetPage();
    }

    public function render()
    {
        $activeLoanScope = fn ($query) => $query->whereHas('loan', fn ($l) => $l->whereIn('status', ['APPROVED', 'DIPINJAM']));

        $q = Item::query()
            ->approved()
            ->notArchived()
            ->with(['room.major', 'category'])
            // Agregat sekali query: mencegah N+1 saat menghitung stok tersedia per kartu.
            ->withSum(['loanItems as active_borrowed' => $activeLoanScope], 'qty_borrowed')
            ->withSum(['loanItems as active_returned' => $activeLoanScope], 'qty_returned')
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('internal_id', 'like', $term)
                    ->orWhere('register_no', 'like', $term)
                    ->orWhereHas('units', fn ($unit) => $unit->where('inventory_code', 'like', $term))
                    ->orWhere('specification', 'like', $term));
            })
            ->when($this->category, fn ($query) => $query->where('category_id', $this->category))
            ->when($this->room, fn ($query) => $query->where('room_id', $this->room))
            ->when($this->status, function ($query) {
                if ($this->status === ItemStatus::DIPINJAM->value) {
                    $query->whereHas('loanItems', fn ($li) => $li->whereIn('loan_id', function ($qq) {
                        $qq->select('id')->from('loans')
                            ->whereIn('status', ['APPROVED', 'DIPINJAM'])
                            ->where('start_date', '<=', now())
                            ->where('due_date', '>=', now());
                    }));
                } else {
                    $query->where('status', $this->status);
                }
            });

        return view('livewire.catalog.item-catalog', [
            'items' => $q->orderBy('name')->paginate($this->perPage),
            'categories' => Category::orderBy('name')->get(),
            'rooms' => Room::orderBy('code')->get(),
            'statuses' => ItemStatus::cases(),
            'activeFilters' => collect(['search', 'status', 'category', 'room'])
                ->filter(fn ($f) => $this->$f !== '')->count(),
        ]);
    }
}
