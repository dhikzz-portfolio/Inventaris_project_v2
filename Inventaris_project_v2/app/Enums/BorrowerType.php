<?php

namespace App\Enums;

/**
 * Kategori peminjam. Dipisahkan dari role sistem: semua kategori memakai
 * role PEMINJAM, sedangkan kategori hanya menjelaskan asal pengguna.
 */
enum BorrowerType: string
{
    case SISWA = 'SISWA';
    case GURU = 'GURU';
    case STAF = 'STAF';

    public function label(): string
    {
        return match ($this) {
            self::SISWA => 'Siswa',
            self::GURU => 'Guru',
            self::STAF => 'Staf / Tenaga Kependidikan',
        };
    }

    /** @return list<self> */
    public static function casesForSelect(): array
    {
        return self::cases();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
