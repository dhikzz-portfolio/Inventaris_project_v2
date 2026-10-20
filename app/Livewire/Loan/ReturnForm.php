<?php

namespace App\Livewire\Loan;

use App\Enums\Condition;
use App\Models\Loan;
use App\Models\Room;
use App\Services\LoanService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReturnForm extends Component
{
    use WithFileUploads;

    public Loan $loan;

    public array $rows = [];

    public bool $confirmMismatch = false;

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->load(['loanItems.item.room', 'borrower']);

        foreach ($this->loan->loanItems as $li) {
            $remaining = $li->qty_borrowed - (int) $li->qty_returned;
            $this->rows[] = [
                'loan_item_id' => $li->id,
                'item_id' => $li->item_id,
                'item_name' => $li->item?->name,
                'internal_id' => $li->item?->internal_id,
                'unit' => $li->item?->unit,
                'is_consumable' => $li->item?->isConsumable() ?? false,
                'room_code' => $li->item?->room?->code,
                'borrowed' => $li->qty_borrowed,
                'already_returned' => (int) $li->qty_returned,
                'remaining' => $remaining,
                'qty_returned' => max(0, $remaining),
                'condition_out' => $li->condition_out->value,
                'condition_in' => 'BAIK',
                'returned_room_id' => $li->item?->room_id,
                'damage_photo' => null,
            ];
        }
    }

    /** @return array<string, mixed> */
    private function dynamicRules(): array
    {
        $rules = [
            'rows' => ['required', 'array'],
            'rows.*.loan_item_id' => ['required', 'integer', 'exists:loan_items,id'],
            'rows.*.qty_returned' => ['required', 'integer', 'min:0'],
            'rows.*.condition_in' => ['required', 'in:BAIK,RUSAK_RINGAN,RUSAK_BERAT'],
            'rows.*.returned_room_id' => ['required', 'exists:rooms,id'],
            'confirmMismatch' => ['boolean'],
        ];

        foreach ($this->rows as $i => $row) {
            $rules["rows.{$i}.qty_returned"][] = 'max:'.(int) $row['remaining'];

            if (($row['condition_in'] ?? 'BAIK') !== 'BAIK' && (int) ($row['qty_returned'] ?? 0) > 0) {
                $rules["rows.{$i}.damage_photo"] = ['required', 'image', 'max:4096'];
            } else {
                $rules["rows.{$i}.damage_photo"] = ['nullable', 'image', 'max:4096'];
            }
        }

        return $rules;
    }

    public function updated(string $property): void
    {
        // Reset konfirmasi saat baris berubah (mis. ruangan tujuan diganti).
        if (str_starts_with($property, 'rows.')) {
            $this->confirmMismatch = $this->confirmMismatch && ! $this->hasMismatch();
        }
    }

    public function hasMismatch(): bool
    {
        return $this->mismatchRows() !== [];
    }

    public function mismatchRows(): array
    {
        $out = [];
        foreach ($this->rows as $i => $row) {
            $origRoom = $this->loan->loanItems->firstWhere('item_id', $row['item_id'])?->item?->room_id;
            if ((int) ($row['returned_room_id'] ?? 0) !== (int) $origRoom) {
                $out[] = $i;
            }
        }

        return $out;
    }

    public function submit(LoanService $service): void
    {
        $validated = $this->validate($this->dynamicRules());

        $mismatches = $this->mismatchRows();
        if ($mismatches !== [] && ! $this->confirmMismatch) {
            $this->addError('confirmMismatch', 'Ada barang yang dikembalikan ke ruangan berbeda dari ruangan asal — konfirmasi dulu (akan tercatat sebagai mutasi).');

            return;
        }

        $rows = [];
        foreach ($validated['rows'] as $i => $row) {
            $payload = $row;
            if (isset($row['damage_photo'])) {
                $payload['damage_photo'] = $row['damage_photo'];
            }
            $rows[] = $payload;
        }

        $service->processReturn($this->loan, auth()->user(), $rows);

        session()->flash('success', 'Pengembalian berhasil diproses.');
        $this->redirectRoute('loans.show', ['loan' => $this->loan->id]);
    }

    public function render()
    {
        $rooms = Room::orderBy('code')->get();

        return view('livewire.loan.return-form', [
            'rooms' => $rooms,
            'conditions' => Condition::cases(),
            'mismatches' => $this->mismatchRows(),
            'totalShortfall' => collect($this->rows)->sum(fn ($r) => max(0, (int) $r['remaining'] - (int) $r['qty_returned'])),
        ]);
    }
}
