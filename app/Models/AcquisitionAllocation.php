<?php

namespace App\Models;

use App\Enums\Condition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionAllocation extends Model
{
    protected $fillable = [
        'item_catalog_id', 'specification_id', 'room_id',
        'quantity', 'received_quantity', 'condition', 'note',
        'acquisition_year', 'funding_source', 'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'condition' => Condition::class,
            'acquisition_year' => 'integer',
            'provisioned_at' => 'datetime',
        ];
    }

    /** Kondisi fisik alokasi; pengadaan lama tanpa nilai dianggap baik. */
    public function conditionEnum(): Condition
    {
        return $this->condition instanceof Condition ? $this->condition : Condition::BAIK;
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
