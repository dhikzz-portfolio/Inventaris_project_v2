<?php

namespace App\Models;

use App\Enums\ItemStatus;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'internal_id', 'location_code', 'asset_code', 'register_no', 'name',
        'category_id', 'room_id', 'specification', 'acquisition_year',
        'funding_source', 'quantity', 'qty_damaged', 'qty_lost', 'unit', 'min_stock', 'is_high_value',
        'status', 'photo_path', 'last_label_printed_at',
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
            'min_stock' => 'integer',
            'is_high_value' => 'boolean',
            'status' => ItemStatus::class,
            'last_label_printed_at' => 'datetime',
            'acquisition_decided_at' => 'datetime',
        ];
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
        return (int) LoanItem::query()
            ->where('item_id', $this->id)
            ->whereIn('loan_id', function ($q) {
                $q->select('id')->from('loans')
                    ->whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value]);
            })
            ->sum('qty_lost');
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

    public function isConsumable(): bool
    {
        return (bool) ($this->category?->is_consumable);
    }

    public function isLowStock(): bool
    {
        return $this->min_stock !== null && $this->quantity <= $this->min_stock;
    }

    public function isAvailableStatus(): bool
    {
        return $this->status !== ItemStatus::DIHAPUS
            && $this->acquisition_status === 'APPROVED'
            && $this->availableQuantity() > 0;
    }
}
