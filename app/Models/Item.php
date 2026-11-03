<?php

namespace App\Models;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory, LogsActivity;

    /** Katalog jenis barang (revisi 2.1; tabel lama `items`). */
    protected $table = 'item_catalogs';

    protected $fillable = [
        'internal_id', 'location_code', 'register_no', 'name',
        'brand', 'model_type', 'description',
        'category_id', 'room_id', 'specification', 'acquisition_year',
        'funding_source', 'quantity', 'qty_damaged', 'qty_lost', 'unit', 'is_high_value',
        'status', 'photo_path', 'last_label_printed_at',
        'archived_at', 'archive_reason',
        'acquisition_status', 'acquisition_requested_by', 'acquisition_approved_by',
        'acquisition_decided_at', 'acquisition_note',
        'acquisition_submitted_at', 'acquisition_confirmed_by', 'acquisition_confirmed_at',
        'acquisition_started_at', 'acquisition_received_by', 'acquisition_received_at',
        'acquisition_received_quantity', 'acquisition_received_condition', 'acquisition_received_note',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_year' => 'integer',
            'quantity' => 'integer',
            'qty_damaged' => 'integer',
            'qty_lost' => 'integer',
            'is_high_value' => 'boolean',
            'status' => ItemStatus::class,
            'last_label_printed_at' => 'datetime',
            'archived_at' => 'datetime',
            'acquisition_decided_at' => 'datetime',
            'acquisition_submitted_at' => 'datetime',
            'acquisition_confirmed_at' => 'datetime',
            'acquisition_started_at' => 'datetime',
            'acquisition_received_at' => 'datetime',
            'acquisition_received_quantity' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'room_id', 'quantity', 'internal_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ItemMovement::class);
    }

    /** Unit inventaris fisik yang terhubung dengan katalog ini. */
    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'item_catalog_id');
    }

    /**
     * Spesifikasi (merek + tipe/model) yang dimiliki katalog ini.
     * Kolom teks `specification` lama tetap dipertahankan sebagai catatan bebas.
     */
    public function specifications(): HasMany
    {
        return $this->hasMany(Specification::class, 'item_catalog_id');
    }

    /** Unit inventaris yang hanya dapat diakses jika memiliki spesifikasi aktif. */
    public function validUnits(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'item_catalog_id')->whereHas('specification', fn ($q) => $q->where('item_catalog_id', $this->id));
    }

    /** Unit inventaris yang tersedia untuk dipinjam (ada spesifikasi, aktif, kondisi baik). */
    public function availableForLoanUnits(): HasMany
    {
        return $this->hasMany(AssetUnit::class, 'item_catalog_id')
            ->whereHas('specification')
            ->where('status', UnitStatus::AKTIF->value)
            ->where('condition', Condition::BAIK->value);
    }

    /** Alokasi lokasi pada pengadaan multi-lokasi. */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcquisitionAllocation::class, 'item_catalog_id');
    }

    /** Riwayat distribusi unit katalog ini. */
    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'item_catalog_id');
    }

    /** Total unit yang dialokasikan (0 bila pengadaan legacy tanpa alokasi). */
    public function allocatedQuantity(): int
    {
        return (int) $this->allocations()->sum('quantity');
    }

    /**
     * ID Ketua Laboran yang berwenang melihat katalog ini.
     * Digunakan untuk menentukan penerima notifikasi yang tepat.
     *
     * @return list<int>
     */
    public function visibleKetuaLaboranIds(): array
    {
        $ketuaLaboranIds = User::where('role', 'KETUA_LABORAN')->pluck('id');

        return $ketuaLaboranIds->filter(function ($id) {
            $user = User::find($id);

            return $user && $this->isVisibleTo($user);
        })->values()->all();
    }

    /**
     * Tentukan apakah katalog ini terlihat oleh pengguna tertentu.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->units->isNotEmpty()) {
            return $this->units->some(function ($unit) use ($user) {
                return $unit->visibleTo($user);
            });
        }

        if ($user->isKetuaLaboran()) {
            return $this->room && $this->room->is_lab;
        }

        if ($user->isLaboran()) {
            return $this->room && $user->hasRoomScope($this->room_id);
        }

        return false;
    }

    /**
     * Katalog yang dapat dilihat pengguna. Katalog tanpa unit dalam cakupan
     * hanya ditampilkan untuk Super Admin sebagai arsip.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user === null || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function ($visible) use ($user) {
            $visible->whereHas('units', fn ($unit) => $unit->visibleTo($user));

            $visible->orWhere(function ($legacy) use ($user) {
                $legacy->whereDoesntHave('units');

                if ($user->isKetuaLaboran()) {
                    $legacy->whereHas('room', fn ($room) => $room->where('is_lab', true));
                } elseif ($user->isLaboran()) {
                    $legacy->whereIn('room_id', $user->roomScopes()->select('room_id'));
                }
            });
        });
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(WriteOff::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquisition_requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquisition_approved_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquisition_confirmed_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acquisition_received_by');
    }

    /**
     * Hanya katalog yang sudah tercatat sebagai inventaris yang dapat dipinjam.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn('acquisition_status', AcquisitionStatus::recordedValues());
    }

    /** Katalog yang belum diarsipkan masih ditawarkan pada transaksi baru. */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Hanya katalog yang sudah tercatat sebagai inventaris.
     */
    public function isRecordedInInventory(): bool
    {
        return in_array($this->acquisition_status, AcquisitionStatus::recordedValues(), true);
    }

    public function acquisitionStatusEnum(): ?AcquisitionStatus
    {
        return AcquisitionStatus::tryFrom((string) $this->acquisition_status);
    }

    /** Peminjaman terakhir barang ini. */
    public function latestLoanItem(): ?LoanItem
    {
        return $this->loanItems()->with('loan.borrower')->latest()->first();
    }

    /** Riwayat peminjam terakhir. */
    public function lastBorrower(): ?User
    {
        return $this->latestLoanItem()?->loan?->borrower;
    }

    /** Sedang dipinjam pada saat ini (untuk filter & tampilan status). */
    public function isOnLoanNow(): bool
    {
        return $this->activeLoanRemainingQuantity() > 0;
    }

    /**
     * Jumlah unit yang masih berada di luar (disetujui/dipinjam) pada saat ini.
     * Menghitung sisa yang belum kembali, bukan seluruh kuantitas record.
     */
    public function activeLoanRemainingQuantity(): int
    {
        // Nilai sudah diagregasi oleh katalog (withSum) — hindari query N+1.
        if (array_key_exists('active_borrowed', $this->attributes)) {
            return max(0, (int) $this->attributes['active_borrowed'] - (int) ($this->attributes['active_returned'] ?? 0));
        }

        return (int) LoanItem::query()
            ->where('item_id', $this->id)
            ->whereIn('loan_id', function ($q) {
                $q->select('id')->from('loans')
                    ->whereIn('status', [
                        LoanStatus::APPROVED->value,
                        LoanStatus::DIPINJAM->value,
                    ]);
            })
            ->selectRaw('COALESCE(SUM(qty_borrowed - COALESCE(qty_returned, 0)), 0) as total')
            ->value('total');
    }

    /**
     * Unit yang benar-benar dapat dipinjam sekarang:
     * total - rusak permanen - hilang permanen - unit yang sedang di luar.
     *
     * Selisih yang belum ditutup (sementara) TIDAK boleh dikurangi lagi di
     * sini: unit yang hilang pada pinjaman aktif sudah tercakup pada
     * `activeLoanRemainingQuantity()`.
     */
    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->qty_damaged - $this->qty_lost - $this->activeLoanRemainingQuantity());
    }

    /**
     * Unit yang masih ditandai kurang pada pinjaman aktif (belum ditutup).
     * Angka ini bersifat sementara sampai pinjaman ditutup oleh Wakasek.
     */
    public function pendingLostQuantity(): int
    {
        return (int) $this->pendingLostQuery()->sum('qty_lost');
    }

    /**
     * Query dasar selisih yang belum ditutup. Status yang difilter adalah
     * status PEMINJAMAN (tabel `loans`), bukan `loan_items`.
     */
    public function pendingLostQuery(): Builder
    {
        return LoanItem::query()
            ->where('item_id', $this->id)
            ->whereIn('loan_id', function ($q) {
                $q->select('id')->from('loans')
                    ->whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value]);
            });
    }

    /** Status efektif untuk ditampilkan/difilter. */
    public function effectiveStatus(): ItemStatus
    {
        if ($this->status === ItemStatus::DIHAPUS) {
            return $this->status;
        }

        if ($this->availableQuantity() === 0 && $this->isOnLoanNow()) {
            return ItemStatus::DIPINJAM;
        }

        if ($this->qty_lost + $this->qty_damaged >= $this->quantity && $this->quantity > 0) {
            return $this->qty_lost > 0 ? ItemStatus::HILANG : $this->status;
        }

        return $this->status;
    }

    /** Ringkasan kondisi per unit untuk ditampilkan di UI. */
    public function stockSummary(): string
    {
        $parts = ["Tersedia {$this->availableQuantity()} {$this->unit}"];
        if ($this->qty_damaged > 0) {
            $parts[] = "rusak {$this->qty_damaged}";
        }
        if ($this->qty_lost > 0) {
            $parts[] = "hilang {$this->qty_lost}";
        }
        $out = $this->activeLoanRemainingQuantity();
        if ($out > 0) {
            $parts[] = "di luar {$out}";
        }

        return implode(' • ', $parts);
    }

    public function isAvailableStatus(): bool
    {
        return $this->status !== ItemStatus::DIHAPUS
            && $this->isRecordedInInventory()
            && $this->availableQuantity() > 0;
    }

    /**
     * Katalog hanya boleh dihapus permanen bila belum pernah dipakai unit
     * maupun transaksi. Selain itu data harus diarsipkan agar audit tetap utuh.
     */
    public function canBeDeletedPermanently(): bool
    {
        return ! $this->units()->exists()
            && ! $this->loanItems()->exists()
            && ! $this->writeOffs()->exists();
    }

    public function isAcquisitionPending(): bool
    {
        return ! $this->isRecordedInInventory();
    }
}
