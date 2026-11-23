<?php

namespace App\Models;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specification extends Model
{
    use HasFactory;

    /** Nilai khusus agar merek/tipe yang tidak diisi tidak menjadi data kosong. */
    public const BRAND_UNSPECIFIED = 'Tanpa Merek';

    public const MODEL_UNSPECIFIED = 'Tidak Ditentukan';

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

    /** Unit inventaris fisik yang memakai spesifikasi ini. */
    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'specification_id');
    }

    /** Alokasi pengadaan yang membeli spesifikasi ini. */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcquisitionAllocation::class, 'specification_id');
    }

    /** Riwayat distribusi spesifikasi ini. */
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'specification_id');
    }

    public static function normalizeBrand(?string $brand): string
    {
        $brand = trim((string) $brand);

        return $brand !== '' ? $brand : self::BRAND_UNSPECIFIED;
    }

    public static function normalizeModel(?string $modelType): string
    {
        $modelType = trim((string) $modelType);

        return $modelType !== '' ? $modelType : self::MODEL_UNSPECIFIED;
    }

    /**
     * Cari/buat spesifikasi milik satu katalog. Katalog tetap satu jenis
     * barang; merek dan tipe/model disimpan pada spesifikasi.
     */
    public static function resolveFor(Item $catalog, ?string $brand, ?string $modelType, ?string $description = null): self
    {
        $brand = self::normalizeBrand($brand);
        $modelType = self::normalizeModel($modelType);

        $existing = self::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('brand', $brand)
            ->where('model_type', $modelType)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sequence = self::query()->where('item_catalog_id', $catalog->id)->count() + 1;

        do {
            $code = sprintf('SPK-C%d-%03d', $catalog->id, $sequence);
            $sequence++;
        } while (self::query()->where('spec_code', $code)->exists());

        return self::create([
            'item_catalog_id' => $catalog->id,
            'brand' => $brand,
            'model_type' => $modelType,
            'description' => $description,
            'spec_code' => $code,
        ]);
    }

    /** Label resmi spesifikasi: `Merek — Tipe/Model`. */
    public function displayName(): string
    {
        return $this->brand.' — '.$this->model_type;
    }

    public function brandLabel(): string
    {
        return self::normalizeBrand($this->brand);
    }

    public function modelLabel(): string
    {
        return self::normalizeModel($this->model_type);
    }

    /** Spesifikasi hanya terlihat bila katalognya berada dalam cakupan pengguna. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('itemCatalog', fn (Builder $catalog) => $catalog->visibleTo($user));
    }

    /**
     * Unit spesifikasi ini yang masih tersedia untuk distribusi: aktif, tidak
     * sedang dipinjam, dan belum pernah dialokasikan lewat distribusi.
     */
    public function distributableQuery(?User $user = null, ?int $excludeRoomId = null): Builder
    {
        return AssetUnit::query()
            ->where('specification_id', $this->id)
            ->where('status', UnitStatus::AKTIF->value)
            ->whereDoesntHave('openLoanItemUnit')
            ->whereDoesntHave('distributionUnit')
            ->when($excludeRoomId !== null, fn (Builder $query) => $query->where('room_id', '!=', $excludeRoomId))
            ->when($user !== null, fn (Builder $query) => $query->visibleTo($user));
    }

    public function distributableCount(?User $user = null, ?int $excludeRoomId = null): int
    {
        return $this->distributableQuery($user, $excludeRoomId)->count();
    }

    /** Unit aktif dan berkondisi baik pada spesifikasi ini (untuk ringkasan stok). */
    public function availableQuery(?User $user = null): Builder
    {
        return AssetUnit::query()
            ->where('specification_id', $this->id)
            ->where('status', UnitStatus::AKTIF->value)
            ->where('condition', Condition::BAIK->value)
            ->whereDoesntHave('openLoanItemUnit')
            ->when($user !== null, fn (Builder $query) => $query->visibleTo($user));
    }
}
