<?php

namespace App\Enums;

enum NotificationType: string
{
    case TERLAMBAT = 'TERLAMBAT';
    case STOK_MENIPIS = 'STOK_MENIPIS';
    case PERSETUJUAN = 'PERSETUJUAN';
    case PENGEMBALIAN = 'PENGEMBALIAN';
    case PENGADAAN = 'PENGADAAN';
    case PENGHAPUSAN = 'PENGHAPUSAN';
    case LAINNYA = 'LAINNYA';

    public function label(): string
    {
        return match ($this) {
            self::TERLAMBAT => 'Terlambat',
            self::STOK_MENIPIS => 'Stok Menipis',
            self::PERSETUJUAN => 'Persetujuan',
            self::PENGEMBALIAN => 'Pengembalian',
            self::PENGADAAN => 'Pengadaan',
            self::PENGHAPUSAN => 'Penghapusan',
            self::LAINNYA => 'Lainnya',
        };
    }
}
