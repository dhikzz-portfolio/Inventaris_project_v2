<?php

namespace App\Enums;

enum EventStatus: string
{
    case BELUM_BERJALAN = 'BELUM_BERJALAN';
    case SEDANG_BERJALAN = 'SEDANG_BERJALAN';
    case SUDAH_BERJALAN = 'SUDAH_BERJALAN';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_BERJALAN => 'Belum Berjalan',
            self::SEDANG_BERJALAN => 'Sedang Berjalan',
            self::SUDAH_BERJALAN => 'Sudah Berjalan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BELUM_BERJALAN => 'bg-gray-100 text-gray-700',
            self::SEDANG_BERJALAN => 'bg-blue-100 text-blue-800',
            self::SUDAH_BERJALAN => 'bg-green-100 text-green-800',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
