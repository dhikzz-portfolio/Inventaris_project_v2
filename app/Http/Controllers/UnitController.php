<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitStatusLog;
use App\Models\LoanItemUnit;
use App\Models\Room;
use App\Models\Specification;
use App\Services\AssetUnitService;
use App\Services\InventoryCodeService;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class UnitController extends Controller
{
    public function show(AssetUnit $unit): View
    {
        abort_unless(
            AssetUnit::query()->visibleTo(auth()->user())->whereKey($unit->id)->exists(),
            404
        );

        $unit->load([
            'catalog.category',
            'specification',
            'room.major',
            'codes' => fn ($query) => $query->orderByDesc('issued_at'),
            'movements.fromRoom',
            'movements.toRoom',
            'movements.mover',
            'maintenanceRecords' => fn ($query) => $query->orderByDesc('performed_at'),
            'statusLogs.actor',
            'damageReports' => fn ($query) => $query
                ->with(['reporter', 'handler', 'logs.user', 'photos'])
                ->latest('created_at'),
        ]);

        $canViewDamageEvidence = auth()->user()->canApproveLoans();

        // Foto bukti dari pengembalian melekat pada unit fisik (bukan katalog),
        // sehingga unit yang sama dapat memiliki riwayat dari beberapa transaksi.
        $returnDamageRows = $canViewDamageEvidence
            ? LoanItemUnit::query()
                ->where('asset_unit_id', $unit->id)
                ->where(fn ($query) => $query
                    ->whereNotNull('damage_photo_path')
                    ->orWhereIn('condition_in', [Condition::RUSAK_RINGAN->value, Condition::RUSAK_BERAT->value]))
                ->with([
                    'loanItem.loan.borrower',
                    'loanItem.loan.requester',
                    'confirmer',
                ])
                ->latest('returned_at')
                ->get()
            : collect();

        $rooms = Room::query()
            ->when(! auth()->user()->isAdmin(), fn ($query) => $query->where('is_lab', true))
            ->whereKeyNot($unit->room_id)
            ->orderBy('building_letter')->orderBy('floor')->orderBy('room_no')
            ->get();

        $editHistory = Activity::query()
            ->where('log_name', 'unit')
            ->where('subject_type', $unit->getMorphClass())
            ->where('subject_id', $unit->getKey())
            ->with('causer')
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => $this->formatEditHistory($activity));

        return view('units.show', [
            'unit' => $unit,
            'destinationRooms' => $rooms,
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
            'canViewDamageEvidence' => $canViewDamageEvidence,
            'returnDamageRows' => $returnDamageRows,
            'editHistory' => $editHistory,
        ]);
    }

    /** Halaman koreksi data unit — hanya Admin. */
    public function edit(AssetUnit $unit): View
    {
        $this->authorizeUnitEditor($unit);

        $unit->load(['catalog.category', 'specification', 'room']);

        return view('units.edit', [
            'unit' => $unit,
            'conditions' => Condition::cases(),
            'statuses' => [UnitStatus::AKTIF, UnitStatus::HILANG],
            'statusLocked' => $unit->status === UnitStatus::DIPINJAM,
        ]);
    }

    /** Simpan koreksi data unit secara manual — hanya Admin. */
    public function updateDetails(Request $request, AssetUnit $unit, AssetUnitService $units): RedirectResponse
    {
        $this->authorizeUnitEditor($unit);

        abort_if($unit->catalog === null, 422, 'Unit belum memiliki katalog sehingga data tidak dapat diubah.');

        $data = $request->validate([
            'brand' => ['nullable', 'string', 'max:100'],
            'model_type' => ['nullable', 'string', 'max:100'],
            'condition' => ['required', Rule::enum(Condition::class)],
            'status' => $unit->status === UnitStatus::DIPINJAM
                ? ['nullable', Rule::in([UnitStatus::DIPINJAM->value])]
                : ['required', Rule::in([UnitStatus::AKTIF->value, UnitStatus::HILANG->value])],
            'status_reason' => ['nullable', 'string', 'max:500'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_year' => ['nullable', 'integer', 'between:1900,2100'],
            'acquisition_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'funding_source' => ['nullable', 'string', 'max:100'],
            'acquisition_source' => ['nullable', 'string', 'max:150'],
            'register_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Status unit yang sedang dipinjam dikelola oleh alur peminjaman,
        // bukan lewat koreksi manual, agar tidak terjadi data menggantung.
        $newStatus = $unit->status === UnitStatus::DIPINJAM
            ? UnitStatus::DIPINJAM
            : UnitStatus::from($data['status']);

        $reason = filled($data['status_reason'] ?? null) ? trim((string) $data['status_reason']) : null;

        if ($newStatus !== $unit->status && $reason === null) {
            throw ValidationException::withMessages([
                'status_reason' => 'Alasan wajib diisi ketika status operasional diubah.',
            ]);
        }

        $specification = Specification::resolveFor($unit->catalog, $data['brand'] ?? null, $data['model_type'] ?? null);

        $before = [
            'spesifikasi' => $unit->specification?->displayName(),
            'kondisi' => $unit->condition->value,
            'status' => $unit->status->value,
            'tanggal_perolehan' => $unit->acquisition_date?->toDateString(),
            'tahun_perolehan' => $unit->acquisition_year,
            'nilai_perolehan' => $unit->acquisition_value,
            'sumber_dana' => $unit->funding_source,
            'sumber_perolehan' => $unit->acquisition_source,
            'nomor_register' => $unit->register_no,
            'catatan' => $unit->notes,
        ];

        $unit->update([
            'specification_id' => $specification->id,
            'condition' => $data['condition'],
            'acquisition_date' => $data['acquisition_date'] ?? null,
            'acquisition_year' => $data['acquisition_year'] ?? null,
            'acquisition_value' => $data['acquisition_value'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'acquisition_source' => $data['acquisition_source'] ?? null,
            'register_no' => $data['register_no'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($newStatus !== $unit->status) {
            $unit = $units->changeStatus($unit, $newStatus, AssetUnitStatusLog::SOURCE_MANUAL, $reason, $request->user());
        }

        $unit->refresh()->load('specification');

        activity('unit')
            ->performedOn($unit)
            ->causedBy($request->user())
            ->withProperties([
                'sebelum' => $before,
                'sesudah' => [
                    'spesifikasi' => $unit->specification?->displayName(),
                    'kondisi' => $unit->condition->value,
                    'status' => $unit->status->value,
                    'tanggal_perolehan' => $unit->acquisition_date?->toDateString(),
                    'tahun_perolehan' => $unit->acquisition_year,
                    'nilai_perolehan' => $unit->acquisition_value,
                    'sumber_dana' => $unit->funding_source,
                    'sumber_perolehan' => $unit->acquisition_source,
                    'nomor_register' => $unit->register_no,
                    'catatan' => $unit->notes,
                ],
            ])
            ->log("Memperbarui data unit {$unit->inventory_code}");

        return redirect()->route('units.show', $unit)
            ->with('success', "Data unit {$unit->inventory_code} diperbarui.");
    }

    /** Ubah kondisi/catatan unit (Laboran dalam cakupan) dan status (pengelola). */
    public function update(Request $request, AssetUnit $unit, AssetUnitService $units): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            AssetUnit::query()->visibleTo($user)->whereKey($unit->id)->exists(),
            404
        );

        abort_if($unit->status === UnitStatus::DIHAPUS, 422, 'Unit yang sudah dihapus tidak dapat diubah.');

        $data = $request->validate([
            'condition' => ['required', Rule::enum(Condition::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status_reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Status hanya boleh diubah pengelola, dan tidak lewat alur hapus/pinjam.
        $newStatus = $unit->status;

        if ($user->managesInventory()) {
            $newStatus = UnitStatus::from($request->validate([
                'status' => ['required', Rule::in([UnitStatus::AKTIF->value, UnitStatus::HILANG->value])],
            ])['status']);
        }

        $statusChanged = $newStatus !== $unit->status;
        $reason = filled($data['status_reason'] ?? null) ? trim((string) $data['status_reason']) : null;

        if ($statusChanged && $reason === null) {
            throw ValidationException::withMessages([
                'status_reason' => 'Alasan wajib diisi ketika status operasional diubah.',
            ]);
        }

        $before = [
            'condition' => $unit->condition->value,
            'status' => $unit->status->value,
            'notes' => $unit->notes,
        ];

        $unit->update([
            'condition' => $data['condition'],
            'notes' => $data['notes'] ?? null,
        ]);

        if ($statusChanged) {
            $unit = $units->changeStatus($unit, $newStatus, AssetUnitStatusLog::SOURCE_MANUAL, $reason, $user);
        }

        $unit->refresh();

        activity('unit')
            ->performedOn($unit)
            ->causedBy($user)
            ->withProperties([
                'sebelum' => $before,
                'sesudah' => [
                    'condition' => $unit->condition->value,
                    'status' => $unit->status->value,
                    'notes' => $unit->notes,
                ],
            ])
            ->log("Memperbarui kondisi unit {$unit->inventory_code}");

        return back()->with('success', "Kondisi unit {$unit->inventory_code} diperbarui.");
    }

    /**
     * Ringkas satu entri audit menjadi daftar field yang benar-benar berubah.
     *
     * @return array{description: string, actor: string, created_at: ?string, changes: list<array{label: string, before: string, after: string}>}
     */
    private function formatEditHistory(Activity $activity): array
    {
        $before = (array) data_get($activity->properties, 'sebelum', []);
        $after = (array) data_get($activity->properties, 'sesudah', []);
        $changes = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[] = [
                'label' => $this->editHistoryFieldLabel($key),
                'before' => $this->editHistoryValue($key, $oldValue),
                'after' => $this->editHistoryValue($key, $newValue),
            ];
        }

        return [
            'description' => (string) $activity->description,
            'actor' => $activity->causer?->name ?? 'Sistem',
            'created_at' => $activity->created_at?->format('d/m/Y H:i'),
            'changes' => $changes,
        ];
    }

    private function editHistoryFieldLabel(string $key): string
    {
        return match ($key) {
            'condition', 'kondisi' => 'Kondisi',
            'status' => 'Status',
            'notes', 'catatan' => 'Catatan',
            'spesifikasi' => 'Merek / tipe',
            'tanggal_perolehan' => 'Tanggal perolehan',
            'tahun_perolehan' => 'Tahun perolehan',
            'nilai_perolehan' => 'Nilai perolehan',
            'sumber_dana' => 'Sumber dana',
            'sumber_perolehan' => 'Sumber perolehan',
            'nomor_register' => 'Nomor register',
            default => Str::headline($key),
        };
    }

    private function editHistoryValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'condition', 'kondisi' => Condition::tryFrom((string) $value)?->label() ?? (string) $value,
            'status' => UnitStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'tanggal_perolehan' => Carbon::parse((string) $value)->format('d/m/Y'),
            'nilai_perolehan' => is_numeric($value)
                ? 'Rp '.number_format((float) $value, 0, ',', '.')
                : (string) $value,
            default => (string) $value,
        };
    }

    /** Koreksi data unit hanya untuk role Admin. */
    private function authorizeUnitEditor(AssetUnit $unit): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Hanya Admin yang dapat mengedit data unit.');

        abort_unless(
            AssetUnit::query()->visibleTo(auth()->user())->whereKey($unit->id)->exists(),
            404
        );

        abort_if($unit->status === UnitStatus::DIHAPUS, 422, 'Unit yang sudah dihapus tidak dapat diubah.');
    }

    public function label(AssetUnit $unit, LabelService $labels)
    {
        abort_unless(
            AssetUnit::query()->visibleTo(auth()->user())->whereKey($unit->id)->exists(),
            404
        );

        return $labels->printUnit($unit)->stream('label-'.$unit->inventory_code.'.pdf');
    }

    public function move(Request $request, AssetUnit $unit, InventoryCodeService $codes): RedirectResponse
    {
        abort_unless(
            AssetUnit::query()->visibleTo($request->user())->whereKey($unit->id)->exists(),
            404
        );

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $destination = Room::findOrFail($data['room_id']);

        if (! $request->user()->isAdmin() && ! $destination->is_lab) {
            abort(404);
        }

        $moved = $codes->move($unit, $destination, $request->user(), $data['note'] ?? null);

        return redirect()->route('units.show', $moved)
            ->with('success', "Unit dipindahkan. Kode baru: {$moved->inventory_code}. Cetak ulang label unit ini.");
    }
}
