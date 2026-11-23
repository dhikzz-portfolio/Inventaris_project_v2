<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    protected $fillable = [
        'asset_unit_id', 'performed_at', 'type', 'description',
        'cost', 'performed_by', 'document_path',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class, 'asset_unit_id');
    }
}
