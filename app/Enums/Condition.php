<?php

namespace App\Enums;

enum Condition: string
{
    case BAIK = 'BAIK';
    case RUSAK_RINGAN = 'RUSAK_RINGAN';
    case RUSAK_BERAT = 'RUSAK_BERAT';

    public function label(): string
    {
        return match ($this) {
            self::BAIK => 'Baik',
            self::RUSAK_RINGAN => 'Rusak Ringan',
            self::RUSAK_BERAT => 'Rusak Berat',
        };
    }

    public function isDamaged(): bool
    {
        return $this !== self::BAIK;
    }
}
