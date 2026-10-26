<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionAllocation extends Model
{
    protected $fillable = ['item_catalog_id', 'room_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
