<?php

namespace App\Models;

use App\Enums\Condition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanItemUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_item_id',
        'asset_unit_id',
        'assigned_at',
        'returned_at',
        'condition_out',
        'condition_in',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
            'condition_out' => Condition::class,
            'condition_in' => Condition::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }

    public function isOpenForAssignment(): bool
    {
        return $this->returned_at === null && $this->opened_at !== null;
    }

    public function markAssigned(): void
    {
        $this->fill([
            'assigned_at' => $this->assigned_at ?? now(),
            'opened_at' => now(),
            'condition_out' => $this->condition_out ?? Condition::BAIK,
        ])->save();
    }

    public function markReturned(Condition $conditionIn, ?User $returner = null): void
    {
        $this->fill([
            'returned_at' => now(),
            'condition_in' => $conditionIn,
            'closed_at' => now(),
        ])->save();
    }

    /** Unit yang masih terbuka (belum dikembalikan) di antara penugasan ini. */
    public static function openForUnit(AssetUnit $unit): ?self
    {
        return static::where('asset_unit_id', $unit->id)
            ->whereNull('returned_at')
            ->first();
    }
}
