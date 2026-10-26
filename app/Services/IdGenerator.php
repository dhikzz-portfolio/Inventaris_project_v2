<?php

namespace App\Services;

use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;

class IdGenerator
{
    /**
     * ID Internal/Spasial — contoh: g1-01-01
     * (huruf gedung + lantai, ruangan ke-berapa, urutan barang ke- di ruangan).
     */
    public static function internalId(Room $room, ?int $excludeItemId = null): string
    {
        $prefix = self::roomPrefix($room);

        $seq = 1;
        do {
            $candidate = $prefix.str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
            $exists = Item::where('internal_id', $candidate)
                ->when($excludeItemId, fn ($q) => $q->where('id', '!=', $excludeItemId))
                ->exists();
            $seq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Prefix ID internal untuk sebuah ruangan: `{huruf gedung}{lantai}-{ruangan ke}-`.
     * Contoh: gedung "G", lantai 1, ruang "G1-01" → "g1-01-".
     *
     * Huruf gedung berasal dari master gedung (identitas kode), bukan teks bebas.
     */
    public static function roomPrefix(Room $room): string
    {
        $buildingLetter = strtolower($room->buildingCode() ?: 'g');

        return sprintf('%s%d-%02d-', $buildingLetter, (int) $room->floor, (int) $room->room_no);
    }

    /**
     * Baris 1 label: Kode Lokasi/Instansi (default dari pengaturan instansi).
     */
    public static function locationCode(): string
    {
        return InstitutionSetting::current()->location_code;
    }

    /**
     * Nomor register administratif berurutan (bukan kode aset kedua).
     * Kode inventaris unit tetap satu-satunya identitas barang pada label,
     * QR, scan, laporan, dan dokumen.
     *
     * @return array{register_no: string}
     */
    public static function register(?string $categoryCode = null): array
    {
        $last = (int) Item::max('register_no');

        return [
            'register_no' => str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Perbarui ID Internal setelah mutasi ruangan (ID & lokasi ikut berubah otomatis).
     */
    public static function refreshInternalId(Item $item): string
    {
        // Ruangan bisa saja baru diubah (mutasi) sementara relasi masih ter-cache.
        $room = $item->room()->first();
        if ($room === null) {
            return $item->internal_id;
        }

        $newId = self::internalId($room, $item->id);
        if ($newId !== $item->internal_id) {
            $item->internal_id = $newId;
            $item->save();
        }

        return $newId;
    }
}
