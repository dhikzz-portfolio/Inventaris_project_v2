<?php

namespace App\Enums;

enum UnitStatus: string
{
    case AKTIF = 'AKTIF';
    case DIPINJAM = 'DIPINJAM';
    case HILANG = 'HILANG';
    case DIHAPUS = 'DIHAPUS';

    public function label(): string
    {
        return match ($this) {
            self::AKTIF => 'Aktif',
            self::DIPINJAM => 'Sedang Dipinjam',
            self::HILANG => 'Hilang',
            self::DIHAPUS => 'Dihapus',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AKTIF => 'bg-green-100 text-green-800',
            self::DIPINJAM => 'bg-blue-100 text-blue-800',
            self::HILANG => 'bg-red-100 text-red-800',
            self::DIHAPUS => 'bg-gray-200 text-gray-600',
        };
    }
}
