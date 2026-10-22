<?php

namespace App\Models;

use App\Enums\Condition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'item_id', 'qty_borrowed', 'qty_returned', 'qty_damaged', 'qty_lost',
        'condition_out', 'condition_in', 'damage_photo_path', 'returned_room_id',
    ];

    protected function casts(): array
    {
        return [
            'qty_borrowed' => 'integer',
            'qty_returned' => 'integer',
            'qty_damaged' => 'integer',
            'qty_lost' => 'integer',
            'condition_out' => Condition::class,
            'condition_in' => Condition::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function returnedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'returned_room_id');
    }

    /** Unit fisik yang ditugaskan pada baris peminjaman ini, termasuk yang sudah dikembalikan. */
    public function unitAssignments(): HasMany
    {
        return $this->hasMany(LoanItemUnit::class);
    }

    /** Unit fisik yang sedang dipinjam (penugasan terbuka) untuk baris ini. */
    public function openUnitAssignments(): HasMany
    {
        return $this->hasMany(LoanItemUnit::class)->whereNull('returned_at');
    }

    /**
     * Unit fisik yang sedang dipinjam (penugasan terbuka) untuk baris ini.
     * Selama masa transisi, baris tanpa penugasan dianggap legacy (baris katalog).
     *
     * @return Collection<int, AssetUnit>
     */
    public function openAssignedUnits(): Collection
    {
        return $this->unitAssignments()->whereNull('returned_at')->with('unit')->get()->map(
            fn (LoanItemUnit $a) => $a->unit
        );
    }

    /** Baris peminjaman tanpa penugasan unit fisik (legacy / belum diserah terima). */
    public function hasUnitAssignments(): bool
    {
        return $this->unitAssignments()->exists();
    }

    public function isReturned(): bool
    {
        return $this->qty_returned !== null;
    }

    /** Sisa unit yang belum kembali (termasuk yang sementara ditandai kurang). */
    public function missingQuantity(): int
    {
        return max(0, $this->qty_borrowed - (int) $this->qty_returned);
    }

    public function remainingQuantity(): int
    {
        return $this->missingQuantity();
    }

    public function hasShortage(): bool
    {
        return $this->isReturned() && $this->missingQuantity() > 0;
    }
}
