<?php

namespace App\Enums;

enum DamageClassification: string
{
    case RUSAK_RINGAN = 'RUSAK_RINGAN';
    case RUSAK_BERAT = 'RUSAK_BERAT';

    public function label(): string
    {
        return match ($this) {
            self::RUSAK_RINGAN => 'Rusak Ringan',
            self::RUSAK_BERAT => 'Rusak Berat',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RUSAK_RINGAN => 'Masih dapat diperbaiki dan berpotensi dipakai kembali setelah perbaikan.',
            self::RUSAK_BERAT => 'Kerusakan serius; dipertimbangkan untuk penghapusan melalui alur resmi.',
        };
    }

    public function condition(): Condition
    {
        return match ($this) {
            self::RUSAK_RINGAN => Condition::RUSAK_RINGAN,
            self::RUSAK_BERAT => Condition::RUSAK_BERAT,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RUSAK_RINGAN => 'bg-amber-100 text-amber-800',
            self::RUSAK_BERAT => 'bg-red-100 text-red-800',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
