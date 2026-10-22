<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'major_id', 'building', 'floor', 'code', 'name',
        'building_letter', 'room_no', 'is_lab',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'room_no' => 'integer',
            'is_lab' => 'boolean',
        ];
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class);
    }

    /** Prefix kode inventaris: huruf gedung + lantai, mis. G1. */
    public function codePrefix(): string
    {
        return ($this->building_letter ?: 'G').(int) $this->floor;
    }

    public function roomLabel(): string
    {
        return sprintf('Gedung %s, Lantai %d, Ruang %02d', $this->building_letter, (int) $this->floor, (int) $this->room_no);
    }

    public function movementsFrom(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'from_room_id');
    }

    public function movementsTo(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'to_room_id');
    }

    /** Label lengkap: "Lab Komputer 1 (G1 Lt.1 / G1-01)" */
    public function fullLabel(): string
    {
        return sprintf('%s (%s Lt.%d / %s)', $this->name, $this->building, $this->floor, $this->code);
    }
}
