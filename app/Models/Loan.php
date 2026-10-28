<?php

namespace App\Models;

use App\Enums\Decision;
use App\Enums\DocumentStatus;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrower_id', 'event_id', 'purpose', 'start_date', 'due_date',
        'returned_at', 'returned_by', 'status', 'needs_high_value_approval',
        'handed_over_at', 'handed_over_by', 'cancelled_at', 'cancelled_by',
        'cancel_reason', 'bast_path', 'letter_path', 'reject_reason',
        'document_status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'due_date' => 'datetime',
            'returned_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => LoanStatus::class,
            'needs_high_value_approval' => 'boolean',
            'document_status' => DocumentStatus::class,
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    /** Satu baris peminjaman dapat memiliki beberapa unit fisik yang ditugaskan. */
    public function loanItemUnits(): HasManyThrough
    {
        return $this->hasManyThrough(LoanItemUnit::class, LoanItem::class)
            ->whereNull('loan_item_units.returned_at');
    }

    public function loanItemUnitsWithReturned(): HasManyThrough
    {
        return $this->hasManyThrough(LoanItemUnit::class, LoanItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LoanApproval::class);
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function handoverOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Batasi query ke data yang boleh dilihat pengguna (mencegah IDOR).
     * Wakasek melihat semua; Ketua Laboran hanya transaksi yang seluruh
     * barangnya berada di laboratorium; Laboran/K3 hanya ruangan yang
     * ditugaskan. Peminjam hanya transaksi miliknya sendiri.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isKetuaLaboran()) {
            return $query->where(function ($q) {
                $q->whereDoesntHave('loanItems.item.room', fn ($room) => $room->where('is_lab', false))
                    ->whereHas('loanItems');
            });
        }

        if ($user->isLaboran()) {
            $roomIds = $user->roomScopes()->select('room_id');

            return $query->where(function ($q) use ($user, $roomIds) {
                $q->where('borrower_id', $user->id)
                    ->orWhere(function ($scoped) use ($roomIds) {
                        $scoped->whereHas('loanItems')
                            ->whereDoesntHave('loanItems.item', fn ($item) => $item->whereNotIn('room_id', $roomIds));
                    });
            });
        }

        return $query->where('borrower_id', $user->id);
    }

    /**
     * Boleh memproses serah terima/pengembalian? Berbeda dari canBeViewedBy():
     * peminjam boleh melihat transaksinya sendiri, tetapi tidak boleh
     * memvalidasi pengembalian.
     */
    public function canBeManagedBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $items = $this->loanItems()->with('item.room')->get();

        if ($items->isEmpty()) {
            return false;
        }

        if ($user->isKetuaLaboran()) {
            return $items->every(fn (LoanItem $line) => (bool) $line->item?->room?->is_lab);
        }

        if ($user->isLaboran()) {
            $roomIds = $user->roomScopes()->pluck('room_id')->map(fn ($id) => (int) $id)->all();

            return $roomIds !== [] && $items->every(
                fn (LoanItem $line) => in_array((int) $line->item?->room_id, $roomIds, true)
            );
        }

        return false;
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($user->isSuperAdmin() || $this->borrower_id === $user->id) {
            return true;
        }

        $items = $this->loanItems()->with('item.room')->get();

        if ($items->isEmpty()) {
            return false;
        }

        if ($user->isKetuaLaboran()) {
            return $items->every(fn (LoanItem $line) => (bool) $line->item?->room?->is_lab);
        }

        if ($user->isLaboran()) {
            $roomIds = $user->roomScopes()->pluck('room_id')->map(fn ($id) => (int) $id)->all();

            return $items->every(fn (LoanItem $line) => in_array((int) $line->item?->room_id, $roomIds, true));
        }

        return false;
    }

    /** Unit fisik yang sedang dipinjam dalam transaksi ini (hanya yang terbuka). */
    public function openLoanItemUnits(): HasManyThrough
    {
        return $this->hasManyThrough(LoanItemUnit::class, LoanItem::class)
            ->whereNull('loan_item_units.returned_at');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true);
    }

    /**
     * Progres validasi pengembalian per unit. Bila transaksi memiliki
     * penugasan unit fisik, yang dihitung adalah unit; baris legacy tanpa
     * penugasan dihitung dari kuantitas katalog.
     *
     * @return array{total: int, confirmed: int, pending: int, percent: int, complete: bool, unit_based: bool, pending_labels: list<string>}
     */
    public function returnProgress(): array
    {
        $items = $this->relationLoaded('loanItems')
            ? $this->loanItems
            : $this->loanItems()->get();

        $items->loadMissing(['item', 'unitAssignments.unit']);

        $total = 0;
        $confirmed = 0;
        $unitBased = false;
        $pendingLabels = [];

        foreach ($items as $loanItem) {
            if ($loanItem->unitAssignments->isNotEmpty()) {
                $unitBased = true;
                $assigned = $loanItem->unitAssignments->count();
                $total += max((int) $loanItem->qty_borrowed, $assigned);

                foreach ($loanItem->unitAssignments as $assignment) {
                    if ($assignment->isConfirmed()) {
                        $confirmed++;

                        continue;
                    }

                    $pendingLabels[] = $assignment->unit?->inventory_code
                        ?? $loanItem->item?->name
                        ?? 'Unit';
                }

                // Penugasan yang tidak lengkap tidak boleh dianggap selesai.
                $unassigned = (int) $loanItem->qty_borrowed - $assigned;
                for ($i = 0; $i < max(0, $unassigned); $i++) {
                    $pendingLabels[] = ($loanItem->item?->name ?? 'Barang').' (belum ada unit)';
                }

                continue;
            }

            $borrowed = (int) $loanItem->qty_borrowed;
            $returned = min($borrowed, (int) $loanItem->qty_returned);
            $total += $borrowed;
            $confirmed += $returned;

            if ($borrowed > $returned) {
                $pendingLabels[] = $loanItem->item?->name ?? 'Baris peminjaman';
            }
        }

        $pending = max(0, $total - $confirmed);

        return [
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'percent' => $total > 0 ? (int) round(($confirmed / $total) * 100) : 0,
            'complete' => $total > 0 && $pending === 0,
            'unit_based' => $unitBased,
            'pending_labels' => $pendingLabels,
        ];
    }

    public function canCompleteReturn(): bool
    {
        return $this->isActive() && $this->returnProgress()['complete'];
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true)
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === LoanStatus::PENDING;
    }

    public function hasLevel2Approval(): bool
    {
        return $this->approvals()->where('level', 2)->where('decision', Decision::APPROVED->value)->exists();
    }

    public function requiresLevel2(): bool
    {
        return $this->needs_high_value_approval;
    }

    /**
     * Level persetujuan terakhir yang diwajibkan transaksi ini.
     * 2 = barang bernilai tinggi (perlu persetujuan Wakasek), 1 = cukup satu persetujuan.
     */
    public function finalApprovalLevel(): int
    {
        return $this->requiresLevel2() ? 2 : 1;
    }

    public function approvalLevel(): int
    {
        return $this->finalApprovalLevel();
    }

    /**
     * Apakah seluruh persetujuan yang diwajibkan sudah terpenuhi?
     *
     * Persetujuan pada level akhir (2 untuk barang bernilai tinggi) sekaligus
     * menutup level di bawahnya: pihak berwenang yang memberi persetujuan
     * terakhir tidak perlu menandatangani persetujuan tambahan yang berulang.
     */
    public function isFullyApproved(): bool
    {
        $final = $this->finalApprovalLevel();

        return $this->approvals()
            ->where('decision', Decision::APPROVED->value)
            ->where('level', $final)
            ->exists();
    }

    /**
     * Level keputusan berikutnya untuk pelaku tertentu (null = tidak ada yang perlu diputuskan).
     *
     *wakasek (approver level 2) berwenang memutuskan pada level akhir, sehingga
     * persetujuannya langsung menutup rangkaian persetujuan tanpa tahap tambahan.
     */
    public function nextApprovalLevelFor(User $approver): ?int
    {
        if ($this->status !== LoanStatus::PENDING || $this->isFullyApproved()) {
            return null;
        }

        if ($approver->isApproverLevel2()) {
            return $this->finalApprovalLevel();
        }

        return $this->nextApprovalLevel();
    }

    /**
     * Level berikutnya yang harus diputuskan (null = tidak ada).
     *
     * Untuk peminjam biasa, level 1 (endorsemen laboran) selalu didahulukan;
     * level 2 hanya menyusul bila persetujuan level 1 sudah diberikan.
     */
    public function nextApprovalLevel(): ?int
    {
        if ($this->status !== LoanStatus::PENDING || $this->isFullyApproved()) {
            return null;
        }

        if (! $this->approvals()->where('level', 1)->where('decision', Decision::APPROVED->value)->exists()) {
            return 1;
        }

        return $this->finalApprovalLevel();
    }

    /** Alasan singkat kenapa transaksi belum bisa diserahkan, null bila sudah siap. */
    public function handoverBlocker(): ?string
    {
        if ($this->status === LoanStatus::DIPINJAM) {
            return 'Barang sudah diserahkan sebelumnya.';
        }

        if ($this->status !== LoanStatus::APPROVED) {
            return match ($this->status) {
                LoanStatus::PENDING => 'Peminjaman masih menunggu persetujuan yang diwajibkan.',
                LoanStatus::DITOLAK => 'Peminjaman sudah ditolak.',
                LoanStatus::DIBATALKAN => 'Peminjaman sudah dibatalkan.',
                LoanStatus::SELESAI => 'Peminjaman sudah selesai.',
                default => 'Peminjaman tidak berstatus Disetujui.',
            };
        }

        if ($this->handed_over_at !== null) {
            return 'Barang sudah diserahkan sebelumnya.';
        }

        if (! $this->isFullyApproved()) {
            return 'Persetujuan yang diwajibkan belum lengkap.';
        }

        return null;
    }
}
