<?php

namespace App\Enums;

/**
 * Status pengadaan. Nilai `APPROVED` dipertahankan sebagai alias data lama
 * ("sudah tercatat sebagai inventaris") agar katalog lama tidak berubah.
 */
enum AcquisitionStatus: string
{
    case DRAFT = 'DRAFT';
    case MENUNGGU_KONFIRMASI = 'MENUNGGU_KONFIRMASI';
    case MENUNGGU_PERSETUJUAN = 'MENUNGGU_PERSETUJUAN';
    case DISETUJUI = 'DISETUJUI';
    case DALAM_PROSES = 'DALAM_PROSES';
    case BARANG_DITERIMA = 'BARANG_DITERIMA';
    case INVENTARIS_TERCATAT = 'INVENTARIS_TERCATAT';
    case DITOLAK = 'DITOLAK';

    /** Nilai lama: pengadaan sudah disetujui dan unitnya sudah terbit. */
    case LEGACY_APPROVED = 'APPROVED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft (belum diajukan)',
            self::MENUNGGU_KONFIRMASI => 'Menunggu validasi Editor/Validator',
            self::MENUNGGU_PERSETUJUAN => 'Menunggu persetujuan Admin',
            self::DISETUJUI => 'Disetujui Admin',
            self::DALAM_PROSES => 'Sedang diproses pengadaan',
            self::BARANG_DITERIMA => 'Barang diterima',
            self::INVENTARIS_TERCATAT, self::LEGACY_APPROVED => 'Inventaris tercatat',
            self::DITOLAK => 'Ditolak',
        };
    }

    /**
     * Keterangan singkat siapa yang sedang memegang pengajuan, dipakai pada
     * daftar pengadaan dan halaman detail barang.
     */
    public function hint(): string
    {
        return match ($this) {
            self::DRAFT => 'Pengajuan masih berupa draft dan belum dikirim ke Editor/Validator.',
            self::MENUNGGU_KONFIRMASI => 'Pengajuan sedang diajukan dan menunggu validasi Editor/Validator.',
            self::MENUNGGU_PERSETUJUAN => 'Pengajuan sudah divalidasi dan sedang menunggu persetujuan Admin.',
            self::DISETUJUI => 'Pengajuan disetujui Admin. Proses pengadaan dapat dimulai.',
            self::DALAM_PROSES => 'Barang sedang dalam proses pengadaan.',
            self::BARANG_DITERIMA => 'Barang sudah diterima dan menunggu pencatatan inventaris atau distribusi.',
            self::INVENTARIS_TERCATAT, self::LEGACY_APPROVED => 'Pengadaan selesai dan barang sudah tercatat sebagai inventaris.',
            self::DITOLAK => 'Pengajuan ditolak. Lihat alasan penolakan pada daftar pengadaan.',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-slate-100 text-slate-700',
            self::MENUNGGU_KONFIRMASI => 'bg-amber-100 text-amber-800',
            self::MENUNGGU_PERSETUJUAN => 'bg-yellow-100 text-yellow-800',
            self::DISETUJUI => 'bg-sky-100 text-sky-800',
            self::DALAM_PROSES => 'bg-blue-100 text-blue-800',
            self::BARANG_DITERIMA => 'bg-indigo-100 text-indigo-800',
            self::INVENTARIS_TERCATAT, self::LEGACY_APPROVED => 'bg-green-100 text-green-800',
            self::DITOLAK => 'bg-red-100 text-red-800',
        };
    }

    /** Sudah menjadi inventaris resmi (unit fisik boleh dipakai). */
    public function isRecorded(): bool
    {
        return in_array($this, [self::INVENTARIS_TERCATAT, self::LEGACY_APPROVED], true);
    }

    /** Nilai yang boleh disimpan di kolom `acquisition_status`. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Nilai yang menandakan katalog sudah boleh dipakai transaksi inventaris. */
    public static function recordedValues(): array
    {
        return [self::INVENTARIS_TERCATAT->value, self::LEGACY_APPROVED->value];
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::MENUNGGU_KONFIRMASI,
            self::MENUNGGU_PERSETUJUAN,
            self::DISETUJUI,
            self::DALAM_PROSES,
            self::BARANG_DITERIMA,
        ], true);
    }
}
