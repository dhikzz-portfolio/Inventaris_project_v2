<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionAllocation extends Model
{
    protected $fillable = ['item_catalog_id', 'specification_id', 'room_id', 'quantity', 'provisioned_at'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Spesifikasi (merek + tipe/model) yang dialokasikan. */
    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }
}
