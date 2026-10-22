<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Master data gedung sekolah.
 *
 * `code` adalah identitas satu huruf (A–Z) yang dipakai generator kode lokasi
 * dan kode inventaris. Identitas ini adalah sumber kebenaran — bukan teks
 * bebas yang diketik pada formulir ruangan.
 */
class Building extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** Ruangan pada gedung ini yang sudah memakai unit inventaris. */
    public function roomsWithUnits(): HasMany
    {
        return $this->hasMany(Room::class)->whereHas('units');
    }

    /** Prefix kode lokasi untuk lantai tertentu, mis. Gedung C lantai 1 → "C1". */
    public function codePrefix(int $floor): string
    {
        return strtoupper($this->code).(int) $floor;
    }

    public function isUsableForNewRooms(): bool
    {
        return $this->is_active;
    }

    /**
     * Pilihan gedung untuk filter: master gedung + huruf gedung warisan yang
     * belum tertaut ke master (agar tidak ada lokasi yang hilang dari filter).
     *
     * @return Collection<int, array{code: string, name: string}>
     */
    public static function filterOptions(): Collection
    {
        $options = static::query()->orderBy('code')->get()
            ->map(fn (self $building) => ['code' => $building->code, 'name' => $building->name])
            ->all();

        $known = array_column($options, 'code');

        foreach (Room::query()->whereNotNull('building_letter')->distinct()->pluck('building_letter') as $letter) {
            $letter = strtoupper(trim((string) $letter));

            if ($letter !== '' && ! in_array($letter, $known, true)) {
                $options[] = ['code' => $letter, 'name' => 'Gedung '.$letter];
                $known[] = $letter;
            }
        }

        return collect($options);
    }
}
