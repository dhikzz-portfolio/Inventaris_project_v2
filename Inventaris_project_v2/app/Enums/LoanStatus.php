<?php

namespace App\Enums;

enum LoanStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case DIPINJAM = 'DIPINJAM';
    case SELESAI = 'SELESAI';
    case DITOLAK = 'DITOLAK';
    case DIBATALKAN = 'DIBATALKAN';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::DIPINJAM => 'Sedang Dipinjam',
            self::SELESAI => 'Selesai',
            self::DITOLAK => 'Ditolak',
            self::DIBATALKAN => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-yellow-100 text-yellow-800',
            self::APPROVED => 'bg-indigo-100 text-indigo-800',
            self::DIPINJAM => 'bg-blue-100 text-blue-800',
            self::SELESAI => 'bg-green-100 text-green-800',
            self::DITOLAK => 'bg-red-100 text-red-800',
            self::DIBATALKAN => 'bg-gray-200 text-gray-700',
        };
    }

    /** Status yang masih mengunci/menyimpan unit pada rentang jadwal. */
    public function isBlockingAvailability(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED, self::DIPINJAM], true);
    }
}
