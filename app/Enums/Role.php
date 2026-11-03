<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';

    /** Administrasi akun pengguna; tidak otomatis memiliki akses inventaris. */
    case SUPER_ADMIN_PENGGUNA = 'SUPER_ADMIN_PENGGUNA';

    case KETUA_LABORAN = 'KETUA_LABORAN';
    case LABORAN = 'LABORAN';
    case PEMINJAM = 'PEMINJAM';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Wakasek Sarpras / Super Admin',
            self::SUPER_ADMIN_PENGGUNA => 'Super Admin Pengguna',
            self::KETUA_LABORAN => 'Ketua Laboran',
            self::LABORAN => 'Laboran / K3',
            self::PEMINJAM => 'Peminjam (Siswa / Guru / Staf)',
        };
    }

    /** Label singkat untuk dokumen resmi (kop & blok tanda tangan). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Wakasek Sarpras',
            self::SUPER_ADMIN_PENGGUNA => 'Super Admin Pengguna',
            self::KETUA_LABORAN => 'Ketua Laboran',
            self::LABORAN => 'Laboran / K3',
            self::PEMINJAM => 'Peminjam',
        };
    }

    /** Role yang boleh dikelola oleh Super Admin Pengguna (least privilege). */
    public function isAssignableByAccountAdmin(): bool
    {
        return $this !== self::SUPER_ADMIN;
    }

    /** @return list<self> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
