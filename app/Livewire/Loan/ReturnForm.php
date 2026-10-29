<?php

namespace App\Livewire\Loan;

use App\Enums\Condition;
use App\Enums\ReturnStatus;
use App\Models\AssetUnit;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Models\Room;
use App\Services\InventoryCodeService;
use App\Services\LoanService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReturnForm extends Component
{
    use WithFileUploads;

    /** Minimal unit sejenis agar validasi massal kelompok ditampilkan. */
    private const MASS_VALIDATION_THRESHOLD = 11;

    public Loan $loan;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, int|string> Indeks baris yang dipilih (belum berarti terkonfirmasi). */
    public array $selected = [];

    public bool $confirmMismatch = false;

    public ?int $massGroupId = null;

    public string $massGroupName = '';

    public int $massPendingCount = 0;

    /** Pencarian & filter daftar unit validasi. */
    public string $search = '';

    public string $filterValidation = '';

    public string $filterCondition = '';

    public string $filterReturnState = '';

    public string $filterRoom = '';

    /** Unit yang disorot dari hasil scan QR (indeks baris). */
    public ?int $focusedIndex = null;

    public string $scanCode = '';

    public string $scanMessage = '';

    public string $scanError = '';

    public function mount(Loan $loan): void
    {
        $this->authorizeAction($loan);

        $this->loan = $loan;

        if (! $loan->isActive()) {
            $this->redirectRoute('loans.show', ['loan' => $loan->id]);

            return;
        }

        $this->loan->load([
            'loanItems.item.room',
            'loanItems.unitAssignments.unit.room',
            'borrower',
        ]);

        $this->buildRows();
    }

    private function authorizeAction(?Loan $loan = null): void
    {
        $user = auth()->user();
        $loan ??= $this->loan;

        abort_unless($user !== null && $loan->canBeManagedBy($user), 403);
    }

    private function buildRows(): void
    {
        $rows = [];

        foreach ($this->loan->loanItems as $loanItem) {
            $assignments = $loanItem->unitAssignments()
                ->with(['unit.room', 'confirmer'])
                ->orderBy('id')
                ->get();

            if ($assignments->isEmpty()) {
                $rows[] = $this->rowForLegacyLoanItem($loanItem);

                continue;
            }

            foreach ($assignments as $assignment) {
                $rows[] = $this->rowForAssignedUnit($loanItem, $assignment);
            }
        }

        $this->rows = $rows;
    }

    /** @return array<string, mixed> */
    private function rowForLegacyLoanItem(LoanItem $loanItem): array
    {
        $remaining = max(0, (int) $loanItem->qty_borrowed - (int) $loanItem->qty_returned);

        return [
            'loan_item_id' => $loanItem->id,
            'item_id' => $loanItem->item_id,
            'item_name' => $loanItem->item?->name,
            'specification' => $loanItem->item?->specification,
            'internal_id' => $loanItem->item?->internal_id,
            'room_code' => $loanItem->item?->room?->code,
            'assignment_id' => null,
            'unit_id' => null,
            'inventory_code' => null,
            'unit_location' => null,
            'unit_room_id' => null,
            'borrowed' => (int) $loanItem->qty_borrowed,
            'already_returned' => (int) $loanItem->qty_returned,
            'remaining' => $remaining,
            'qty_returned' => $remaining,
            'condition_out' => $loanItem->condition_out->value,
            'condition_in' => 'BAIK',
            'returned_room_id' => $loanItem->item?->room_id,
            'damage_photo' => null,
            'return_notes' => '',
            'returned' => false,
            'return_status' => ReturnStatus::BELUM_DIPERIKSA->value,
            'confirmed_by_name' => null,
            'confirmed_at' => null,
            'has_assignment' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function rowForAssignedUnit(LoanItem $loanItem, LoanItemUnit $assignment): array
    {
        $unit = $assignment->unit;

        return [
            'loan_item_id' => $loanItem->id,
            'item_id' => $loanItem->item_id,
            'item_name' => $loanItem->item?->name,
            'specification' => $unit?->specification?->name ?? $loanItem->item?->specification,
            'internal_id' => $loanItem->item?->internal_id,
            'room_code' => $loanItem->item?->room?->code,
            'assignment_id' => $assignment->id,
            'unit_id' => $assignment->asset_unit_id,
            'inventory_code' => $unit?->inventory_code,
            'unit_location' => $unit?->locationLabel(),
            'unit_room_id' => $unit?->room_id,
            'borrowed' => 1,
            'already_returned' => $assignment->returned_at !== null ? 1 : 0,
            'remaining' => $assignment->returned_at === null ? 1 : 0,
            'qty_returned' => $assignment->returned_at === null ? 0 : 1,
            'condition_out' => $assignment->condition_out?->value ?? Condition::BAIK->value,
            'condition_in' => $assignment->condition_in?->value ?? Condition::BAIK->value,
            'returned_room_id' => $unit?->room_id ?? $loanItem->item?->room_id,
            'damage_photo' => null,
            'return_notes' => $assignment->return_notes ?? '',
            'returned' => $assignment->returned_at !== null,
            'return_status' => ($assignment->return_status ?? ReturnStatus::BELUM_DIPERIKSA)->value,
            'confirmed_by_name' => $assignment->confirmer?->name,
            'confirmed_at' => $assignment->confirmed_at,
            'has_assignment' => true,
        ];
    }

    public function hasUnitBasedRows(): bool
    {
        return collect($this->rows)->contains(fn (array $row) => $row['has_assignment']);
    }

    /** @return array<int, int> */
    private function normalizedSelection(): array
    {
        return collect($this->selected)
            ->map(fn ($index) => (int) $index)
            ->filter(fn (int $index) => isset($this->rows[$index]))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function confirmableIndexesForGroup(int $itemId): array
    {
        return collect($this->rows)
            ->filter(fn (array $row) => (int) $row['item_id'] === $itemId
                && $row['has_assignment']
                && ! $row['returned'])
            ->keys()
            ->map(fn ($index) => (int) $index)
            ->values()
            ->all();
    }

    public function toggleGroup(int $itemId): void
    {
        $this->authorizeAction();

        $indexes = $this->confirmableIndexesForGroup($itemId);
        $selected = $this->normalizedSelection();
        $allSelected = $indexes !== [] && array_diff($indexes, $selected) === [];

        if ($allSelected) {
            $this->selected = array_values(array_diff($selected, $indexes));

            return;
        }

        $this->selected = array_values(array_unique(array_merge($selected, $indexes)));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->massGroupId = null;
        $this->massPendingCount = 0;
    }

    /** @return array<int, int> */
    private function targetIndexesForSubmit(): array
    {
        $indexes = $this->normalizedSelection();

        if (! $this->hasUnitBasedRows()) {
            return $indexes;
        }

        // Kompatibilitas alur lama: baris unit yang qty-nya sudah diisi
        // dianggap dipilih secara eksplisit. Membuka form saja tetap tidak
        // mengonfirmasi apa pun karena qty unit baru dimulai dari 0.
        foreach ($this->rows as $index => $row) {
            if ($row['has_assignment'] && (int) $row['qty_returned'] > 0 && ! $row['returned']) {
                $indexes[] = (int) $index;
            }
        }

        return array_values(array_unique($indexes));
    }

    private function validateRows(array $indexes): void
    {
        $rules = [];

        foreach ($indexes as $index) {
            $row = $this->rows[$index] ?? null;

            if ($row === null || $row['returned']) {
                continue;
            }

            $rules["rows.{$index}.condition_in"] = ['required', 'in:BAIK,RUSAK_RINGAN,RUSAK_BERAT'];
            $rules["rows.{$index}.returned_room_id"] = ['required', 'exists:rooms,id'];
            $rules["rows.{$index}.return_notes"] = ['nullable', 'string', 'max:1000'];

            $damaged = ($row['condition_in'] ?? Condition::BAIK->value) !== Condition::BAIK->value;
            $rules["rows.{$index}.damage_photo"] = $damaged
                ? ['required', 'image', 'max:4096']
                : ['nullable', 'image', 'max:4096'];

            if (! $row['has_assignment']) {
                $rules["rows.{$index}.qty_returned"] = [
                    'required', 'integer', 'min:0', 'max:'.(int) $row['remaining'],
                ];
            }
        }

        $this->validate($rules);
    }

    /** @return array<int, int> */
    private function mismatchesForIndexes(array $indexes): array
    {
        $out = [];

        foreach ($indexes as $index) {
            $row = $this->rows[$index] ?? null;

            if ($row === null || $row['returned']) {
                continue;
            }

            if (! $row['has_assignment'] && (int) ($row['qty_returned'] ?? 0) < 1) {
                continue;
            }

            $originalRoom = $row['unit_room_id']
                ?? $this->loan->loanItems->firstWhere('item_id', $row['item_id'])?->item?->room_id;

            if ((int) ($row['returned_room_id'] ?? 0) !== (int) $originalRoom) {
                $out[] = $index;
            }
        }

        return $out;
    }

    public function hasMismatch(): bool
    {
        return $this->mismatchRows() !== [];
    }

    public function mismatchRows(): array
    {
        return $this->mismatchesForIndexes(array_keys($this->rows));
    }

    private function processIndexes(array $indexes, bool $redirect = false): void
    {
        $this->authorizeAction();

        $indexes = array_values(array_filter(
            array_unique(array_map('intval', $indexes)),
            fn (int $index) => isset($this->rows[$index]) && ! $this->rows[$index]['returned']
        ));

        if ($indexes === []) {
            $this->addError('selection', 'Pilih minimal satu unit yang belum dikonfirmasi.');

            return;
        }

        $this->validateRows($indexes);

        $mismatches = $this->mismatchesForIndexes($indexes);
        if ($mismatches !== [] && ! $this->confirmMismatch) {
            $this->addError(
                'confirmMismatch',
                'Ada unit yang dikembalikan ke ruangan berbeda dari ruangan asal — konfirmasi dulu (akan tercatat sebagai mutasi).'
            );

            return;
        }

        $payload = [];

        foreach ($indexes as $index) {
            $row = $this->rows[$index];

            $payload[] = [
                'loan_item_id' => (int) $row['loan_item_id'],
                'assignment_id' => $row['assignment_id'] !== null ? (int) $row['assignment_id'] : null,
                'unit_id' => $row['unit_id'] !== null ? (int) $row['unit_id'] : null,
                'qty_returned' => $row['has_assignment'] ? 1 : (int) $row['qty_returned'],
                'condition_in' => $row['condition_in'],
                'returned_room_id' => (int) $row['returned_room_id'],
                'return_notes' => filled($row['return_notes'] ?? null) ? (string) $row['return_notes'] : null,
                'damage_photo' => $row['damage_photo'] ?? null,
            ];
        }

        app(LoanService::class)->processReturn($this->loan, auth()->user(), $payload);

        foreach ($indexes as $index) {
            if ($this->rows[$index]['has_assignment']) {
                $this->rows[$index]['returned'] = true;
                $this->rows[$index]['return_status'] = ReturnStatus::SUDAH_DIKONFIRMASI->value;
                $this->rows[$index]['confirmed_by_name'] = auth()->user()?->name;
                $this->rows[$index]['confirmed_at'] = now();
            }
        }

        $this->selected = array_values(array_diff($this->normalizedSelection(), $indexes));
        $this->massGroupId = null;
        $this->massPendingCount = 0;

        session()->flash('success', count($indexes).' unit berhasil dikonfirmasi.');

        if ($redirect) {
            $this->redirectRoute('loans.show', ['loan' => $this->loan->id]);
        }
    }

    /** Konfirmasi satu unit tanpa menyentuh unit lain. */
    public function confirmOne(int $index): void
    {
        $this->processIndexes([$index]);
    }

    /** Konfirmasi unit-unit yang dicentang. Pilihan saja bukan konfirmasi. */
    public function confirmSelected(): void
    {
        $this->processIndexes($this->normalizedSelection());
    }

    /**
     * Aksi submit lama tetap dipertahankan untuk kompatibilitas: pada
     * transaksi berbasis unit hanya baris yang dipilih/eksplisit diisi yang
     * diproses. Transaksi legacy tetap memakai alur kuantitas.
     */
    public function submit(): void
    {
        $this->authorizeAction();

        if ($this->hasUnitBasedRows()) {
            $this->processIndexes($this->targetIndexesForSubmit(), true);

            return;
        }

        $this->submitLegacy();
    }

    private function submitLegacy(): void
    {
        $rules = [
            'rows' => ['required', 'array'],
            'rows.*.loan_item_id' => ['required', 'integer', 'exists:loan_items,id'],
            'rows.*.qty_returned' => ['required', 'integer', 'min:0'],
            'rows.*.condition_in' => ['required', 'in:BAIK,RUSAK_RINGAN,RUSAK_BERAT'],
            'rows.*.returned_room_id' => ['required', 'exists:rooms,id'],
            'rows.*.return_notes' => ['nullable', 'string', 'max:1000'],
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

        $validated = $this->validate($rules);

        $mismatches = $this->mismatchRows();
        if ($mismatches !== [] && ! $this->confirmMismatch) {
            $this->addError(
                'confirmMismatch',
                'Ada barang yang dikembalikan ke ruangan berbeda dari ruangan asal — konfirmasi dulu (akan tercatat sebagai mutasi).'
            );

            return;
        }

        app(LoanService::class)->processReturn($this->loan, auth()->user(), $validated['rows']);

        session()->flash('success', 'Pengembalian berhasil diproses.');
        $this->redirectRoute('loans.show', ['loan' => $this->loan->id]);
    }

    /**
     * Minta konfirmasi massal. Penghitungan ulang dilakukan saat konfirmasi
     * sehingga permintaan yang sudah basi tidak memproses kelompok lain.
     */
    public function requestMassValidation(int $itemId): void
    {
        $this->authorizeAction();

        $group = collect($this->rows)->filter(fn (array $row) => (int) $row['item_id'] === $itemId);
        $assignedTotal = $group->where('has_assignment', true)->count();
        $pending = $this->confirmableIndexesForGroup($itemId);

        if ($assignedTotal < self::MASS_VALIDATION_THRESHOLD) {
            $this->massGroupId = null;
            $this->addError(
                'mass',
                'Validasi massal hanya tersedia untuk kelompok sejenis dengan minimal '.self::MASS_VALIDATION_THRESHOLD.' unit.'
            );

            return;
        }

        if ($pending === []) {
            $this->massGroupId = null;
            $this->addError('mass', 'Semua unit pada kelompok ini sudah dikonfirmasi.');

            return;
        }

        $this->resetErrorBag('mass');
        $this->massGroupId = $itemId;
        $this->massGroupName = (string) ($group->first()['item_name'] ?? 'Kelompok barang');
        $this->massPendingCount = count($pending);
    }

    public function cancelMassValidation(): void
    {
        $this->massGroupId = null;
        $this->massGroupName = '';
        $this->massPendingCount = 0;
    }

    public function confirmMassValidation(): void
    {
        $this->authorizeAction();

        if ($this->massGroupId === null) {
            $this->addError('mass', 'Tidak ada kelompok yang dipilih untuk validasi massal.');

            return;
        }

        $itemId = (int) $this->massGroupId;
        $group = collect($this->rows)->filter(fn (array $row) => (int) $row['item_id'] === $itemId);
        $assignedTotal = $group->where('has_assignment', true)->count();
        $pending = $this->confirmableIndexesForGroup($itemId);

        if ($assignedTotal < self::MASS_VALIDATION_THRESHOLD) {
            $this->addError('mass', 'Validasi massal tidak lagi memenuhi syarat minimal 11 unit untuk kelompok ini.');

            return;
        }

        if ($pending === []) {
            $this->addError('mass', 'Tidak ada unit tersisa yang perlu divalidasi pada kelompok ini.');
            $this->cancelMassValidation();

            return;
        }

        $this->processIndexes($pending);
    }

    /** Selesaikan transaksi setelah seluruh unit terkonfirmasi. */
    public function finishReturn(): void
    {
        $this->authorizeAction();

        $progress = $this->renderProgress();

        if (! $progress['complete']) {
            $this->addError(
                'finish',
                "Masih ada {$progress['pending']} dari {$progress['total']} unit yang belum dikonfirmasi."
            );

            return;
        }

        try {
            app(LoanService::class)->completeReturn($this->loan, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('finish', $message);
                }
            }

            return;
        } catch (\RuntimeException $e) {
            $this->addError('finish', $e->getMessage());

            return;
        }

        session()->flash('success', 'Pengembalian selesai — seluruh unit telah dikonfirmasi.');
        $this->redirectRoute('loans.show', ['loan' => $this->loan->id]);
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'rows.') && $this->confirmMismatch && ! $this->hasMismatch()) {
            $this->confirmMismatch = false;
        }

        if (in_array($property, ['search', 'filterValidation', 'filterCondition', 'filterReturnState', 'filterRoom'], true)) {
            $this->focusedIndex = null;
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterValidation = '';
        $this->filterCondition = '';
        $this->filterReturnState = '';
        $this->filterRoom = '';
        $this->focusedIndex = null;
        $this->resetErrorBag('scan');
    }

    /** Status tampilan satu baris: pilihan belum berarti konfirmasi. */
    private function displayStatus(int $index): ReturnStatus
    {
        $row = $this->rows[$index] ?? null;

        if ($row === null) {
            return ReturnStatus::BELUM_DIPERIKSA;
        }

        if ($row['returned']) {
            return ReturnStatus::SUDAH_DIKONFIRMASI;
        }

        return in_array($index, $this->normalizedSelection(), true)
            ? ReturnStatus::MENUNGGU_KONFIRMASI
            : ReturnStatus::BELUM_DIPERIKSA;
    }

    /** @param array<string, mixed> $row */
    private function rowMatchesFilters(array $row): bool
    {
        $term = mb_strtolower(trim($this->search));

        if ($term !== '') {
            $haystack = mb_strtolower(implode(' ', array_filter([
                $row['item_name'] ?? null,
                $row['specification'] ?? null,
                $row['internal_id'] ?? null,
                $row['inventory_code'] ?? null,
                $row['unit_location'] ?? null,
                $row['room_code'] ?? null,
                $row['return_notes'] ?? null,
            ])));

            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        if ($this->filterValidation !== '') {
            $index = (int) ($row['row_index'] ?? -1);

            if ($this->displayStatus($index)->value !== $this->filterValidation) {
                return false;
            }
        }

        if ($this->filterCondition !== '' && ($row['condition_in'] ?? '') !== $this->filterCondition) {
            return false;
        }

        if ($this->filterReturnState === 'sudah' && ! $row['returned']) {
            return false;
        }

        if ($this->filterReturnState === 'belum' && $row['returned']) {
            return false;
        }

        if ($this->filterRoom !== '' && (int) ($row['unit_room_id'] ?? 0) !== (int) $this->filterRoom) {
            return false;
        }

        return true;
    }

    /**
     * Temukan unit dari hasil scan dan pilih barisnya. Tidak pernah
     * mengonfirmasi pengembalian — petugas tetap menekan aksi konfirmasi.
     */
    public function scanUnitCode(?string $code = null): void
    {
        $this->authorizeAction();
        $this->scanError = '';
        $this->scanMessage = '';

        $raw = trim((string) ($code ?? $this->scanCode));

        if ($raw === '') {
            $this->scanError = 'Masukkan kode inventaris atau pindai QR unit terlebih dahulu.';

            return;
        }

        $unitId = null;

        if (preg_match('#^https?://#i', $raw) === 1) {
            $parts = parse_url($raw) ?: [];
            parse_str((string) ($parts['query'] ?? ''), $query);

            if (isset($query['u']) && ctype_digit((string) $query['u'])) {
                $unitId = (int) $query['u'];
            }

            $raw = isset($query['c']) ? (string) $query['c'] : basename((string) ($parts['path'] ?? ''));
        }

        $unit = $unitId !== null
            ? AssetUnit::query()->find($unitId)
            : app(InventoryCodeService::class)->resolve($raw);

        if ($unit === null) {
            $this->scanError = 'QR tidak dikenali. Periksa stiker atau masukkan kode inventaris secara manual.';

            return;
        }

        $index = collect($this->rows)->search(
            fn (array $row) => (int) ($row['unit_id'] ?? 0) === (int) $unit->id
        );

        if ($index === false) {
            $this->scanError = 'Unit tidak termasuk dalam transaksi pengembalian yang sedang divalidasi.';

            return;
        }

        $index = (int) $index;

        if ($this->rows[$index]['returned']) {
            $this->scanError = 'Unit ini sudah dikonfirmasi pengembaliannya.';

            return;
        }

        // Tampilkan baris yang ditemukan meskipun filter aktif menyembunyikannya.
        $this->search = '';
        $this->filterValidation = '';
        $this->filterCondition = '';
        $this->filterReturnState = '';
        $this->filterRoom = '';
        $this->focusedIndex = $index;

        $selected = $this->normalizedSelection();
        if (! in_array($index, $selected, true)) {
            $selected[] = $index;
            $this->selected = array_values($selected);
        }

        $this->scanCode = '';
        $this->scanMessage = 'Unit '.$unit->inventory_code.' ditemukan dan dipilih. Konfirmasi tetap memerlukan tindakan Anda.';
        $this->dispatch('focus-unit', index: $index);
    }

    /** @return array{total: int, confirmed: int, pending: int, percent: int, complete: bool} */
    private function renderProgress(): array
    {
        $total = 0;
        $confirmed = 0;

        foreach ($this->rows as $row) {
            if ($row['has_assignment']) {
                $total++;
                $confirmed += $row['returned'] ? 1 : 0;

                continue;
            }

            $borrowed = (int) $row['borrowed'];
            $total += $borrowed;
            $confirmed += min($borrowed, (int) $row['already_returned']);
        }

        $pending = max(0, $total - $confirmed);

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'percent' => $total > 0 ? (int) round(($confirmed / $total) * 100) : 0,
            'complete' => $total > 0 && $pending === 0,
        ];
    }

    public function render()
    {
        $rooms = Room::orderBy('code')->get();
        $selected = $this->normalizedSelection();

        $indexedRows = collect($this->rows)->map(
            fn (array $row, int $index) => $row + ['row_index' => $index]
        );

        $filteredRows = $indexedRows
            ->filter(fn (array $row) => $this->rowMatchesFilters($row))
            ->values();

        $groupedRows = $filteredRows->groupBy('item_id')->map(function ($groupRows, $itemId) use ($selected, $indexedRows) {
            $groupRows = $groupRows->values();
            $allRows = $indexedRows->where('item_id', (int) $itemId)->values();
            $assignedRows = $allRows->where('has_assignment', true);
            $assignedTotal = $assignedRows->count();
            $openAssigned = $assignedRows->filter(fn (array $row) => ! $row['returned'])->count();

            $totalBorrowed = (int) $allRows->sum('borrowed');
            $confirmed = (int) $allRows->sum(function (array $row) {
                if ($row['has_assignment']) {
                    return $row['returned'] ? 1 : 0;
                }

                return min((int) $row['borrowed'], (int) $row['already_returned']);
            });
            $remaining = max(0, $totalBorrowed - $confirmed);

            // Pilihan massal mengikuti baris yang sedang tampil (hasil filter),
            // bukan unit tersembunyi yang tidak dilihat petugas.
            $groupIndexes = $groupRows
                ->filter(fn (array $row) => $row['has_assignment'] && ! $row['returned'])
                ->pluck('row_index')
                ->map(fn ($index) => (int) $index)
                ->values()
                ->all();

            $allSelected = $groupIndexes !== [] && array_diff($groupIndexes, $selected) === [];
            $selectedInGroup = count(array_intersect($groupIndexes, $selected));

            return [
                'item_id' => (int) $itemId,
                'item_name' => $groupRows->first()['item_name'] ?? '-',
                'specification' => $groupRows->first()['specification'] ?? null,
                'rows' => $groupRows,
                'total_borrowed' => $totalBorrowed,
                'confirmed' => $confirmed,
                'remaining' => $remaining,
                'assigned_total' => $assignedTotal,
                'open_assigned' => $openAssigned,
                'visible_assignable' => count($groupIndexes),
                'selected_in_group' => $selectedInGroup,
                'all_selected' => $allSelected,
                'status' => $confirmed >= $totalBorrowed ? 'Selesai' : ($confirmed > 0 ? 'Sebagian' : 'Belum dikembalikan'),
                'can_mass_validate' => $assignedTotal >= self::MASS_VALIDATION_THRESHOLD && $openAssigned > 0,
            ];
        })->values();

        $progress = $this->renderProgress();
        $selectedCount = count($selected);

        $pendingLabels = $indexedRows
            ->filter(fn (array $row) => $row['has_assignment']
                ? ! $row['returned']
                : (int) $row['already_returned'] < (int) $row['borrowed'])
            ->map(fn (array $row) => $row['has_assignment']
                ? ($row['inventory_code'] ?? $row['item_name'])
                : $row['item_name'].' ('.$row['remaining'].' unit)')
            ->take(8)
            ->values();

        $filterRooms = $indexedRows
            ->filter(fn (array $row) => $row['unit_room_id'] !== null)
            ->map(fn (array $row) => [
                'id' => (int) $row['unit_room_id'],
                'label' => ($row['unit_location'] ?: $row['room_code']) ?? '-',
            ])
            ->unique('id')
            ->sortBy('label')
            ->values();

        return view('livewire.loan.return-form', [
            'rooms' => $rooms,
            'conditions' => Condition::cases(),
            'returnStatuses' => ReturnStatus::cases(),
            'filterRooms' => $filterRooms,
            'visibleUnitCount' => $filteredRows->count(),
            'totalUnitCount' => $indexedRows->count(),
            'mismatches' => $this->mismatchRows(),
            'groupedRows' => $groupedRows,
            'progress' => $progress,
            'selectedCount' => $selectedCount,
            'canFinish' => $progress['complete'],
            'hasUnitBasedRows' => $this->hasUnitBasedRows(),
            'massThreshold' => self::MASS_VALIDATION_THRESHOLD,
            'pendingLabels' => $pendingLabels,
        ]);
    }
}
