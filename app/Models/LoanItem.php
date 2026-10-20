<?php

namespace App\Models;

use App\Enums\Condition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanItem extends Model
{
    protected $fillable = [
        'loan_id', 'item_id', 'qty_borrowed', 'qty_returned', 'qty_damaged', 'qty_lost',
        'condition_out', 'condition_in', 'damage_photo_path', 'returned_room_id',
    ];

    protected function casts(): array
    {
        return [
            'qty_borrowed' => 'integer',
            'qty_returned' => 'integer',
            'qty_damaged' => 'integer',
            'qty_lost' => 'integer',
            'condition_out' => Condition::class,
            'condition_in' => Condition::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returnedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'returned_room_id');
    }

    public function isReturned(): bool
    {
        return $this->qty_returned !== null;
    }

    /** Sisa unit yang belum kembali (termasuk yang sementara ditandai kurang). */
    public function missingQuantity(): int
    {
        return max(0, $this->qty_borrowed - (int) $this->qty_returned);
    }

    public function remainingQuantity(): int
    {
        return $this->missingQuantity();
    }

    public function hasShortage(): bool
    {
        return $this->isReturned() && $this->missingQuantity() > 0;
    }
}
