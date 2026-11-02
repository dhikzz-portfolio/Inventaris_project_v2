<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WriteOff extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id', 'quantity', 'mode', 'reason', 'berita_acara_path', 'status',
        'requested_by', 'approved_by', 'approved_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Snapshot unit yang diajukan pada penghapusan berbasis unit. */
    public function units(): HasMany
    {
        return $this->hasMany(WriteOffUnit::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /** Penghapusan berbasis unit (bukan kuantitas katalog legacy). */
    public function isUnitBased(): bool
    {
        return $this->mode === 'UNIT' || $this->units()->exists();
    }
}
