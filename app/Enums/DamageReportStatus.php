<?php

namespace App\Enums;

/**
 * Status penanganan laporan kerusakan.
 *
 * Sengaja tidak memuat tahap persetujuan perbaikan karena keputusan tersebut
 * masih PENDING (lihat docs/PENDING_DECISIONS.md). Semua transisi di sini
 * bersifat pencatatan/pemantauan, bukan otorisasi penghapusan.
 */
enum DamageReportStatus: string
{
    case DILAPORKAN = 'DILAPORKAN';
    case DIPERIKSA = 'DIPERIKSA';
    case DALAM_PERBAIKAN = 'DALAM_PERBAIKAN';
    case SELESAI = 'SELESAI';
    case MENUNGGU_PENGHAPUSAN = 'MENUNGGU_PENGHAPUSAN';

    public function label(): string
    {
        return match ($this) {
            self::DILAPORKAN => 'Dilaporkan',
            self::DIPERIKSA => 'Diperiksa',
            self::DALAM_PERBAIKAN => 'Dalam perbaikan',
            self::SELESAI => 'Selesai ditangani',
            self::MENUNGGU_PENGHAPUSAN => 'Menunggu penghapusan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DILAPORKAN => 'bg-yellow-100 text-yellow-800',
            self::DIPERIKSA => 'bg-sky-100 text-sky-800',
            self::DALAM_PERBAIKAN => 'bg-blue-100 text-blue-800',
            self::SELESAI => 'bg-green-100 text-green-800',
            self::MENUNGGU_PENGHAPUSAN => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Transisi status yang diizinkan. Tahap persetujuan perbaikan belum
     * diaktifkan; MENUNGGU_PENGHAPUSAN hanya penanda agar proses penghapusan
     * resmi tetap dijalankan terpisah.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DILAPORKAN => [self::DIPERIKSA, self::DALAM_PERBAIKAN, self::SELESAI, self::MENUNGGU_PENGHAPUSAN],
            self::DIPERIKSA => [self::DALAM_PERBAIKAN, self::SELESAI, self::MENUNGGU_PENGHAPUSAN],
            self::DALAM_PERBAIKAN => [self::SELESAI, self::MENUNGGU_PENGHAPUSAN, self::DIPERIKSA],
            self::SELESAI => [],
            self::MENUNGGU_PENGHAPUSAN => [self::SELESAI],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
