<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\DocumentStatus;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\NotificationType;
use App\Enums\UnitStatus;
use App\Exceptions\AvailabilityException;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LoanService
{
    /**
     * Cek ketersediaan anti-bentrok: total unit fisik dikurangi unit rusak/hilang
     * tidak boleh terlampaui oleh reservasi lain pada rentang tanggal yang sama.
     *
     * @param  array<int, array{item_id: int, qty: int}>  $lines
     *
     * @throws AvailabilityException
     */
    public function assertAvailable(array $lines, string $start, string $due, ?int $excludeLoanId = null): void
    {
        $startAt = Carbon::parse($start);
        $dueAt = Carbon::parse($due);

        if ($dueAt->lessThanOrEqualTo($startAt)) {
            throw new AvailabilityException('Tanggal kembali harus setelah tanggal mulai peminjaman.');
        }

        // Urutkan berdasarkan ID agar transaksi bersamaan mengambil lock dengan urutan sama.
        usort($lines, fn ($a, $b) => $a['item_id'] <=> $b['item_id']);

        foreach ($lines as $line) {
            $qty = (int) $line['qty'];
            if ($qty < 1) {
                throw new AvailabilityException('Jumlah barang yang dipinjam minimal 1.');
            }

            /** @var Item $item */
            $item = Item::where('id', $line['item_id'])->lockForUpdate()->with('room')->firstOrFail();

            if ($item->status === ItemStatus::DIHAPUS) {
                throw new AvailabilityException("Barang \"{$item->name}\" sudah dihapus dari inventaris.");
            }
            if ($item->acquisition_status !== 'APPROVED') {
                throw new AvailabilityException("Barang \"{$item->name}\" belum disetujui pada proses pengadaan.");
            }
            if ($item->isArchived()) {
                throw new AvailabilityException("Barang \"{$item->name}\" sudah dinonaktifkan dan tidak dapat dipinjam lagi.");
            }

            $physical = $item->quantity - $item->qty_damaged - $item->qty_lost;
            if ($physical <= 0) {
                throw new AvailabilityException("Barang \"{$item->name}\" tidak memiliki unit yang dapat dipinjam.");
            }

            $overlappingQty = (int) LoanItem::query()
                ->where('item_id', $item->id)
                ->whereIn('loan_id', function ($q) use ($startAt, $dueAt, $excludeLoanId) {
                    $q->select('id')->from('loans')
                        ->whereIn('status', [
                            LoanStatus::PENDING->value,
                            LoanStatus::APPROVED->value,
                            LoanStatus::DIPINJAM->value,
                        ])
                        ->where('start_date', '<', $dueAt)
                        ->where('due_date', '>', $startAt)
                        ->when($excludeLoanId, fn ($qq) => $qq->where('id', '!=', $excludeLoanId));
                })
                ->selectRaw('COALESCE(SUM(qty_borrowed - COALESCE(qty_returned, 0)), 0) as total')
                ->value('total');

            if ($overlappingQty + $qty > $physical) {
                $available = max(0, $physical - $overlappingQty);

                throw new AvailabilityException(
                    "Jadwal bentrok: \"{$item->name}\" hanya tersedia {$available} {$item->unit} pada rentang tanggal yang dipilih (unit rusak/hilang tidak dihitung)."
                );
            }
        }
    }

    /**
     * Simpan request peminjaman multi-item dalam satu transaksi database.
     *
     * @param  array<int, array{item_id: int, qty: int}>  $lines
     */
    public function requestLoan(User $borrower, array $lines, string $purpose, string $start, string $due, ?int $eventId = null): Loan
    {
        $merged = $this->mergeLines($lines);

        return DB::transaction(function () use ($borrower, $merged, $purpose, $start, $due, $eventId) {
            $this->assertAvailable($merged, $start, $due);

            /** @var Collection<int, Item> $items */
            $items = Item::with('room')->whereIn('id', array_column($merged, 'item_id'))->get()->keyBy('id');
            $hasHighValue = $items->contains(fn (Item $item) => $item->is_high_value);

            // Wakasek adalah pengelola penuh inventaris sekolah: pengajuannya
            // tidak menunggu persetujuan orang lain. Persetujuannya dicatat atas
            // nama dirinya sendiri — tidak dipalsukan sebagai persetujuan Admin lain.
            $isWakasek = $borrower->isApproverLevel2();

            // Laboran/K3 yang mengajukan barang dari laboratorium yang menjadi
            // kewenangannya sendiri tidak perlu endorsement orang lain. Barang
            // bernilai tinggi tetap harus menunggu persetujuan Wakasek.
            $withinOwnLab = $this->allLinesWithinLaboranScope($borrower, $merged, $items);
            $autoEndorseLevel1 = ! $isWakasek && $borrower->isLaboran() && $withinOwnLab;
            $selfApproved = $isWakasek || ($autoEndorseLevel1 && ! $hasHighValue);

            $loan = Loan::create([
                'borrower_id' => $borrower->id,
                'event_id' => $eventId,
                'purpose' => $purpose,
                'start_date' => $start,
                'due_date' => $due,
                'status' => $selfApproved ? LoanStatus::APPROVED : LoanStatus::PENDING,
                'needs_high_value_approval' => $hasHighValue,
                'document_status' => DocumentStatus::BELUM_DIBUAT,
            ]);

            foreach ($merged as $line) {
                $item = Item::findOrFail($line['item_id']);
                LoanItem::create([
                    'loan_id' => $loan->id,
                    'item_id' => $item->id,
                    'qty_borrowed' => $line['qty'],
                    'condition_out' => Condition::BAIK->value,
                ]);
            }

            // Selama masa transisi, peminjaman masih level katalog/kuantitas.
            // Unit fisik ditugaskan pada saat serah terima (handover).
            activity('peminjaman')
                ->performedOn($loan)
                ->causedBy($borrower)
                ->log('Mengajukan peminjaman '.count($merged).' barang');

            if ($selfApproved) {
                $loan->approvals()->create([
                    'approver_id' => $borrower->id,
                    'level' => $loan->finalApprovalLevel(),
                    'decision' => Decision::APPROVED->value,
                    'note' => $autoEndorseLevel1
                        ? 'Persetujuan otomatis — Laboran/K3 mengajukan barang dari laboratorium yang ditugaskan, bukan barang bernilai tinggi.'
                        : 'Persetujuan melekat — pengajuan oleh Wakasek Sarpras atas kewenangan penuh inventaris sekolah.',
                    'signature_path' => null,
                    'decided_at' => now(),
                ]);

                activity('peminjaman')
                    ->performedOn($loan)
                    ->causedBy($borrower)
                    ->log($autoEndorseLevel1
                        ? 'Persetujuan otomatis: Laboran/K3 mengajukan barang dari laboratorium sendiri (bukan nilai tinggi)'
                        : 'Persetujuan melekat: pengajuan disetujui atas kewenangan Wakasek (tanpa persetujuan tambahan)');

                if ($loan->event_id) {
                    app(BastService::class)->generateForLoan($loan);
                }

                return $loan;
            }

            // Barang bernilai tinggi dari laboratorium sendiri: endorsemen level 1
            // dicatat otomatis atas kewenangan Laboran/K3, lalu menunggu
            // persetujuan khusus Wakasek. Ini mencegah pemohon menyetujui
            // dirinya sendiri lewat request manual.
            if ($autoEndorseLevel1) {
                $loan->approvals()->create([
                    'approver_id' => $borrower->id,
                    'level' => 1,
                    'decision' => Decision::APPROVED->value,
                    'note' => 'Endorsemen otomatis Laboran/K3 untuk laboratorium yang ditugaskan; barang bernilai tinggi tetap memerlukan persetujuan Wakasek.',
                    'signature_path' => null,
                    'decided_at' => now(),
                ]);

                activity('peminjaman')
                    ->performedOn($loan)
                    ->causedBy($borrower)
                    ->log('Endorsemen otomatis level 1: Laboran/K3 mengajukan barang bernilai tinggi dari laboratorium sendiri');

                NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PERSETUJUAN,
                    "Persetujuan barang bernilai tinggi diperlukan untuk \"{$purpose}\" (peminjam {$borrower->name}).",
                    route('loans.pending'));

                return $loan;
            }

            NotifyApp::toApprovers(
                NotificationType::PERSETUJUAN,
                "Request peminjaman baru dari {$borrower->name}: \"{$purpose}\"",
                route('loans.show', $loan)
            );

            return $loan;
        });
    }

    /**
     * Semua baris berada pada laboratorium yang ditugaskan ke Laboran/K3.
     * Laboran tanpa penugasan ruangan tidak memperoleh persetujuan otomatis.
     *
     * @param  array<int, array{item_id: int, qty: int}>  $lines
     * @param  Collection<int, Item>  $items
     */
    private function allLinesWithinLaboranScope(User $borrower, array $lines, Collection $items): bool
    {
        if (! $borrower->isLaboran()) {
            return false;
        }

        $roomIds = $borrower->roomScopes()->pluck('room_id')->map(fn ($id) => (int) $id)->all();

        if ($roomIds === []) {
            return false;
        }

        foreach ($lines as $line) {
            /** @var Item|null $item */
            $item = $items->get((int) $line['item_id']);

            if ($item === null || ! $item->room?->is_lab || ! in_array((int) $item->room_id, $roomIds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Persetujuan berjenjang: level 1 = Laboran, level 2 = Wakasek (barang bernilai tinggi).
     *
     *wakasek (approver level 2) berwenang penuh atas inventaris sekolah. Satu
     * persetujuan yang sah darinya sudah cukup — tidak dituntut persetujuan
     * Admin tambahan yang berulang. Persetujuan dari role lain tetap mengikuti
     * alur berjenjang yang diwajibkan.
     */
    public function decide(Loan $loan, User $approver, Decision $decision, ?string $note = null, ?string $signaturePath = null): Loan
    {
        return DB::transaction(function () use ($loan, $approver, $decision, $note, $signaturePath) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $expectedLevel = $loan->nextApprovalLevelFor($approver);

            if ($expectedLevel === null) {
                throw new \RuntimeException('Peminjaman ini tidak menunggu persetujuan.');
            }

            if ($expectedLevel === 2 && ! $approver->isApproverLevel2()) {
                throw new \RuntimeException('Barang bernilai tinggi memerlukan persetujuan Wakasek Sarpras.');
            }

            // Peminjam tidak boleh menyetujui pengajuannya sendiri. Pengecualian
            // hanya persetujuan melekat Wakasek yang memang dicatat sebagai
            // kewenangan jabatannya, bukan pemalsuan aktor lain.
            if ($approver->id === $loan->borrower_id && ! $approver->isApproverLevel2()) {
                throw new \RuntimeException('Anda tidak dapat menyetujui pengajuan peminjaman milik sendiri.');
            }

            // Laboran/K3 hanya berwenang untuk ruangan laboratorium yang
            // ditugaskan; Ketua Laboran untuk seluruh laboratorium (bukan
            // inventaris nonlaboratorium). Seluruh item harus berada dalam cakupan.
            if ($approver->isLaboran()) {
                $roomIds = $approver->roomScopes()->pluck('room_id')->map(fn ($id) => (int) $id)->all();
                $outside = $loan->loanItems()
                    ->whereHas('item', fn ($q) => $q->whereNotIn('room_id', $roomIds))
                    ->exists();

                if ($roomIds === [] || $outside) {
                    throw new \RuntimeException('Peminjaman ini mencakup barang di luar laboratorium yang ditugaskan kepada Anda.');
                }
            }

            if ($approver->isKetuaLaboran()) {
                $outside = $loan->loanItems()
                    ->whereHas('item.room', fn ($q) => $q->where('is_lab', false))
                    ->exists();

                if ($outside) {
                    throw new \RuntimeException('Peminjaman ini mencakup inventaris nonlaboratorium di luar kewenangan Ketua Laboran.');
                }
            }

            // Pihak berwenang level 2 memutuskan pada level akhir sehingga
            // persetujuannya langsung menutup rantai persetujuan tanpa tahap tambahan.
            $recordedLevel = $approver->isApproverLevel2()
                ? $loan->finalApprovalLevel()
                : $expectedLevel;

            $loan->approvals()->create([
                'approver_id' => $approver->id,
                'level' => $recordedLevel,
                'decision' => $decision->value,
                'note' => $note,
                'signature_path' => $signaturePath,
                'decided_at' => now(),
            ]);

            $borrower = $loan->borrower;

            if ($decision === Decision::REJECTED) {
                $loan->update([
                    'status' => LoanStatus::DITOLAK,
                    'reject_reason' => $note,
                ]);

                NotifyApp::to($borrower, NotificationType::PERSETUJUAN,
                    "Peminjaman \"{$loan->purpose}\" DITOLAK oleh {$approver->name}".($note ? " — {$note}" : ''),
                    route('loans.show', $loan));
            } else {
                $fullyApproved = $loan->isFullyApproved();

                if ($fullyApproved) {
                    $loan->update(['status' => LoanStatus::APPROVED]);

                    NotifyApp::to($borrower, NotificationType::PERSETUJUAN,
                        "Peminjaman \"{$loan->purpose}\" DISETUJUI. Silakan ambil barang di ruangan terkait.",
                        route('loans.show', $loan));

                    if ($loan->event_id) {
                        app(BastService::class)->generateForLoan($loan);
                    }
                } else {
                    NotifyApp::to($borrower, NotificationType::PERSETUJUAN,
                        "Peminjaman \"{$loan->purpose}\" disetujui level {$recordedLevel}, menunggu persetujuan Wakasek.",
                        route('loans.show', $loan));

                    // Level berikutnya (Wakasek) harus menerima notifikasi tindakan.
                    NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PERSETUJUAN,
                        "Persetujuan level 2 diperlukan untuk \"{$loan->purpose}\" (peminjam {$borrower->name}, barang bernilai tinggi).",
                        route('loans.pending'));
                }
            }

            activity('peminjaman')
                ->performedOn($loan)
                ->causedBy($approver)
                ->log(($decision === Decision::APPROVED ? 'Menyetujui' : 'Menolak')." peminjaman level {$recordedLevel}");

            return $loan->refresh();
        });
    }

    /**
     * Serah terima barang: APPROVED -> DIPINJAM.
     * Wajib dilakukan petugas sebelum pengembalian dapat diproses.
     *
     * Pada alur unit fisik, serah terima juga menugaskan unit inventaris yang
     * diserahkan ke peminjam dan mengunci ketersediaannya.
     */
    public function handover(Loan $loan, User $officer, ?array $unitAssignments = null, ?string $note = null): Loan
    {
        return DB::transaction(function () use ($loan, $officer, $unitAssignments, $note) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::APPROVED) {
                throw new \RuntimeException('Hanya peminjaman berstatus Disetujui yang dapat diserahterimakan.');
            }

            // Penjagaan lapis kedua: pengiriman ulang permintaan yang sama
            // tidak boleh menghasilkan penyerahan ganda.
            if ($loan->handed_over_at !== null) {
                throw new \RuntimeException('Barang sudah diserahkan sebelumnya.');
            }

            // Jika penugasan unit disediakan, validasi dan simpan sebelum mengubah status.
            $assignedUnits = $this->assignUnitsOnHandover($loan, $unitAssignments, $officer);

            $loan->update([
                'status' => LoanStatus::DIPINJAM,
                'handed_over_at' => now(),
                'handed_over_by' => $officer->id,
            ]);

            // Beri tahu audit hanya bila ada unit fisik yang ditugaskan.
            if ($assignedUnits !== null) {
                activity('peminjaman')
                    ->performedOn($loan)
                    ->causedBy($officer)
                    ->withProperties([
                        'unit_assignments' => $assignedUnits->map(
                            fn (LoanItemUnit $a) => [
                                'inventory_code' => $a->unit->inventory_code,
                                'unit_id' => $a->asset_unit_id,
                                'condition_out' => $a->condition_out->value,
                            ]
                        )->toArray(),
                    ])
                    ->log('Menyerahkan barang + menugaskan unit fisik kepada peminjam');
            }

            activity('peminjaman')
                ->performedOn($loan)
                ->causedBy($officer)
                ->log('Menyerahkan barang kepada peminjam'.($note ? ": {$note}" : ''));

            NotifyApp::to($loan->borrower, NotificationType::LAINNYA,
                "Barang untuk peminjaman \"{$loan->purpose}\" telah diserahkan. Harap kembalikan sebelum {$loan->due_date->format('d M Y H:i')}.",
                route('loans.show', $loan));

            return $loan->refresh();
        });
    }

    /**
     * Tugaskan unit fisik pada serah terima.
     * Hanya peminjam yang menerima unit yang benar-benar tersedia dan belum dipinjam orang lain.
     *
     * @param  array<int, array{loan_item_id?: int, item_id?: int, unit_ids: list<int>}>|null  $unitAssignments
     * @return Collection<int, LoanItemUnit>|null
     */
    private function assignUnitsOnHandover(Loan $loan, ?array $unitAssignments, ?User $officer = null): ?Collection
    {
        if ($unitAssignments === null || $unitAssignments === []) {
            return null;
        }

        $assigned = new Collection;

        /** @var array<int, array<int, bool>> $assignedUnitIds unit yang ditugaskan per baris peminjaman */
        $assignedUnitIds = [];

        foreach ($unitAssignments as $assignment) {
            $loanItem = $this->resolveLoanItemForAssignment($loan, $assignment);

            if ($loanItem === null) {
                throw new \RuntimeException('Baris peminjaman tidak ditemukan untuk penugasan unit.');
            }

            $unitIds = array_values(array_unique(array_map('intval', (array) ($assignment['unit_ids'] ?? []))));

            if ($unitIds === []) {
                throw new \RuntimeException('Data penugasan unit tidak valid.');
            }

            if ((int) $loanItem->qty_borrowed < count($unitIds)) {
                throw new \RuntimeException(
                    'Jumlah unit yang ditugaskan melebihi jumlah yang dipinjam pada baris ini.'
                );
            }

            // Kunci baris unit agar dua proses serah terima bersamaan tidak
            // dapat menugaskan unit fisik yang sama.
            $units = AssetUnit::visibleTo($officer ?? auth()->user() ?? $loan->borrower)
                ->whereIn('id', $unitIds)
                ->lockForUpdate()
                ->get();

            if ($units->count() !== count($unitIds)) {
                throw new \RuntimeException('Salah satu unit tidak ditemukan atau tidak dapat diakses.');
            }

            // Unit harus benar-benar bagian dari transaksi ini: tidak boleh ada
            // ID unit liar yang dikirim lewat request.
            $loanItemIds = $loan->loanItems()->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (! in_array((int) $loanItem->id, $loanItemIds, true)) {
                throw new \RuntimeException('Baris peminjaman bukan bagian dari transaksi ini.');
            }

            foreach ($units as $unit) {
                // Perbandingan harus eksplisit: `! $x == $y` di PHP berarti
                // `(! $x) == $y` sehingga pengecekan katalog selalu lolos.
                if ((int) $unit->item_catalog_id !== (int) $loanItem->item_id) {
                    throw new \RuntimeException(
                        "Unit {$unit->inventory_code} tidak termasuk katalog barang pada baris ini."
                    );
                }

                if (! $unit->isAvailableForLoan()) {
                    $reason = match (true) {
                        $unit->status !== UnitStatus::AKTIF => "Unit {$unit->inventory_code} tidak aktif.",
                        $unit->condition !== Condition::BAIK => "Unit {$unit->inventory_code} tidak dalam kondisi baik.",
                        $unit->isCurrentlyOnLoan() => "Unit {$unit->inventory_code} sedang dipinjam.",
                        default => "Unit {$unit->inventory_code} tidak dapat dipinjam.",
                    };

                    throw new \RuntimeException($reason);
                }

                // Unit yang sudah pernah kembali pada peminjaman ini tidak boleh
                // ditugaskan ulang.
                $existing = LoanItemUnit::query()
                    ->where('loan_item_id', $loanItem->id)
                    ->where('asset_unit_id', $unit->id)
                    ->first();

                if ($existing !== null && $existing->returned_at !== null) {
                    throw new \RuntimeException(
                        "Unit {$unit->inventory_code} sudah tercatat kembali pada peminjaman ini."
                    );
                }

                // Pastikan unit belum ditugaskan pada peminjaman lain yang masih terbuka.
                $existingOpen = LoanItemUnit::openForUnit($unit);
                if ($existingOpen !== null && $existingOpen->loanItem->loan_id !== $loan->id) {
                    throw new \RuntimeException(
                        "Unit {$unit->inventory_code} sudah ditugaskan pada peminjaman lain dan tidak dapat dipinjam lagi."
                    );
                }

                if ($existingOpen !== null && (int) $existingOpen->loan_item_id !== (int) $loanItem->id) {
                    throw new \RuntimeException(
                        "Unit {$unit->inventory_code} sudah ditugaskan pada baris barang lain dalam peminjaman ini."
                    );
                }

                $loanItemUnit = LoanItemUnit::updateOrCreate(
                    ['loan_item_id' => $loanItem->id, 'asset_unit_id' => $unit->id],
                    [
                        'assigned_at' => now(),
                        'condition_out' => $unit->condition->value,
                        'opened_at' => now(),
                        'closed_at' => null,
                        'returned_at' => null,
                    ]
                );

                $loanItemUnit->markAssigned();

                // Status unit fisik mengikuti posisi riil: sedang dipinjam.
                $unit->forceFill(['status' => UnitStatus::DIPINJAM])->save();

                $assignedUnitIds[(int) $loanItem->id][(int) $unit->id] = true;

                $assigned->push($loanItemUnit);
            }
        }

        $this->assertUnitAssignmentCompleteness($loan, $assignedUnitIds);

        return $assigned;
    }

    /**
     * Pastikan penugasan unit pada serah terima lengkap per baris peminjaman.
     *
     * Penugasan parsial (jumlah unit dipilih lebih sedikit daripada jumlah
     * yang dipinjam) membuat pencatatan unit fisik dan pembukuan kuantitas
     * tidak sinkron: unit yang tidak pernah ditugaskan tetap tercatat
     * "dipinjam" pada baris peminjaman, sementara unit yang ditugaskan bisa
     * saja tidak tercatat.
     *
     * @param  array<int, array<int, bool>>  $assignedUnitIds
     */
    private function assertUnitAssignmentCompleteness(Loan $loan, array $assignedUnitIds): void
    {
        foreach ($loan->loanItems()->with('item')->get() as $loanItem) {
            $required = (int) $loanItem->qty_borrowed;
            $selected = count($assignedUnitIds[(int) $loanItem->id] ?? []);

            if ($selected === $required) {
                continue;
            }

            $item = $loanItem->item;
            $totalUnits = $item?->units()->count() ?? 0;

            // Katalog tanpa unit fisik (data lama) atau yang belum provisioned
            // penuh tetap memakai pembukuan kuantitas warisan.
            if ($totalUnits === 0 || $totalUnits < $required) {
                continue;
            }

            $name = $item?->name ?? 'barang';

            throw new \RuntimeException($selected === 0
                ? "Tidak ada unit yang dapat diserahkan untuk \"{$name}\" — pastikan tersedia unit aktif dalam kondisi baik."
                : "Jumlah unit yang dipilih untuk \"{$name}\" ({$selected}) harus sama dengan jumlah yang dipinjam ({$required}).");
        }
    }

    /**
     * Tentukan baris peminjaman tujuan dari payload penugasan unit.
     * Identitas baris-pinjaman lebih didahulukan daripada katalog karena
     * baris peminjaman sudah terverifikasi sebagai milik transaksi ini.
     *
     * @param  array<string, mixed>  $assignment
     */
    private function resolveLoanItemForAssignment(Loan $loan, array $assignment): ?LoanItem
    {
        $loanItemId = (int) ($assignment['loan_item_id'] ?? 0);
        if ($loanItemId > 0) {
            $loanItem = $loan->loanItems()->whereKey($loanItemId)->first();
            if ($loanItem !== null) {
                return $loanItem;
            }
        }

        $itemId = (int) ($assignment['item_id'] ?? 0);
        if ($itemId > 0) {
            $loanItem = $loan->loanItems()->where('item_id', $itemId)->first();
            if ($loanItem !== null) {
                return $loanItem;
            }
        }

        return null;
    }

    /**
     * Pembatalan oleh peminjam (sebelum diserahkan) atau pengelola.
     * Status DIBATALKAN tidak lagi memblokir ketersediaan.
     */
    public function cancel(Loan $loan, User $actor, ?string $reason = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $reason) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! in_array($loan->status, [LoanStatus::PENDING, LoanStatus::APPROVED], true)) {
                throw new \RuntimeException('Peminjaman ini tidak dapat dibatalkan pada status saat ini.');
            }
            if ($loan->handed_over_at !== null) {
                throw new \RuntimeException('Barang sudah diserahkan — proses melalui pengembalian, bukan pembatalan.');
            }
            if ($loan->borrower_id !== $actor->id && ! $actor->managesInventory()) {
                throw new \RuntimeException('Anda tidak berwenang membatalkan peminjaman ini.');
            }

            $loan->update([
                'status' => LoanStatus::DIBATALKAN,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            activity('peminjaman')
                ->performedOn($loan)
                ->causedBy($actor)
                ->log('Membatalkan peminjaman'.($reason ? ": {$reason}" : ''));

            if ($loan->borrower_id !== $actor->id) {
                NotifyApp::to($loan->borrower, NotificationType::PERSETUJUAN,
                    "Peminjaman \"{$loan->purpose}\" dibatalkan oleh {$actor->name}".($reason ? " — {$reason}" : ''),
                    route('loans.show', $loan));
            } else {
                NotifyApp::toApprovers(NotificationType::PERSETUJUAN,
                    "Peminjaman \"{$loan->purpose}\" dibatalkan oleh peminjam {$actor->name}.",
                    route('loans.show', $loan));
            }

            return $loan->refresh();
        });
    }

    /**
     * Pengembalian tervalidasi: kuantitas akumulatif, kondisi akhir, foto bukti, ruangan tujuan.
     *
     * @param  array<int, array{loan_item_id: int, qty_returned: int, condition_in: string, returned_room_id: int, damage_photo?: UploadedFile}>  $rows
     */
    public function processReturn(Loan $loan, User $returner, array $rows): Loan
    {
        if (app(InventoryCodeService::class)->isConnected() === false) {
            throw new \RuntimeException('Layanan inventaris belum terhubung. Pemrosesan pengembalian tidak dapat dilanjutkan.');
        }

        return DB::transaction(function () use ($loan, $returner, $rows) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                throw ValidationException::withMessages(['rows' => 'Peminjaman tidak dalam status dapat dikembalikan.']);
            }

            $hasShortage = false;
            $hasDamage = false;
            $misplaced = false;
            $seenLoanItemIds = [];
            $seenAssignmentIds = [];

            foreach ($rows as $row) {
                /** @var LoanItem $loanItem */
                $loanItem = LoanItem::with('item.room')->lockForUpdate()->findOrFail($row['loan_item_id']);
                abort_unless($loanItem->loan_id === $loan->id, 403);

                $assignmentId = isset($row['assignment_id']) ? (int) $row['assignment_id'] : null;
                $hasUnitAssignments = $loanItem->hasUnitAssignments();

                if ($hasUnitAssignments && $assignmentId === null) {
                    $qtyForLegacyReturn = (int) $row['qty_returned'];
                    $openAssignmentCount = $loanItem->openUnitAssignments()->count();

                    if ($qtyForLegacyReturn > $openAssignmentCount) {
                        throw ValidationException::withMessages([
                            'rows' => 'Pengembalian per unit wajib mencantumkan identitas unit yang dikembalikan.',
                        ]);
                    }
                }
                if (! $hasUnitAssignments && $assignmentId !== null) {
                    throw ValidationException::withMessages([
                        'rows' => 'Identitas unit tidak cocok dengan baris peminjaman yang dipilih.',
                    ]);
                }
                if ($assignmentId !== null && ! $loanItem->unitAssignments()->whereKey($assignmentId)->exists()) {
                    throw ValidationException::withMessages([
                        'rows' => 'Unit yang dipilih tidak termasuk baris peminjaman ini.',
                    ]);
                }
                if (isset($seenLoanItemIds[$loanItem->id]) && $assignmentId === null) {
                    throw ValidationException::withMessages([
                        'rows' => 'Setiap baris katalog hanya boleh diproses satu kali per pengembalian.',
                    ]);
                }
                if ($assignmentId !== null && isset($seenAssignmentIds[$assignmentId])) {
                    throw ValidationException::withMessages([
                        'rows' => 'Setiap unit fisik hanya boleh diproses satu kali per pengembalian.',
                    ]);
                }
                $seenLoanItemIds[$loanItem->id] = true;
                if ($assignmentId !== null) {
                    $seenAssignmentIds[$assignmentId] = true;
                }

                $qtyNow = (int) $row['qty_returned'];
                if ($hasUnitAssignments && $assignmentId === null && $qtyNow === 0) {
                    continue;
                }
                if ($qtyNow === 0 && $assignmentId !== null) {
                    continue;
                }
                $alreadyReturned = (int) $loanItem->qty_returned;
                $remaining = max(0, $loanItem->qty_borrowed - $alreadyReturned);

                if ($qtyNow < 0 || $qtyNow > $remaining) {
                    throw ValidationException::withMessages([
                        'rows' => "Jumlah kembali untuk \"{$loanItem->item->name}\" melebihi sisa yang belum dikembalikan ({$remaining}).",
                    ]);
                }

                $condition = Condition::from($row['condition_in']);
                $room = Room::findOrFail((int) $row['returned_room_id']);
                $returnNotes = isset($row['return_notes']) ? trim((string) $row['return_notes']) : null;

                if ($returnNotes !== null && mb_strlen($returnNotes) > 1000) {
                    throw ValidationException::withMessages([
                        'rows' => 'Catatan pemeriksaan maksimal 1000 karakter.',
                    ]);
                }

                // Foto bukti wajib jika ada unit yang benar-benar kembali dalam kondisi rusak.
                // Pada alur unit fisik, setiap unit rusak wajib membawa fotonya sendiri;
                // foto unit sebelumnya tidak boleh dianggap sebagai bukti unit berikutnya.
                $hasUploadedPhoto = isset($row['damage_photo'])
                    && $row['damage_photo']
                    && $row['damage_photo']->isValid();
                $damagePath = $loanItem->damage_photo_path;

                if ($qtyNow > 0 && $condition->isDamaged() && $hasUnitAssignments && ! $hasUploadedPhoto) {
                    throw ValidationException::withMessages([
                        'rows' => "Foto bukti kerusakan wajib diunggah untuk setiap unit rusak pada \"{$loanItem->item->name}\".",
                    ]);
                }

                if ($qtyNow > 0 && $condition->isDamaged() && $hasUploadedPhoto) {
                    $damagePath = $this->storeDamageFile($row['damage_photo'], $loan->id);
                }

                if ($qtyNow > 0 && $condition->isDamaged() && ! $damagePath) {
                    throw ValidationException::withMessages([
                        'rows' => "Foto bukti kerusakan wajib diunggah untuk \"{$loanItem->item->name}\".",
                    ]);
                }

                $cumulative = $alreadyReturned + $qtyNow;
                $missing = max(0, $loanItem->qty_borrowed - $cumulative);

                $loanItem->update([
                    'qty_returned' => $cumulative,
                    'qty_lost' => $hasUnitAssignments ? 0 : $missing,
                    'qty_damaged' => $loanItem->qty_damaged + ($qtyNow > 0 && $condition->isDamaged() ? $qtyNow : 0),
                    'condition_in' => $qtyNow > 0 ? $condition->value : $loanItem->condition_in,
                    'damage_photo_path' => $damagePath,
                    'returned_room_id' => $room->id,
                ]);

                $item = $loanItem->item;
                $item->refresh();

                if ($missing > 0 && ! $hasUnitAssignments) {
                    $hasShortage = true;
                }

                if ($qtyNow > 0 && $condition->isDamaged()) {
                    $hasDamage = true;
                    $item->qty_damaged = min(
                        $item->quantity - $item->qty_lost,
                        $item->qty_damaged + $qtyNow
                    );
                    $item->status = $condition === Condition::RUSAK_BERAT
                        ? ItemStatus::RUSAK_BERAT
                        : ItemStatus::RUSAK_RINGAN;
                    $item->save();
                }

                // Barang dikembalikan ke ruangan berbeda → tercatat sebagai mutasi otomatis.
                if ($qtyNow > 0 && $room->id !== $item->room_id) {
                    $misplaced = true;

                    // Pada alur unit fisik, perpindahan dicatat pada unitnya
                    // (AssetUnitMovement) — katalog tidak boleh ikut dipindah
                    // hanya karena satu unit dikembalikan.
                    if (! $hasUnitAssignments) {
                        $this->recordMovement($item, $room, $returner, 'Pengembalian ke ruangan berbeda dari ruangan asal.');
                        $item->refresh();
                    }
                }

                // Tandai hilang hanya bila seluruh unit tidak tersedia; unit hilang sementara
                // tetap dilacak di loan_items.qty_lost sampai pinjaman ditutup.
                if (! $hasUnitAssignments && $missing > 0 && $item->availableQuantity() === 0 && $item->qty_damaged === 0) {
                    $item->update(['status' => ItemStatus::HILANG]);
                } elseif ($missing === 0 && ! $condition->isDamaged() && $item->qty_damaged === 0 && $item->qty_lost === 0) {
                    $item->update(['status' => ItemStatus::BAIK]);
                }

                // Tutup penugasan unit fisik setelah penghitungan katalog agar
                // counter katalog disinkronkan ulang dari kondisi unit sebenarnya.
                if ($qtyNow > 0 && $hasUnitAssignments) {
                    $this->closeUnitAssignmentsForReturn(
                        $loanItem,
                        $qtyNow,
                        $condition,
                        $returner,
                        $row['damage_photo'] ?? null,
                        $room,
                        isset($row['assignment_id']) ? (int) $row['assignment_id'] : null,
                        isset($row['unit_id']) ? (int) $row['unit_id'] : null,
                        $returnNotes
                    );
                }
            }

            $loan->refresh();
            $loanItems = $loan->loanItems()->get();
            $fullyReturned = $loanItems->every(fn ($li) => $li->missingQuantity() === 0);
            $hasShortage = $loanItems->contains(fn (LoanItem $li) => (int) $li->qty_lost > 0);
            $hasUnitAssignments = $loan->loanItemUnitsWithReturned()->exists();
            $openUnitAssignments = $hasUnitAssignments
                ? $loan->loanItemUnitsWithReturned()->whereNull('returned_at')->count()
                : 0;

            // Transaksi berbasis unit tidak boleh berstatus Selesai hanya karena
            // semua baris katalog terlihat penuh: penyelesaian wajib lewat
            // tombol/aksi terpisah yang memeriksa ulang setiap unit.
            if ($fullyReturned && ! $hasUnitAssignments) {
                $loan->update([
                    'status' => LoanStatus::SELESAI,
                    'returned_at' => now(),
                    'returned_by' => $returner->id,
                ]);
            }

            activity('pengembalian')
                ->performedOn($loan)
                ->causedBy($returner)
                ->log('Memproses pengembalian'.($hasShortage ? ' dengan selisih (flag Hilang)' : '').($hasDamage ? ' dengan kerusakan' : '').($misplaced ? ' ke ruangan berbeda' : ''));

            $notificationMessage = match (true) {
                $fullyReturned && ! $hasUnitAssignments => "Pengembalian \"{$loan->purpose}\" selesai. Terima kasih.",
                $hasUnitAssignments && $openUnitAssignments === 0 => "Pengembalian \"{$loan->purpose}\" diproses. Seluruh unit sudah dikonfirmasi — selesaikan dari form pengembalian.",
                $hasUnitAssignments => "Pengembalian \"{$loan->purpose}\" diproses; masih ada {$openUnitAssignments} unit yang belum dikonfirmasi.",
                default => "Pengembalian \"{$loan->purpose}\" diproses, TERDAPAT SELISIH kuantitas — menunggu tindak lanjut admin.",
            };

            NotifyApp::to($loan->borrower, NotificationType::PENGEMBALIAN,
                $notificationMessage,
                route('loans.show', $loan));

            if ($hasShortage) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'KETUA_LABORAN'], NotificationType::PENGEMBALIAN,
                    "Selisih kuantitas pada peminjaman \"{$loan->purpose}\" (peminjam: {$loan->borrower->name}).",
                    route('loans.show', $loan));
            }

            if ($hasDamage) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'KETUA_LABORAN'], NotificationType::PENGEMBALIAN,
                    "Barang rusak dikembalikan pada peminjaman \"{$loan->purpose}\" — periksa foto bukti dan kondisi unit.",
                    route('loans.show', $loan));
            }

            if ($misplaced) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'KETUA_LABORAN'], NotificationType::LAINNYA,
                    "Barang dari peminjaman \"{$loan->purpose}\" dikembalikan ke ruangan berbeda — periksa mutasi lokasi.",
                    route('movements.index'));
            }

            return $loan->refresh();
        });
    }

    /**
     * Selesaikan pengembalian setelah seluruh unit terkonfirmasi.
     *
     * Frontend hanya menonaktifkan tombol; keputusan akhir tetap dihitung
     * ulang dari database di dalam transaksi dan baris pinjaman dikunci agar
     * dua petugas tidak menyelesaikan transaksi yang sama secara bersamaan.
     */
    public function completeReturn(Loan $loan, User $officer): Loan
    {
        if (! $officer->canApproveLoans()) {
            throw new \RuntimeException('Anda tidak berwenang menyelesaikan pengembalian.');
        }

        return DB::transaction(function () use ($loan, $officer) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                throw new \RuntimeException('Peminjaman tidak dalam status dapat diselesaikan.');
            }

            $items = $loan->loanItems()->with(['item', 'unitAssignments.unit'])->get();

            $total = 0;
            $confirmed = 0;
            $pending = [];

            foreach ($items as $loanItem) {
                $borrowed = (int) $loanItem->qty_borrowed;
                $assignments = $loanItem->unitAssignments;

                if ($assignments->isNotEmpty()) {
                    $total += max($borrowed, $assignments->count());

                    foreach ($assignments as $assignment) {
                        if ($assignment->isConfirmed()) {
                            $confirmed++;

                            continue;
                        }

                        $pending[] = $assignment->unit?->inventory_code
                            ?? $loanItem->item?->name
                            ?? 'Unit';
                    }

                    $unassigned = $borrowed - $assignments->count();
                    for ($i = 0; $i < max(0, $unassigned); $i++) {
                        $pending[] = ($loanItem->item?->name ?? 'Barang').' (belum ada unit)';
                    }

                    continue;
                }

                $returned = min($borrowed, (int) $loanItem->qty_returned);
                $total += $borrowed;
                $confirmed += $returned;

                if ($borrowed > $returned) {
                    $pending[] = $loanItem->item?->name ?? 'Baris peminjaman';
                }
            }

            if ($pending !== []) {
                $sample = implode(', ', array_slice($pending, 0, 5));
                $more = count($pending) > 5 ? ' dan '.(count($pending) - 5).' lainnya' : '';

                throw ValidationException::withMessages([
                    'finish' => "Pengembalian belum dapat diselesaikan: {$confirmed} dari {$total} unit sudah dikonfirmasi. "
                        ."Unit yang belum selesai: {$sample}{$more}.",
                ]);
            }

            if ($total === 0) {
                throw ValidationException::withMessages([
                    'finish' => 'Tidak ada unit yang dapat diselesaikan pada peminjaman ini.',
                ]);
            }

            $loan->update([
                'status' => LoanStatus::SELESAI,
                'returned_at' => now(),
                'returned_by' => $officer->id,
            ]);

            activity('pengembalian')
                ->performedOn($loan)
                ->causedBy($officer)
                ->withProperties([
                    'total_units' => $total,
                    'confirmed_units' => $confirmed,
                ])
                ->log('Menyelesaikan pengembalian setelah seluruh unit dikonfirmasi');

            NotifyApp::to($loan->borrower, NotificationType::PENGEMBALIAN,
                "Pengembalian \"{$loan->purpose}\" selesai. Terima kasih.",
                route('loans.show', $loan));

            return $loan->refresh();
        });
    }

    /**
     * Ubah status dokumen peminjaman (surat peminjaman / BAST).
     *
     * Status dokumen dipisahkan dari status transaksi: dokumen yang sudah
     * dicetak atau ditandatangani tidak membuat transaksi menjadi disetujui,
     * dan transaksi yang sudah disetujui tidak terblokir hanya karena
     * dokumen belum ditandatangani.
     */
    public function markDocumentStatus(Loan $loan, User $actor, DocumentStatus $status): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $status) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->document_status === $status) {
                return $loan;
            }

            if ($status === DocumentStatus::DITANDATANGANI_DIGITAL
                && ! $loan->approvals()->whereNotNull('signature_path')->exists()) {
                throw new \RuntimeException(
                    'Tidak ada tanda tangan digital pada persetujuan ini. Status dokumen tidak dapat ditandai ditandatangani digital.'
                );
            }

            if (in_array($status, [DocumentStatus::DICETAK, DocumentStatus::DITANDATANGANI_FISIK], true)
                && $loan->bast_path === null) {
                throw new \RuntimeException(
                    'Dokumen belum dibuat. Buat/unduh surat peminjaman terlebih dahulu sebelum mencatat dicetak atau ditandatangani.'
                );
            }

            $loan->update(['document_status' => $status]);

            activity('dokumen')
                ->performedOn($loan)
                ->causedBy($actor)
                ->log('Status dokumen peminjaman diubah menjadi '.$status->label());

            return $loan->refresh();
        });
    }

    /**
     * Tutup pinjaman dengan selisih: selisih dipindahkan menjadi kerugian permanen
     * pada item (qty_lost) dan audit trail dipertahankan.
     */
    public function closeWithShortage(Loan $loan, User $approver): Loan
    {
        return DB::transaction(function () use ($loan, $approver) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                throw new \RuntimeException('Peminjaman tidak aktif.');
            }

            $items = $loan->loanItems()->with('item')->get();
            if ($items->every(fn ($li) => $li->missingQuantity() === 0)) {
                throw new \RuntimeException('Tidak ada selisih pada pinjaman ini.');
            }

            foreach ($items as $loanItem) {
                $missing = $loanItem->missingQuantity();
                if ($missing <= 0) {
                    continue;
                }

                $loanItem->update(['qty_lost' => $missing]);

                $item = $loanItem->item;
                $item->qty_lost = min($item->quantity - $item->qty_damaged, $item->qty_lost + $missing);
                if ($item->availableQuantity() === 0) {
                    $item->status = ItemStatus::HILANG;
                }
                $item->save();

                activity('pengembalian')
                    ->performedOn($item)
                    ->causedBy($approver)
                    ->log("Menetapkan {$missing} {$item->unit} sebagai hilang dari pinjaman #{$loan->id}");
            }

            $loan->update([
                'status' => LoanStatus::SELESAI,
                'returned_at' => $loan->returned_at ?? now(),
                'returned_by' => $loan->returned_by ?? $approver->id,
            ]);

            activity('pengembalian')
                ->performedOn($loan)
                ->causedBy($approver)
                ->log('Menutup pinjaman dengan selisih barang (flag Hilang permanen)');

            return $loan->refresh();
        });
    }

    /**
     * Tutup penugasan unit fisik untuk unit yang dikembalikan.
     *
     * Bila `assignmentId` diberikan, penugasan yang ditutup adalah unit yang
     * benar-benar dipilih petugas pada baris tersebut — bukan unit dengan id
     * terbesar. Tanpa `assignmentId` (alur kuantitas lama), penugasan terbuka
     * ditutup sebanyak jumlah yang dikembalikan.
     *
     * Selisih (unit tidak dikembalikan) ditangani di level loan_items agar
     * transisi dengan penugasan per unit tetap konsisten.
     */
    private function closeUnitAssignmentsForReturn(
        LoanItem $loanItem,
        int $qtyReturnedNow,
        Condition $conditionIn,
        ?User $returner,
        ?UploadedFile $damagePhoto,
        ?Room $returnedRoom = null,
        ?int $assignmentId = null,
        ?int $unitId = null,
        ?string $returnNotes = null
    ): void {
        if ($qtyReturnedNow <= 0) {
            return;
        }

        $openAssignments = $loanItem->openUnitAssignments()->orderBy('id')->lockForUpdate()->get();

        if ($openAssignments->isEmpty()) {
            return;
        }

        // Tanpa identitas unit yang dipilih (alur kuantitas lama), penugasan
        // yang ditutup hanya sebanyak unit yang benar-benar dikembalikan —
        // bukan seluruh penugasan terbuka.
        $targets = $openAssignments->take($qtyReturnedNow);

        if ($assignmentId !== null) {
            $target = $openAssignments->firstWhere('id', $assignmentId);

            if ($target === null) {
                throw ValidationException::withMessages([
                    'rows' => 'Unit yang dipilih sudah tercatat kembali sebelumnya atau tidak berada pada peminjaman ini.',
                ]);
            }

            if ($unitId !== null && (int) $target->asset_unit_id !== $unitId) {
                throw ValidationException::withMessages([
                    'rows' => 'Kode inventaris yang dipilih tidak cocok dengan penugasan unit pada peminjaman ini.',
                ]);
            }

            $targets = collect([$target]);
        }

        $closedAny = false;

        $targets->each(function (LoanItemUnit $assignment) use ($conditionIn, $returner, $loanItem, $returnedRoom, $returnNotes, &$closedAny) {
            if ($assignment->returned_at !== null) {
                return;
            }

            $assignment->markReturned($conditionIn, $returner, $returnNotes);
            $closedAny = true;

            $unit = $assignment->unit;

            if ($unit !== null) {
                // Kondisi & status unit mengikuti hasil pengembalian supaya unit
                // rusak tidak lagi dianggap dapat dipinjam.
                $unit->forceFill([
                    'condition' => $conditionIn,
                    'status' => UnitStatus::AKTIF,
                ])->save();

                // Unit yang kembali ke ruangan lain harus berpindah lokasi secara
                // resmi (kode inventaris ikut diterbitkan ulang).
                if ($returnedRoom !== null && (int) $unit->room_id !== (int) $returnedRoom->id) {
                    $unit->forceFill(['status' => UnitStatus::AKTIF])->save();

                    app(InventoryCodeService::class)->move(
                        $unit->fresh(),
                        $returnedRoom,
                        $returner ?? $loanItem->loan?->borrower ?? auth()->user(),
                        'Dikembalikan pada ruangan berbeda dari ruangan asal.'
                    );
                }

                app(AssetUnitService::class)->syncCatalogCounters($unit->catalog);
            }

            if ($returner !== null) {
                activity('pengembalian')
                    ->performedOn($assignment->unit)
                    ->causedBy($returner)
                    ->withProperties([
                        'loan_item_id' => $loanItem->id,
                        'loan_item_unit_id' => $assignment->id,
                        'condition_in' => $conditionIn->value,
                        'return_notes' => $returnNotes,
                        'inventory_code' => $assignment->unit?->inventory_code,
                    ])
                    ->log($conditionIn->isDamaged()
                        ? 'Mencatat pengembalian unit dengan kondisi rusak'
                        : 'Mencatat pengembalian unit');
            }
        });

        if (! $closedAny) {
            throw ValidationException::withMessages([
                'rows' => 'Tidak ada unit yang ditugaskan dapat ditutup untuk pengembalian ini. Pastikan unit sudah ditugaskan pada serah terima.',
            ]);
        }
    }

    /** Simpan foto bukti kerusakan (mendukung upload biasa & upload sementara Livewire). */
    private function storeDamageFile($file, int $loanId): string
    {
        if ($file instanceof TemporaryUploadedFile) {
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $ext = strtolower(str_replace('+xml', '', explode('/', $mime)[1] ?? 'jpg'));
            $path = "damage/loan-{$loanId}/".Str::random(40).'.'.$ext;
            \Storage::disk('local')->put($path, $file->get());

            return $path;
        }

        return $file->store("damage/loan-{$loanId}", 'local');
    }

    /** Catat mutasi lokasi + perbarui ruangan & ID Internal barang. */
    public function recordMovement(Item $item, Room $toRoom, User $actor, ?string $note = null, int $quantity = 1): void
    {
        $fromRoom = $item->room;

        ItemMovement::create([
            'item_id' => $item->id,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'moved_by' => $actor->id,
            'quantity' => $quantity,
            'note' => $note,
            'moved_at' => now(),
        ]);

        $item->update(['room_id' => $toRoom->id]);
        IdGenerator::refreshInternalId($item);

        activity('mutasi')
            ->performedOn($item)
            ->causedBy($actor)
            ->log("Memindahkan {$item->name} dari {$fromRoom->code} ke {$toRoom->code}");
    }

    /**
     * Gabungkan baris duplikat untuk item yang sama agar validasi tidak dapat dilewati.
     *
     * @param  array<int, array{item_id: int, qty: int}>  $lines
     * @return array<int, array{item_id: int, qty: int}>
     */
    private function mergeLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $id = (int) ($line['item_id'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);

            if ($id <= 0 || $qty <= 0) {
                throw new AvailabilityException('Data keranjang tidak valid.');
            }

            if (array_key_exists($id, $merged)) {
                throw new AvailabilityException('Katalog yang sama tidak boleh dipilih lebih dari sekali dalam satu pengajuan.');
            }

            $merged[$id] = ['item_id' => $id, 'qty' => $qty];
        }

        return array_values($merged);
    }
}
