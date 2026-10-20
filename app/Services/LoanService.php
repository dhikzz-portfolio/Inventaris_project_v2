<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\NotificationType;
use App\Exceptions\AvailabilityException;
use App\Models\Item;
use App\Models\ItemMovement;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Room;
use App\Models\User;
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

            $hasHighValue = Item::whereIn('id', array_column($merged, 'item_id'))
                ->where('is_high_value', true)
                ->exists();

            $loan = Loan::create([
                'borrower_id' => $borrower->id,
                'event_id' => $eventId,
                'purpose' => $purpose,
                'start_date' => $start,
                'due_date' => $due,
                'status' => LoanStatus::PENDING,
                'needs_high_value_approval' => $hasHighValue,
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

            activity('peminjaman')
                ->performedOn($loan)
                ->causedBy($borrower)
                ->log('Mengajukan peminjaman '.count($merged).' barang');

            NotifyApp::toApprovers(
                NotificationType::PERSETUJUAN,
                "Request peminjaman baru dari {$borrower->name}: \"{$purpose}\"",
                route('loans.show', $loan)
            );

            return $loan;
        });
    }

    /**
     * Persetujuan berjenjang: level 1 = Laboran, level 2 = Wakasek (barang bernilai tinggi).
     */
    public function decide(Loan $loan, User $approver, Decision $decision, ?string $note = null, ?string $signaturePath = null): Loan
    {
        return DB::transaction(function () use ($loan, $approver, $decision, $note, $signaturePath) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $expectedLevel = $loan->nextApprovalLevel();

            if ($expectedLevel === null) {
                throw new \RuntimeException('Peminjaman ini tidak menunggu persetujuan.');
            }

            if ($expectedLevel === 2 && ! $approver->isApproverLevel2()) {
                throw new \RuntimeException('Barang bernilai tinggi memerlukan persetujuan Wakasek Sarpras.');
            }

            // Laboran hanya berwenang untuk unit di jurusannya — seluruh item harus berada dalam cakupan.
            if ($approver->isLaboran() && $approver->major_id) {
                $outside = $loan->loanItems()
                    ->whereHas('item.room', fn ($q) => $q->where('major_id', '!=', $approver->major_id)->orWhereNull('major_id'))
                    ->exists();

                if ($outside) {
                    throw new \RuntimeException('Peminjaman ini mencakup barang di luar jurusan Anda.');
                }
            }

            $loan->approvals()->create([
                'approver_id' => $approver->id,
                'level' => $expectedLevel,
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
                $fullyApproved = $loan->nextApprovalLevel() === null;

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
                        "Peminjaman \"{$loan->purpose}\" disetujui level {$expectedLevel}, menunggu persetujuan Wakasek.",
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
                ->log(($decision === Decision::APPROVED ? 'Menyetujui' : 'Menolak')." peminjaman level {$expectedLevel}");

            return $loan->refresh();
        });
    }

    /**
     * Serah terima barang: APPROVED -> DIPINJAM.
     * Wajib dilakukan petugas sebelum pengembalian dapat diproses.
     */
    public function handover(Loan $loan, User $officer, ?string $note = null): Loan
    {
        return DB::transaction(function () use ($loan, $officer, $note) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::APPROVED) {
                throw new \RuntimeException('Hanya peminjaman berstatus Disetujui yang dapat diserahterimakan.');
            }

            $loan->update([
                'status' => LoanStatus::DIPINJAM,
                'handed_over_at' => now(),
                'handed_over_by' => $officer->id,
            ]);

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
        return DB::transaction(function () use ($loan, $returner, $rows) {
            /** @var Loan $loan */
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if (! $loan->isActive()) {
                throw ValidationException::withMessages(['rows' => 'Peminjaman tidak dalam status dapat dikembalikan.']);
            }

            $hasShortage = false;
            $hasDamage = false;
            $misplaced = false;

            foreach ($rows as $row) {
                /** @var LoanItem $loanItem */
                $loanItem = LoanItem::with('item.room')->lockForUpdate()->findOrFail($row['loan_item_id']);
                abort_unless($loanItem->loan_id === $loan->id, 403);

                $qtyNow = (int) $row['qty_returned'];
                $alreadyReturned = (int) $loanItem->qty_returned;
                $remaining = max(0, $loanItem->qty_borrowed - $alreadyReturned);

                if ($qtyNow < 0 || $qtyNow > $remaining) {
                    throw ValidationException::withMessages([
                        'rows' => "Jumlah kembali untuk \"{$loanItem->item->name}\" melebihi sisa yang belum dikembalikan ({$remaining}).",
                    ]);
                }

                $condition = Condition::from($row['condition_in']);
                $room = Room::findOrFail((int) $row['returned_room_id']);

                // Foto bukti wajib jika ada unit yang benar-benar kembali dalam kondisi rusak.
                $damagePath = $loanItem->damage_photo_path;
                if ($qtyNow > 0 && $condition->isDamaged() && isset($row['damage_photo']) && $row['damage_photo'] && $row['damage_photo']->isValid()) {
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
                    'qty_lost' => $missing,
                    'qty_damaged' => $loanItem->qty_damaged + ($qtyNow > 0 && $condition->isDamaged() ? $qtyNow : 0),
                    'condition_in' => $qtyNow > 0 ? $condition->value : $loanItem->condition_in,
                    'damage_photo_path' => $damagePath,
                    'returned_room_id' => $room->id,
                ]);

                $item = $loanItem->item;
                $item->refresh();

                if ($missing > 0) {
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
                    $this->recordMovement($item, $room, $returner, 'Pengembalian ke ruangan berbeda dari ruangan asal.');
                    $item->refresh();
                }

                // Tandai hilang hanya bila seluruh unit tidak tersedia; unit hilang sementara
                // tetap dilacak di loan_items.qty_lost sampai pinjaman ditutup.
                if ($missing > 0 && $item->availableQuantity() === 0 && $item->qty_damaged === 0) {
                    $item->update(['status' => ItemStatus::HILANG]);
                } elseif ($missing === 0 && ! $condition->isDamaged() && $item->qty_damaged === 0 && $item->qty_lost === 0) {
                    $item->update(['status' => ItemStatus::BAIK]);
                }
            }

            $loan->refresh();
            $fullyReturned = $loan->loanItems()->get()->every(fn ($li) => $li->missingQuantity() === 0);

            if ($fullyReturned) {
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

            NotifyApp::to($loan->borrower, NotificationType::PENGEMBALIAN,
                $fullyReturned
                    ? "Pengembalian \"{$loan->purpose}\" selesai. Terima kasih."
                    : "Pengembalian \"{$loan->purpose}\" diproses, TERDAPAT SELISIH kuantitas — menunggu tindak lanjut admin.",
                route('loans.show', $loan));

            if ($hasShortage) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'ADMIN_SARPRAS'], NotificationType::PENGEMBALIAN,
                    "Selisih kuantitas pada peminjaman \"{$loan->purpose}\" (peminjam: {$loan->borrower->name}).",
                    route('loans.show', $loan));
            }

            if ($hasDamage) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'ADMIN_SARPRAS'], NotificationType::PENGEMBALIAN,
                    "Barang rusak dikembalikan pada peminjaman \"{$loan->purpose}\" — periksa foto bukti dan kondisi unit.",
                    route('loans.show', $loan));
            }

            if ($misplaced) {
                NotifyApp::toRoles(['SUPER_ADMIN', 'ADMIN_SARPRAS'], NotificationType::LAINNYA,
                    "Barang dari peminjaman \"{$loan->purpose}\" dikembalikan ke ruangan berbeda — periksa mutasi lokasi.",
                    route('movements.index'));
            }

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
            $merged[$id] = ['item_id' => $id, 'qty' => ($merged[$id]['qty'] ?? 0) + $qty];
        }

        return array_values($merged);
    }
}
