<?php

namespace App\Models;

use App\Enums\BorrowerType;
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

    protected $fillable = [
        'name', 'degree', 'email', 'email_verified_at', 'password',
        'role', 'borrower_type', 'major_id', 'class_name', 'last_login_at',
        'birth_date', 'profile_completed_at', 'profile_completed', 'work_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'borrower_type' => BorrowerType::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date',
            'profile_completed_at' => 'datetime',
            'profile_completed' => 'boolean',
        ];
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /**
     * Nama untuk sapaan dan dokumen resmi: nama lengkap + gelar bila ada.
     */
    public function displayName(): string
    {
        $name = trim((string) ($this->name ?: $this->email));
        $degree = trim((string) ($this->degree ?? ''));

        return $degree !== '' ? $name.', '.$degree : $name;
    }

    /** Nama tanpa gelar (dipakai bila gelar sudah menyatu pada nama lama). */
    public function greetingName(): string
    {
        return $this->displayName() ?: 'User';
    }

    public function isGuru(): bool
    {
        return $this->borrower_type === BorrowerType::GURU;
    }

    /**
     * Profile readiness is derived from the current required fields; the
     * legacy completion flag is retained only for compatibility and auditing.
     */
    public function profileIsComplete(): bool
    {
        if (blank($this->name) || $this->birth_date === null || $this->birth_date->isFuture()) {
            return false;
        }

        return match ($this->borrower_type) {
            BorrowerType::SISWA => filled($this->class_name) && $this->major_id !== null,
            BorrowerType::GURU => true,
            BorrowerType::STAF => filled($this->work_unit),
            default => false,
        };
    }

    public function hasCompletedProfile(): bool
    {
        return (bool) $this->profile_completed && $this->profileIsComplete();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'borrower_id');
    }

    public function notificationsApp(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /** Notifikasi in-app yang belum dibaca — dipakai badge lonceng & menu. */
    public function unreadNotifications(): HasMany
    {
        return $this->notificationsApp()->whereNull('read_at');
    }

    /** Cakupan laboratorium/ruangan untuk Laboran/K3. */
    public function roomScopes(): HasMany
    {
        return $this->hasMany(UserRoomScope::class);
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
        return $this->role === Role::KETUA_LABORAN->value;
    }

    public function isKetuaLaboran(): bool
    {
        return $this->role === Role::KETUA_LABORAN->value;
    }

    public function isLaboran(): bool
    {
        return $this->role === Role::LABORAN->value;
    }

    public function isPeminjam(): bool
    {
        return $this->role === Role::PEMINJAM->value;
    }

    /** Boleh kelola data barang / mutasi / laporan pada cakupan kewenangannya. */
    public function managesInventory(): bool
    {
        return in_array($this->role, [Role::SUPER_ADMIN->value, Role::KETUA_LABORAN->value], true);
    }

    /** Boleh menyetujui peminjaman level 1 (laboran) — pengelola juga dianggap boleh. */
    public function canApproveLoans(): bool
    {
        return in_array($this->role, [Role::SUPER_ADMIN->value, Role::KETUA_LABORAN->value, Role::LABORAN->value], true);
    }

    /** Laboran/K3 yang ditugaskan pada satu atau lebih laboratorium. */
    public function hasRoomScope(int $roomId): bool
    {
        return $this->roomScopes()->where('room_id', $roomId)->exists();
    }

    /** Boleh menyetujui pengadaan/penghapusan & approval level 2 (wakasek). */
    public function isApproverLevel2(): bool
    {
        return $this->role === Role::SUPER_ADMIN->value;
    }

    /**
     * Boleh memutuskan pada level persetujuan tertentu.
     * Level 2 (barang bernilai tinggi) hanya boleh diputuskan Wakasek Sarpras
     * sesuai kebijakan yang terdokumentasi pada DECISIONS.md.
     */
    public function canDecideAt(int $level): bool
    {
        return $level >= 2 ? $this->isApproverLevel2() : $this->canApproveLoans();
    }

    /** Boleh mencatat status dokumen peminjaman (cetak / tanda tangan fisik). */
    public function canManageLoanDocuments(): bool
    {
        return $this->managesInventory() || $this->canApproveLoans();
    }

    /** Guru dan staf boleh mencatat rekap kegiatan sekolah. */
    public function canRecordActivities(): bool
    {
        return in_array($this->borrower_type, [BorrowerType::GURU, BorrowerType::STAF], true);
    }
}
