<?php

namespace App\Livewire\Loan;

use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Building;
use App\Models\Category;
use App\Models\Event;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Room;
use App\Services\LoanService;
use Illuminate\Support\Carbon;
use Livewire\Component;

class LoanRequest extends Component
{
    // Keranjang
    public array $cart = [];          // [{item_id, name, internal_id, qty, max, room, unit}]

    public string $search = '';

    public string $category = '';

    public string $building = '';

    public string $room = '';

    public string $availability = '';

    // Formulir
    public string $purpose = '';

    public string $event_id = '';

    public string $start_date = '';

    public string $due_date = '';

    // Kalender
    public int $calYear;

    public int $calMonth;

    public string $error = '';

    public string $success = '';

    public function mount(): void
    {
        $this->start_date = now()->addDay()->format('Y-m-d\T07:00');
        $this->due_date = now()->addDay()->format('Y-m-d\T15:00');
        $this->calYear = (int) now()->format('Y');
        $this->calMonth = (int) now()->format('n');
    }

    /** Katalog yang sudah dipilih tidak boleh ditambahkan sebagai entri duplikat. */
    public function add(int $itemId): void
    {
        if (collect($this->cart)->contains(fn (array $line) => (int) $line['item_id'] === $itemId)) {
            $this->error = 'Katalog ini sudah ada di keranjang. Ubah jumlahnya pada daftar pilihan.';

            return;
        }

        /** @var Item $item */
        $item = Item::with('room')
            ->approved()
            ->notArchived()
            ->visibleTo(auth()->user())
            ->findOrFail($itemId);

        $available = $item->availableQuantity();

        if (! $item->isAvailableStatus() || $available < 1) {
            $this->error = "\"{$item->name}\" tidak memiliki unit yang tersedia untuk dipinjam saat ini.";

            return;
        }

        $this->cart[] = [
            'item_id' => $item->id,
            'name' => $item->name,
            'internal_id' => $item->internal_id,
            'room' => $item->room?->code,
            'unit' => $item->unit,
            'is_high_value' => $item->is_high_value,
            'max' => $available,
            'qty' => 1,
        ];
        $this->error = '';
        $this->validateDates();
    }

    public function remove(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function updateQty(int $index, int $qty): void
    {
        if (isset($this->cart[$index])) {
            $max = $this->cart[$index]['max'] ?? 1;
            $this->cart[$index]['qty'] = max(1, min($qty, (int) $max));
        }
    }

    public function updatedCart(string $property, $value): void
    {
        // properti berbentuk cart.{index}.qty
        if (preg_match('/^cart\.(\d+)\.qty$/', $property, $m)) {
            $index = (int) $m[1];
            $max = $this->cart[$index]['max'] ?? 1;
            $this->cart[$index]['qty'] = max(1, min((int) $value, (int) $max));
        }
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'category', 'building', 'room', 'availability'], true)) {
            $this->error = '';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'category', 'building', 'room', 'availability']);
    }

    public function prevMonth(): void
    {
        $this->calMonth--;
        if ($this->calMonth < 1) {
            $this->calMonth = 12;
            $this->calYear--;
        }
    }

    public function nextMonth(): void
    {
        $this->calMonth++;
        if ($this->calMonth > 12) {
            $this->calMonth = 1;
            $this->calYear++;
        }
    }

    public function render()
    {
        $this->validateDates();

        $user = auth()->user();

        $items = Item::query()
            ->approved()
            ->notArchived()
            ->visibleTo($user)
            ->with(['room.building', 'category'])
            ->with(['units' => fn ($query) => $query
                ->visibleTo($user)
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value)
                ->orderBy('inventory_code')
                ->limit(8),
            ])
            ->withCount(['units as available_units_count' => fn ($query) => $query
                ->visibleTo($user)
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value),
            ])
            ->where('status', '!=', ItemStatus::DIHAPUS->value)
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($where) => $where
                    ->where('name', 'like', $term)
                    ->orWhere('specification', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('model_type', 'like', $term)
                    ->orWhere('internal_id', 'like', $term)
                    ->orWhereHas('units', fn ($unit) => $unit->where('inventory_code', 'like', $term)));
            })
            ->when($this->category !== '', fn ($query) => $query->where('category_id', $this->category))
            ->when($this->room !== '', fn ($query) => $query->whereHas('units', fn ($unit) => $unit->where('room_id', $this->room)))
            ->when($this->building !== '', fn ($query) => $query->whereHas('units.room', fn ($room) => $room->where('building_letter', $this->building)))
            ->when($this->availability === 'available', fn ($query) => $query->whereHas('units', fn ($unit) => $unit
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value)))
            ->when($this->availability === 'unavailable', fn ($query) => $query->whereDoesntHave('units', fn ($unit) => $unit
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', Condition::BAIK->value)))
            ->orderBy('name')
            ->limit(24)
            ->get();

        return view('livewire.loan.loan-request', [
            'items' => $items,
            'events' => Event::where('end_date', '>=', now()->subMonth())->orderByDesc('start_date')->get(),
            'lineStatus' => $this->lineStatuses(),
            'calendar' => $this->calendarData(),
            'categories' => Category::orderBy('name')->get(),
            'buildings' => Building::filterOptions(),
            'rooms' => Room::query()
                ->when($this->building !== '', fn ($query) => $query->where('building_letter', $this->building))
                ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
                ->get(),
            'activeFilters' => collect(['search', 'category', 'building', 'room', 'availability'])
                ->filter(fn ($field) => $this->$field !== '')->count(),
        ]);
    }

    /** Validasi rentang tanggal dasar. */
    private function validateDates(): void
    {
        if ($this->start_date && $this->due_date && $this->due_date <= $this->start_date) {
            $this->error = $this->error ?: 'Tanggal kembali harus setelah tanggal pinjam.';
        }
    }

    /**
     * Status ketersediaan per baris keranjang (anti-bentrok per rentang tanggal).
     *
     * @return array<int, array{ok: bool, msg: string}>
     */
    private function lineStatuses(): array
    {
        $statuses = [];
        if (! $this->start_date || ! $this->due_date || $this->due_date <= $this->start_date) {
            return $statuses;
        }

        $service = app(LoanService::class);
        foreach ($this->cart as $line) {
            try {
                $service->assertAvailable(
                    [['item_id' => (int) $line['item_id'], 'qty' => (int) $line['qty']]],
                    $this->start_date,
                    $this->due_date
                );
                $statuses[$line['item_id']] = ['ok' => true, 'msg' => 'Tersedia pada rentang tanggal ini'];
            } catch (AvailabilityException $e) {
                $statuses[$line['item_id']] = ['ok' => false, 'msg' => $e->getMessage()];
            }
        }

        return $statuses;
    }

    /**
     * Kalender ketersediaan: hari yang terpakai oleh pemesanan barang di keranjang.
     *
     * @return array{label: string, days: list<array{day: int, busy: bool}>, busyRanges: list<string>}
     */
    private function calendarData(): array
    {
        $first = Carbon::create($this->calYear, $this->calMonth, 1);
        $daysInMonth = $first->daysInMonth;
        $start = $first->copy()->startOfMonth();
        $end = $first->copy()->endOfMonth();

        $busyDays = [];
        $ranges = [];

        if ($this->cart !== [] && $this->start_date && $this->due_date) {
            $itemIds = array_column($this->cart, 'item_id');

            $loans = Loan::whereIn('status', ['PENDING', 'APPROVED', 'DIPINJAM'])
                ->whereHas('loanItems', fn ($q) => $q->whereIn('item_id', $itemIds))
                ->where('start_date', '<', $end->copy()->addDay())
                ->where('due_date', '>', $start)
                ->with('loanItems.item')
                ->get();

            foreach ($loans as $loan) {
                $names = $loan->loanItems->pluck('item.name')->unique()->implode(', ');
                $ranges[] = sprintf('%s — %s → %s (%s)',
                    $names,
                    $loan->start_date->format('d/m H:i'),
                    $loan->due_date->format('d/m H:i'),
                    $loan->status->label());

                $cursor = $loan->start_date->copy()->startOfDay();
                while ($cursor->lte($loan->due_date) && $cursor->lte($end)) {
                    if ($cursor->gte($start)) {
                        $busyDays[(int) $cursor->format('j')] = true;
                    }
                    $cursor->addDay();
                }
            }
        }

        $leading = ($first->dayOfWeek + 6) % 7; // Senin pertama
        $days = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $days[] = ['day' => $i, 'busy' => isset($busyDays[$i])];
        }

        return [
            'label' => $first->translatedFormat('F Y'),
            'leading' => $leading,
            'days' => $days,
            'busyRanges' => $ranges,
        ];
    }

    public function submit(): void
    {
        $this->error = '';

        if ($this->cart === []) {
            $this->error = 'Keranjang kosong — tambahkan minimal satu barang.';

            return;
        }
        if (strlen(trim($this->purpose)) < 3) {
            $this->error = 'Tujuan peminjaman wajib diisi (min. 3 karakter).';

            return;
        }
        if (! $this->start_date || ! $this->due_date || $this->due_date <= $this->start_date) {
            $this->error = 'Rentang tanggal tidak valid — tanggal kembali harus setelah tanggal pinjam.';

            return;
        }

        // Cegah katalog duplikat walau state frontend dimanipulasi.
        $itemIds = array_map(fn ($line) => (int) $line['item_id'], $this->cart);
        if (count($itemIds) !== count(array_unique($itemIds))) {
            $this->error = 'Katalog yang sama tidak boleh dipilih lebih dari sekali dalam satu pengajuan.';

            return;
        }

        try {
            $loan = app(LoanService::class)->requestLoan(
                auth()->user(),
                array_map(fn ($l) => ['item_id' => (int) $l['item_id'], 'qty' => (int) $l['qty']], $this->cart),
                trim($this->purpose),
                $this->start_date,
                $this->due_date,
                $this->event_id !== '' ? (int) $this->event_id : null
            );
        } catch (AvailabilityException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->cart = [];
        $this->purpose = '';

        session()->flash('success', $this->resultMessage($loan));
        $this->redirectRoute('loans.show', ['loan' => $loan->id]);
    }

    /** Pesan hasil yang membedakan persetujuan otomatis per kewenangan. */
    private function resultMessage(Loan $loan): string
    {
        if ($loan->status !== LoanStatus::APPROVED) {
            return "Request peminjaman #{$loan->id} terkirim — menunggu persetujuan.";
        }

        if (auth()->user()->isApproverLevel2()) {
            return "Request peminjaman #{$loan->id} disetujui atas kewenangan Wakasek Sarpras — lanjutkan ke serah terima.";
        }

        return "Request peminjaman #{$loan->id} disetujui otomatis untuk laboratorium yang Anda tugaskan — lanjutkan ke serah terima.";
    }
}
