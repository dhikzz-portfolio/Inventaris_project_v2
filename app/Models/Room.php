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
        'major_id', 'building_id', 'floor', 'code', 'name',
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

    /** Master gedung pemilik ruangan ini. */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class);
    }

    /**
     * Huruf identitas gedung.
     *
     * Sumber kebenaran adalah master gedung; `building_letter` dipakai sebagai
     * cadangan untuk ruangan lama yang belum tertaut ke master.
     */
    public function buildingCode(): string
    {
        $code = $this->building?->code ?: $this->building_letter;

        return strtoupper((string) $code);
    }

    /** Prefix kode inventaris: huruf gedung + lantai, mis. C1. */
    public function codePrefix(): string
    {
        return ($this->buildingCode() ?: 'G').(int) $this->floor;
    }

    /** Kode lokasi ruangan, mis. C1-03. */
    public function locationCode(): string
    {
        return sprintf('%s-%02d', $this->codePrefix(), (int) $this->room_no);
    }

    public function roomLabel(): string
    {
        return sprintf('Gedung %s, Lantai %d, Ruang %02d', $this->buildingCode(), (int) $this->floor, (int) $this->room_no);
    }

    public function movementsFrom(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'from_room_id');
    }

    public function movementsTo(): HasMany
    {
        return $this->hasMany(ItemMovement::class, 'to_room_id');
    }

    /** Label lengkap: "Lab Komputer 1 (Gedung G Lt.1 / G1-01)" */
    public function fullLabel(): string
    {
        $building = $this->building?->name ?: $this->buildingCode();

        return sprintf('%s (%s Lt.%d / %s)', $this->name, $building, (int) $this->floor, $this->code);
    }
}
