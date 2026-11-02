<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriteOffUnit extends Model
{
    protected $fillable = [
        'write_off_id', 'asset_unit_id', 'is_active', 'item_catalog_id', 'specification_id',
        'inventory_code', 'brand', 'model_type', 'room_id', 'condition', 'status_before',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(WriteOff::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class, 'specification_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function identityLabel(): string
    {
        return trim(collect([$this->brand, $this->model_type])->filter()->implode(' — ')) ?: 'Tanpa Merek';
    }
}
