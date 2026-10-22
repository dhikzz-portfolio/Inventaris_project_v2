<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN_SARPRAS = 'ADMIN_SARPRAS';
    case LABORAN = 'LABORAN';
    case PEMINJAM = 'PEMINJAM';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Wakasek Sarpras / Super Admin',
            self::ADMIN_SARPRAS => 'Pengelola Inventaris / Admin Sarpras',
            self::LABORAN => 'Laboran / Ketua Kompetensi Keahlian',
            self::PEMINJAM => 'Peminjam / Guru / Staf',
        };
    }

    /** Label singkat untuk dokumen resmi (kop & blok tanda tangan). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Wakasek Sarpras',
            self::ADMIN_SARPRAS => 'Pengelola Inventaris',
            self::LABORAN => 'Laboran / Ketua Laboran',
            self::PEMINJAM => 'Peminjam',
        };
    }

    /** @return list<self> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
