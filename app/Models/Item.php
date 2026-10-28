<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use App\Services\ItemTypeCodeService;
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
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $catalog): void {
            if (blank($catalog->item_type_code)) {
                $catalog->item_type_code = app(ItemTypeCodeService::class)->generate((string) $catalog->name);
            }
        });

        static::updating(function (self $catalog): void {
            if ($catalog->isDirty('item_type_code')) {
                throw new \LogicException('Kode jenis barang tidak dapat diubah setelah dibuat.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'room_id', 'quantity', 'internal_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    /** Alokasi lokasi pada pengadaan multi-lokasi. */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcquisitionAllocation::class, 'item_catalog_id');
    }

    /** Total unit yang dialokasikan (0 bila pengadaan legacy tanpa alokasi). */
    public function allocatedQuantity(): int
    {
        return (int) $this->allocations()->sum('quantity');
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

            // Katalog lama/pengadaan yang belum memiliki unit tetap mengikuti
            // lokasi katalognya agar data pra-migrasi tidak hilang dari tampilan.
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

    /** Hanya item yang sudah disetujui pengadaan yang tampil di katalog. */
    public function scopeApproved($query)
    {
        return $query->where('acquisition_status', 'APPROVED');
    }

    /** Katalog yang diarsipkan tidak ditawarkan lagi untuk peminjaman baru. */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
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
        return $this->acquisition_status === 'PENDING';
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
     * `activeLoanRemainingQuantity()` (qty_borrowed - qty_returned), sehingga
     * menghitungnya dua kali membuat stok tersedia terlihat kurang.
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
     * Query dasar selisih yang belum ditutup.
     *
     * Status yang difilter adalah status PEMINJAMAN (tabel `loans`), bukan
     * `loan_items` — tabel `loan_items` tidak punya kolom `status`, sehingga
     * memfilter kolom itu akan menggagalkan query ("Unknown column") di MySQL.
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
            && $this->acquisition_status === 'APPROVED'
            && $this->availableQuantity() > 0;
    }
}
