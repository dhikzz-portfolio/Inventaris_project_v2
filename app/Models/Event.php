<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'pic_name', 'description', 'start_date', 'end_date',
        'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => EventStatus::class,
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Guru/staf hanya boleh mengubah kegiatan yang dibuatnya; pengelola
     * inventaris (Wakasek/Ketua Laboran) boleh memantau dan mengubah semua
     * kegiatan sesuai kewenangannya.
     */
    public function isManageableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->managesInventory() || (int) $this->created_by === (int) $user->id;
    }

    /** Gabungan semua ID barang yang dibooking pada event ini. */
    public function bookedItemIds(): array
    {
        return $this->loans()
            ->with('loanItems.item:id,name,internal_id')
            ->get()
            ->flatMap(fn ($loan) => $loan->loanItems)
            ->pluck('item.internal_id')
            ->unique()
            ->values()
            ->all();
    }
}
