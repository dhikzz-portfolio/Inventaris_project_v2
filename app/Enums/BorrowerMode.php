<?php

namespace App\Enums;

/**
 * Siapa yang meminjam barang pada satu transaksi peminjaman.
 *
 * Berbeda dari role akun pembuat transaksi: Laboran/Wakasek/Ketua Laboran
 * dapat membuat transaksi untuk pihak lain, sedangkan akun Peminjam hanya
 * boleh meminjam untuk dirinya sendiri.
 */
enum BorrowerMode: string
{
    case SELF = 'SELF';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::SELF => 'Diri sendiri',
            self::OTHER => 'Orang lain',
        };
    }

    /** @return list<self> */
    public static function optionsForOperationalRoles(): array
    {
        return self::cases();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
