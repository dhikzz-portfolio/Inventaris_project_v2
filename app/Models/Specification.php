<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specification extends Model
{
    use HasFactory;

    protected $table = 'specifications';

    protected $fillable = [
        'item_catalog_id', 'brand', 'model_type', 'description', 'spec_code',
    ];

    protected function casts(): array
    {
        return [
            'item_catalog_id' => 'integer',
        ];
    }

    public function itemCatalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'item_catalog_id');
    }

    /** Format nama resmi: brand + model_type jika ada */
    public function displayName(): string
    {
        $parts = [];

        $brand = $this->brand;
        if (! empty($brand)) {
            $parts[] = $brand;
        }

        $model = $this->model_type;
        if (! empty($model)) {
            $parts[] = $model;
        }

        $spec = $this->spec_code;
        if (! empty($spec)) {
            $parts[] = $spec;
        }

        if (empty($parts)) {
            return '-';
        }

        return implode(' ', $parts);
    }
}
