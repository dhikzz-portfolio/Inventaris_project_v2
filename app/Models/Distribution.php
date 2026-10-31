<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribution extends Model
{
    protected $fillable = [
        'item_catalog_id', 'specification_id', 'to_room_id', 'quantity',
        'distributed_by', 'distributed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'distributed_at' => 'datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(DistributionUnit::class);
    }
}
