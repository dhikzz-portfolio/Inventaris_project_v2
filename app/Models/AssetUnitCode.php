<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetUnitCode extends Model
{
    protected $fillable = [
        'asset_unit_id', 'inventory_code', 'room_id', 'item_type_code', 'unit_no',
        'issued_at', 'revoked_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'unit_no' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
