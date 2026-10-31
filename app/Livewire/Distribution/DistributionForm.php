<?php

namespace App\Livewire\Distribution;

use App\Enums\UnitStatus;
use App\Models\Distribution;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Services\DistributionService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DistributionForm extends Component
{
    public string $catalogId = '';

    public string $specificationId = '';

    public int $quantity = 1;

    public string $toRoomId = '';

    public string $note = '';

    protected function rules(): array
    {
        return [
            'catalogId' => ['required', 'integer', 'exists:item_catalogs,id'],
            'specificationId' => ['required', 'integer', 'exists:specifications,id'],
            'toRoomId' => ['required', 'integer', 'exists:rooms,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected $messages = [
        'catalogId.required' => 'Pilih katalog barang terlebih dahulu.',
        'specificationId.required' => 'Pilih merek dan tipe/model yang akan didistribusikan.',
        'toRoomId.required' => 'Pilih ruangan tujuan distribusi.',
    ];

    public function updatedCatalogId(): void
    {
        $this->specificationId = '';
        $this->quantity = 1;
    }

    public function updatedSpecificationId(): void
    {
        $this->quantity = 1;
    }

    public function submit(DistributionService $service): void
    {
        $data = $this->validate();
        $user = auth()->user();

        $specification = Specification::query()->findOrFail($data['specificationId']);

        abort_unless(
            Item::query()->approved()->visibleTo($user)->whereKey($specification->item_catalog_id)->exists(),
            404
        );

        $room = Room::findOrFail($data['toRoomId']);

        if (! $user->isSuperAdmin() && ! $room->is_lab) {
            abort(404);
        }

        try {
            $distribution = $service->distribute(
                $specification,
                $room,
                (int) $data['quantity'],
                $user,
                filled($data['note']) ? $data['note'] : null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('quantity', $messages[0]);
            }

            return;
        }

        $this->reset(['specificationId', 'quantity', 'note']);
        $this->quantity = 1;

        session()->flash('success', sprintf(
            '%d unit %s didistribusikan ke %s. Kode inventaris baru: %s.',
            $distribution->quantity,
            $specification->displayName(),
            $room->name,
            $distribution->units->pluck('new_code')->take(3)->implode(', ').($distribution->quantity > 3 ? '…' : '')
        ));
    }

    public function render()
    {
        $user = auth()->user();

        $catalogs = Item::query()
            ->approved()
            ->notArchived()
            ->visibleTo($user)
            ->with('category')
            ->withCount(['units as spec_unit_total' => fn ($unit) => $unit
                ->visibleTo($user)
                ->where('status', '!=', UnitStatus::DIHAPUS->value),
            ])
            ->orderBy('name')
            ->get();

        $selectedCatalog = $this->catalogId !== ''
            ? $catalogs->firstWhere('id', (int) $this->catalogId)
            : null;

        $specifications = $selectedCatalog === null
            ? collect()
            : Specification::query()
                ->where('item_catalog_id', $selectedCatalog->id)
                ->orderBy('brand')->orderBy('model_type')
                ->get()
                ->map(fn (Specification $specification) => [
                    'id' => $specification->id,
                    'label' => $specification->displayName(),
                    'available' => $specification->distributableCount($user, $this->toRoomId !== '' ? (int) $this->toRoomId : null),
                ]);

        $selectedSpecification = $this->specificationId !== ''
            ? Specification::query()->find((int) $this->specificationId)
            : null;

        $rooms = Room::query()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('is_lab', true))
            ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
            ->get();

        $recent = Distribution::query()
            ->whereIn('item_catalog_id', $catalogs->pluck('id'))
            ->with(['specification', 'toRoom', 'actor', 'catalog'])
            ->latest('distributed_at')
            ->limit(10)
            ->get();

        return view('livewire.distribution.distribution-form', [
            'catalogs' => $catalogs,
            'specifications' => $specifications,
            'rooms' => $rooms,
            'selectedRoom' => $this->toRoomId !== '' ? $rooms->firstWhere('id', (int) $this->toRoomId) : null,
            'stockAvailable' => $selectedSpecification?->distributableCount(
                $user,
                $this->toRoomId !== '' ? (int) $this->toRoomId : null
            ) ?? 0,
            'recent' => $recent,
            'statuses' => UnitStatus::cases(),
        ]);
    }
}
