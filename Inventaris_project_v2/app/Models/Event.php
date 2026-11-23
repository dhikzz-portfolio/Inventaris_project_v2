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
        'name', 'pic_name', 'pic_user_id', 'description', 'start_date', 'end_date',
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

    /** Akun penanggung jawab bila dipilih dari pengguna terdaftar. */
    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    /**
     * Nama penanggung jawab untuk tampilan/dokumen. Nama akun (dengan gelar)
     * diprioritaskan; nama khusus dipakai bila PIC bukan akun pengguna.
     */
    public function picDisplayName(): string
    {
        return trim((string) ($this->picUser?->displayName() ?: $this->pic_name));
    }

    /** PIC adalah akun pengguna terdaftar, bukan nama khusus. */
    public function picIsUserAccount(): bool
    {
        return $this->pic_user_id !== null;
    }

    public function hasLoans(): bool
    {
        return $this->loans()->exists();
    }

    /**
     * Pembuat kegiatan atau Admin boleh menghapus. PIC tidak otomatis
     * menjadi pemilik. Kegiatan yang sudah memiliki riwayat peminjaman tidak
     * dapat dihapus siapa pun.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if ($user === null || $this->hasLoans()) {
            return false;
        }

        return $user->isAdmin() || (int) $this->created_by === (int) $user->id;
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Guru/staf hanya boleh mengubah kegiatan yang dibuatnya; pengelola
     * inventaris (Admin/Editor/Validator) boleh memantau dan mengubah semua
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
