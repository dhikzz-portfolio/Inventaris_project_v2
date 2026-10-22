<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetUnitMovement extends Model
{
    protected $fillable = [
        'asset_unit_id', 'from_room_id', 'to_room_id',
        'old_code', 'new_code', 'moved_by', 'moved_at', 'reason', 'note',
    ];

    protected function casts(): array
    {
        return ['moved_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function mover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
