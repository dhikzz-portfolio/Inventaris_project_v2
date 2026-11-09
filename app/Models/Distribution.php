<?php

namespace App\Models;

use App\Casts\ConditionCast;
use App\Casts\UnitStatusCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribution extends Model
{
    /** Distribusi menerbitkan unit baru dari stok penerimaan. */
    public const SOURCE_STOCK = 'STOK';

    /** Distribusi lama: memindahkan unit yang sudah ada ke ruangan lain. */
    public const SOURCE_UNIT = 'UNIT';

    protected $fillable = [
        'item_catalog_id', 'specification_id', 'to_room_id', 'quantity', 'source',
        'condition', 'status', 'reason',
        'distributed_by', 'distributed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'condition' => ConditionCast::class,
            'status' => UnitStatusCast::class,
            'distributed_at' => 'datetime',
        ];
    }

    public function isStockDistribution(): bool
    {
        return $this->source === self::SOURCE_STOCK;
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
