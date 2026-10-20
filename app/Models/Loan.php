<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrower_id', 'event_id', 'purpose', 'start_date', 'due_date',
        'returned_at', 'returned_by', 'status', 'needs_high_value_approval',
        'handed_over_at', 'handed_over_by', 'cancelled_at', 'cancelled_by',
        'cancel_reason', 'bast_path', 'letter_path', 'reject_reason',
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
     * Laboran hanya melihat pinjaman yang menyentuh jurusannya.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->managesInventory()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('borrower_id', $user->id);

            if ($user->isLaboran() && $user->major_id) {
                $q->orWhereHas('loanItems.item.room', fn ($rq) => $rq->where('major_id', $user->major_id));
            }
        });
    }

    public function canBeViewedBy(User $user): bool
    {
        if ($user->managesInventory() || $this->borrower_id === $user->id) {
            return true;
        }

        return $user->isLaboran()
            && $user->major_id !== null
            && $this->loanItems()->whereHas('item.room', fn ($q) => $q->where('major_id', $user->major_id))->exists();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true);
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
        return $this->approvals()->where('level', 2)->where('decision', 'APPROVED')->exists();
    }

    public function requiresLevel2(): bool
    {
        return $this->needs_high_value_approval;
    }

    public function approvalLevel(): int
    {
        return $this->requiresLevel2() ? 2 : 1;
    }

    /** Level berikutnya yang harus memutuskan (null = tidak ada). */
    public function nextApprovalLevel(): ?int
    {
        if ($this->status !== LoanStatus::PENDING) {
            return null;
        }
        if (! $this->approvals()->where('level', 1)->where('decision', 'APPROVED')->exists()) {
            return 1;
        }
        if ($this->requiresLevel2() && ! $this->hasLevel2Approval()) {
            return 2;
        }

        return null;
    }
}
