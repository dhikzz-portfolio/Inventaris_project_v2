<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case BELUM_DIPERIKSA = 'BELUM_DIPERIKSA';
    case MENUNGGU_KONFIRMASI = 'MENUNGGU_KONFIRMASI';
    case SUDAH_DIKONFIRMASI = 'SUDAH_DIKONFIRMASI';
    case PERLU_TINDAK_LANJUT = 'PERLU_TINDAK_LANJUT';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIPERIKSA => 'Belum diperiksa',
            self::MENUNGGU_KONFIRMASI => 'Menunggu konfirmasi',
            self::SUDAH_DIKONFIRMASI => 'Sudah dikonfirmasi',
            self::PERLU_TINDAK_LANJUT => 'Memerlukan tindak lanjut',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BELUM_DIPERIKSA => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            self::MENUNGGU_KONFIRMASI => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            self::SUDAH_DIKONFIRMASI => 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
            self::PERLU_TINDAK_LANJUT => 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200',
        };
    }

    public function isConfirmed(): bool
    {
        return $this === self::SUDAH_DIKONFIRMASI;
    }
}
