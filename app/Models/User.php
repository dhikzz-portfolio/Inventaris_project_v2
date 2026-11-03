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
        'role', 'is_active', 'must_change_password', 'password_changed_at',
        'borrower_type', 'major_id', 'class_name', 'last_login_at',
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
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
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

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN->value;
    }

    public function isEditorValidator(): bool
    {
        return $this->role === Role::EDITOR_VALIDATOR->value;
    }

    public function isOperator(): bool
    {
        return $this->role === Role::OPERATOR->value;
    }

    public function isMember(): bool
    {
        return $this->role === Role::MEMBER->value;
    }

    /**
     * Alias kompatibilitas dari nama role lama.
     * Kelompok ini dipertahankan hanya agar penyesuaian view/service/
     * test dapat dilakukan bertahap; sumber otorisasi aktif sebaiknya
     * menggunakan nama role baru atau pemeriksaan level kewenangan.
     */
    public function isLabLeader(): bool
    {
        return $this->role === Role::EDITOR_VALIDATOR->value;
    }

    public function isStaff(): bool
    {
        return $this->role === Role::OPERATOR->value;
    }

    public function isBorrower(): bool
    {
        return $this->role === Role::MEMBER->value;
    }

    public function isSuperAdminUser(): bool
    {
        return $this->role === Role::SUPER_ADMIN->value;
    }

    public function isWakasekSarpras(): bool
    {
        return $this->role === Role::ADMIN->value;
    }

    /** Boleh mengelola akun pengguna: hanya Super Admin. */
    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isActive(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    /** Akun wajib mengganti password awal sebelum memakai aplikasi. */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    /**
     * Super Admin hanya mengelola akun dan pengaturan. Role ini tidak memperoleh
     * transaksi inventaris apa pun meskipun dapat login ke aplikasi.
     */
    public function canUseInventoryOperations(): bool
    {
        return ! $this->isSuperAdmin();
    }

    /**
     * Super Admin tidak memiliki kewenangan operasional inventaris.
     * Admin dan role operasional lainnya memiliki kewenangan sesuai jabatannya.
     */
    public function hasOperationalInventoryAccess(): bool
    {
        return in_array($this->role, [
            Role::ADMIN->value,
            Role::EDITOR_VALIDATOR->value,
            Role::OPERATOR->value,
        ], true);
    }

    /** Boleh kelola data barang / mutasi / laporan pada cakupan kewenangannya. */
    public function managesInventory(): bool
    {
        return in_array($this->role, [Role::ADMIN->value, Role::EDITOR_VALIDATOR->value], true);
    }

    /** Boleh menyetujui peminjaman level 1 (operator/editor) — pengelola juga dianggap boleh. */
    public function canApproveLoans(): bool
    {
        return in_array($this->role, [Role::ADMIN->value, Role::EDITOR_VALIDATOR->value, Role::OPERATOR->value], true);
    }

    /** Operator yang ditugaskan pada satu atau lebih laboratorium. */
    public function hasRoomScope(int $roomId): bool
    {
        return $this->roomScopes()->where('room_id', $roomId)->exists();
    }

    /** Boleh menyetujui pengadaan/penghapusan & approval level 2 (admin). */
    public function isApproverLevel2(): bool
    {
        return $this->role === Role::ADMIN->value;
    }

    /**
     * Boleh memutuskan pada level persetujuan tertentu.
     * Level 2 (barang bernilai tinggi) hanya boleh diputuskan Admin
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
