<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'pic_name', 'description', 'start_date', 'end_date', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
