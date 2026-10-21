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
     * Contoh: gedung "G1", lantai 1, ruang "G1-01" → "g1-01-".
     */
    public static function roomPrefix(Room $room): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', (string) $room->building);
        $buildingLetter = $letters !== null && $letters !== '' ? strtolower($letters[0]) : 'g';

        preg_match('/(\d+)\s*$/', (string) $room->code, $matches);
        $roomNo = isset($matches[1]) ? (int) $matches[1] : 1;

        return sprintf('%s%d-%02d-', $buildingLetter, (int) $room->floor, $roomNo);
    }

    /**
     * Baris 1 label: Kode Lokasi/Instansi (default dari pengaturan instansi).
     */
    public static function locationCode(): string
    {
        return InstitutionSetting::current()->location_code;
    }

    /**
     * Baris 2 label: Kode Barang (klasifikasi kategori) + Nomor Register.
     * Contoh gabungan: 01.01.11.04.01.0001
     *
     * @return array{asset_code: string, register_no: string}
     */
    public static function register(?string $categoryCode): array
    {
        $last = (int) Item::max('register_no');

        $registerNo = str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
        $assetCode = $categoryCode ?: '00.00.00.00.00';

        return [
            'asset_code' => $assetCode,
            'register_no' => $registerNo,
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
