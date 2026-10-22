<?php

namespace App\Enums;

/**
 * Status dokumen peminjaman (surat peminjaman / BAST).
 *
 * Dipisahkan dari LoanStatus karena status dokumen tidak memengaruhi
 * kelayakan transaksi: dokumen belum dibuat, sudah dicetak, atau sudah
 * ditandatangani secara fisik tidak menentukan apakah peminjaman sah.
 */
enum DocumentStatus: string
{
    case BELUM_DIBUAT = 'BELUM_DIBUAT';
    case SIAP_DICETAK = 'SIAP_DICETAK';
    case DICETAK = 'DICETAK';
    case DITANDATANGANI_FISIK = 'DITANDATANGANI_FISIK';
    case DITANDATANGANI_DIGITAL = 'DITANDATANGANI_DIGITAL';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBUAT => 'Belum dibuat',
            self::SIAP_DICETAK => 'Siap dicetak',
            self::DICETAK => 'Sudah dicetak',
            self::DITANDATANGANI_FISIK => 'Ditandatangani (fisik)',
            self::DITANDATANGANI_DIGITAL => 'Ditandatangani (digital)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BELUM_DIBUAT => 'bg-gray-100 text-gray-600',
            self::SIAP_DICETAK => 'bg-blue-100 text-blue-800',
            self::DICETAK => 'bg-indigo-100 text-indigo-800',
            self::DITANDATANGANI_FISIK => 'bg-green-100 text-green-800',
            self::DITANDATANGANI_DIGITAL => 'bg-green-100 text-green-800',
        };
    }

    /** Dokumen sudah ditandatangani (fisik maupun digital). */
    public function isSigned(): bool
    {
        return in_array($this, [self::DITANDATANGANI_FISIK, self::DITANDATANGANI_DIGITAL], true);
    }
}
