<?php

namespace App\Livewire\Loan;

use App\Exceptions\AvailabilityException;
use App\Models\Event;
use App\Models\Item;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Support\Carbon;
use Livewire\Component;

class LoanRequest extends Component
{
    // Keranjang
    public array $cart = [];          // [{item_id, name, internal_id, qty, max, room, unit}]

    public string $search = '';

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

    public function add(int $itemId): void
    {
        if (array_key_exists($itemId, $this->cart)) {
            return;
        }

        /** @var Item $item */
        $item = Item::with('room')->approved()->findOrFail($itemId);

        if (! $item->isAvailableStatus()) {
            $this->error = "\"{$item->name}\" berstatus {$item->status->label()} dan tidak dapat dipinjam.";

            return;
        }

        $this->cart[] = [
            'item_id' => $item->id,
            'name' => $item->name,
            'internal_id' => $item->internal_id,
            'room' => $item->room?->code,
            'unit' => $item->unit,
            'is_high_value' => $item->is_high_value,
            'is_consumable' => $item->isConsumable(),
            'max' => $item->quantity,
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

        $items = collect();
        if (strlen($this->search) >= 2) {
            $term = '%'.$this->search.'%';
            $items = Item::approved()
                ->with('room')
                ->where('status', '!=', 'DIHAPUS')
                ->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('internal_id', 'like', $term)
                    ->orWhere('category_id', function ($qq) use ($term) {
                        $qq->select('id')->from('categories')->where('name', 'like', $term);
                    }))
                ->orderBy('name')
                ->limit(12)
                ->get();
        }

        return view('livewire.loan.loan-request', [
            'items' => $items,
            'events' => Event::where('end_date', '>=', now()->subMonth())->orderByDesc('start_date')->get(),
            'lineStatus' => $this->lineStatuses(),
            'calendar' => $this->calendarData(),
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

        session()->flash('success', "Request peminjaman #{$loan->id} terkirim — menunggu persetujuan laboran.");
        $this->redirectRoute('loans.show', ['loan' => $loan->id]);
    }
}
