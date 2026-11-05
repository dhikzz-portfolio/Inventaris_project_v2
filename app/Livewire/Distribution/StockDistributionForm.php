<?php

namespace App\Livewire\Distribution;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\Distribution;
use App\Models\Item;
use App\Models\Room;
use App\Services\DistributionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Distribusi stok penerimaan.
 *
 * Satu pengiriman formulir dapat berisi beberapa alokasi ruangan. Setiap baris
 * menentukan kelompok spesifikasi, jumlah, kondisi fisik, dan status
 * operasionalnya sendiri sehingga satu katalog dapat memiliki beberapa
 * kelompok spesifikasi yang berbeda per ruangan.
 */
class StockDistributionForm extends Component
{
    public string $catalogId = '';

    public string $note = '';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function mount(?int $catalog = null): void
    {
        $this->rows = [$this->blankRow()];

        if ($catalog !== null && $catalog > 0) {
            $this->catalogId = (string) $catalog;
        }
    }

    /** @return array<string, mixed> */
    private function blankRow(): array
    {
        return [
            'key' => (string) str()->random(8),
            'room_id' => '',
            'brand' => '',
            'model_type' => '',
            'acquisition_year' => '',
            'funding_source' => '',
            'quantity' => 1,
            'condition' => Condition::BAIK->value,
            'status' => UnitStatus::AKTIF->value,
            'reason' => '',
            'note' => '',
        ];
    }

    public function addRow(): void
    {
        $this->rows[] = $this->blankRow();
        $this->resetValidation();
    }

    public function removeRow(int $index): void
    {
        if (count($this->rows) <= 1) {
            return;
        }

        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        $this->resetValidation();
    }

    public function updatedCatalogId(): void
    {
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'catalogId' => ['required', 'integer', 'exists:item_catalogs,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
            'rows.*.quantity' => ['required', 'integer', 'min:1'],
            'rows.*.brand' => ['nullable', 'string', 'max:100'],
            'rows.*.model_type' => ['nullable', 'string', 'max:100'],
            'rows.*.acquisition_year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'rows.*.funding_source' => ['nullable', 'string', 'max:100'],
            'rows.*.condition' => ['nullable', Rule::enum(Condition::class)],
            'rows.*.status' => ['nullable', Rule::enum(UnitStatus::class)],
            'rows.*.reason' => ['nullable', 'string', 'max:255'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected $messages = [
        'catalogId.required' => 'Pilih katalog yang stoknya akan didistribusikan.',
        'rows.*.room_id.required' => 'Pilih ruangan tujuan pada setiap baris alokasi.',
        'rows.*.quantity.required' => 'Isi jumlah unit pada setiap baris alokasi.',
    ];

    public function submit(DistributionService $service): void
    {
        $data = $this->validate();
        $user = auth()->user();

        $catalog = Item::query()
            ->distributable()
            ->visibleTo($user)
            ->whereKey((int) $data['catalogId'])
            ->first();

        if ($catalog === null) {
            $this->addError('catalogId', 'Katalog tidak tersedia dalam cakupan Anda.');

            return;
        }

        try {
            $distributions = $service->distributeFromStock(
                $catalog,
                $data['rows'],
                $user,
                filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                // Service memakai kunci `allocations.*`; formulir Livewire
                // memakai `rows.*` sehingga pesan tampil pada baris yang tepat.
                $key = $field;

                if ($field === 'allocations') {
                    $key = 'rows';
                } elseif (preg_match('/^allocations\.(\d+)\.(.+)$/', (string) $field, $matches) === 1) {
                    $key = "rows.{$matches[1]}.{$matches[2]}";
                }

                $this->addError($key, $messages[0]);
            }

            return;
        }

        $created = (int) $distributions->sum('quantity');
        $remaining = $catalog->fresh()->remainingStockQuantity();

        $this->rows = [$this->blankRow()];
        $this->note = '';
        $this->resetValidation();

        session()->flash('success', sprintf(
            '%d unit didistribusikan ke %d alokasi ruangan. Sisa stok belum didistribusikan: %d unit.',
            $created,
            $distributions->count(),
            $remaining,
        ));
    }

    public function render()
    {
        $user = auth()->user();

        $catalogs = Item::query()
            ->distributable()
            ->notArchived()
            ->visibleTo($user)
            ->with('category')
            ->withSum('receipts as received_stock', 'quantity')
            ->withSum(['distributions as distributed_stock' => fn ($query) => $query
                ->where('source', Distribution::SOURCE_STOCK),
            ], 'quantity')
            ->orderBy('name')
            ->get()
            ->map(function (Item $catalog): array {
                $remaining = max(0, (int) $catalog->received_stock - (int) $catalog->distributed_stock);

                return [
                    'id' => (string) $catalog->id,
                    'name' => $catalog->name,
                    'category' => $catalog->category?->name,
                    'received' => (int) $catalog->received_stock,
                    'distributed' => (int) $catalog->distributed_stock,
                    'remaining' => $remaining,
                ];
            })
            ->values();

        $selectedCatalog = $catalogs->firstWhere('id', $this->catalogId);

        $rooms = Room::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('is_lab', true))
            ->when($user->isOperator(), fn ($query) => $query->whereIn('id', $user->roomScopes()->select('room_id')))
            ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
            ->get();

        $requestedTotal = (int) collect($this->rows)->sum(fn ($row): int => (int) ($row['quantity'] ?? 0));
        $remaining = (int) ($selectedCatalog['remaining'] ?? 0);

        return view('livewire.distribution.stock-distribution-form', [
            'catalogs' => $catalogs,
            'selectedCatalog' => $selectedCatalog,
            'rooms' => $rooms,
            'conditions' => [Condition::BAIK, Condition::RUSAK_RINGAN, Condition::RUSAK_BERAT],
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
            'requestedTotal' => $requestedTotal,
            'stockRemaining' => $remaining,
            'overStock' => $requestedTotal > $remaining,
        ]);
    }
}
