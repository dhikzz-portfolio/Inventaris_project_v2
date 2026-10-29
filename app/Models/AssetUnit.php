<?php

namespace App\Models;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetUnit extends Model
{
    protected $fillable = [
        'item_catalog_id', 'inventory_code', 'room_id', 'unit_no',
        'condition', 'status', 'acquisition_date', 'acquisition_year',
        'acquisition_value', 'funding_source', 'acquisition_source',
        'register_no', 'photo_path', 'notes', 'last_label_printed_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'condition' => Condition::class,
            'status' => UnitStatus::class,
            'acquisition_date' => 'date',
            'acquisition_year' => 'integer',
            'acquisition_value' => 'decimal:2',
            'last_label_printed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_catalog_id');
    }

    /** Spesifikasi aktif untuk unit ini. */
    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class, 'specification_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(AssetUnitCode::class);
    }

    public function activeCode(): HasMany
    {
        return $this->hasMany(AssetUnitCode::class)->whereNull('revoked_at');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetUnitMovement::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /** Penugasan unit pada peminjaman; satu unit hanya boleh punya satu penugasan terbuka. */
    public function openLoanItemUnit(): HasMany
    {
        return $this->hasMany(LoanItemUnit::class)->whereNull('returned_at');
    }

    public function isCurrentlyOnLoan(): bool
    {
        return $this->openLoanItemUnit()->exists();
    }

    /**
     * Cakupan data unit per peran. Super Admin melihat semua; Ketua Laboran
     * hanya ruang laboratorium; Laboran/K3 hanya ruangan yang ditetapkan.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isKetuaLaboran()) {
            return $query->whereHas('room', fn (Builder $room) => $room->where('is_lab', true));
        }

        if ($user->isLaboran()) {
            return $query->whereIn('room_id', $user->roomScopes()->select('room_id'));
        }

        return $query;
    }

    public function scopeSearchCode(Builder $query, string $code): Builder
    {
        return $query->where('inventory_code', 'like', $code.'%');
    }

    /**
     * Unit dapat dipinjam bila aktif, kondisi baik, tidak sedang dipinjam,
     * dan memenuhi status inventaris yang berlaku.
     */
    public function isAvailableForLoan(): bool
    {
        return $this->status === UnitStatus::AKTIF
            && $this->condition === Condition::BAIK
            && ! $this->isCurrentlyOnLoan();
    }

    public function locationLabel(): string
    {
        $room = $this->room;

        if ($room === null) {
            return '-';
        }

        return sprintf(
            'Gedung %s, Lantai %d, Ruang %02d',
            $room->buildingCode() ?: '-',
            (int) $room->floor,
            (int) ($room->room_no ?: 0)
        );
    }

    /** Filter unit berdasarkan spesifikasi aktif. */
    public function scopeBySpecification(Builder $query, int $specId): Builder
    {
        return $query->whereHas('specification', fn ($q) => $q->where('id', $specId));
    }
}
