<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['username', 'name', 'password', 'role', 'major_id', 'last_login_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'borrower_id');
    }

    public function notificationsApp(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function hasRoleValue(Role $role): bool
    {
        return $this->role === $role->value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SUPER_ADMIN->value;
    }

    public function isAdminSarpras(): bool
    {
        return $this->role === Role::ADMIN_SARPRAS->value;
    }

    public function isLaboran(): bool
    {
        return $this->role === Role::LABORAN->value;
    }

    public function isPeminjam(): bool
    {
        return $this->role === Role::PEMINJAM->value;
    }

    /** Boleh kelola data barang / mutasi / laporan / peminjaman seluruh unit. */
    public function managesInventory(): bool
    {
        return in_array($this->role, [Role::SUPER_ADMIN->value, Role::ADMIN_SARPRAS->value], true);
    }

    /** Boleh menyetujui peminjaman level 1 (laboran) — admin juga dianggap boleh. */
    public function canApproveLoans(): bool
    {
        return in_array($this->role, [Role::SUPER_ADMIN->value, Role::ADMIN_SARPRAS->value, Role::LABORAN->value], true);
    }

    /** Boleh menyetujui pengadaan/penghapusan & approval level 2 (wakasek). */
    public function isApproverLevel2(): bool
    {
        return $this->role === Role::SUPER_ADMIN->value;
    }
}
