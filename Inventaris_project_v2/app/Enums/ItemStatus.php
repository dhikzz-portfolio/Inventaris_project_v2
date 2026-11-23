<?php

namespace App\Enums;

enum ItemStatus: string
{
    case BAIK = 'BAIK';
    case RUSAK_RINGAN = 'RUSAK_RINGAN';
    case RUSAK_BERAT = 'RUSAK_BERAT';
    case HILANG = 'HILANG';
    case DIPINJAM = 'DIPINJAM';
    case DIHAPUS = 'DIHAPUS';

    public function label(): string
    {
        return match ($this) {
            self::BAIK => 'Baik',
            self::RUSAK_RINGAN => 'Rusak Ringan',
            self::RUSAK_BERAT => 'Rusak Berat',
            self::HILANG => 'Hilang',
            self::DIPINJAM => 'Sedang Dipinjam',
            self::DIHAPUS => 'Dihapus',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BAIK => 'bg-green-100 text-green-800',
            self::RUSAK_RINGAN => 'bg-yellow-100 text-yellow-800',
            self::RUSAK_BERAT => 'bg-orange-100 text-orange-800',
            self::HILANG => 'bg-red-100 text-red-800',
            self::DIPINJAM => 'bg-blue-100 text-blue-800',
            self::DIHAPUS => 'bg-gray-200 text-gray-600',
        };
    }
}
