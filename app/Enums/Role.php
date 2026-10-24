<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case KETUA_LABORAN = 'KETUA_LABORAN';
    case LABORAN = 'LABORAN';
    case PEMINJAM = 'PEMINJAM';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Wakasek Sarpras / Super Admin',
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
            self::KETUA_LABORAN => 'Ketua Laboran',
            self::LABORAN => 'Laboran / K3',
            self::PEMINJAM => 'Peminjam',
        };
    }

    /** @return list<self> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
