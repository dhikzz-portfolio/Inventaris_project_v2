<?php

namespace App\Services;

use App\Models\InstitutionSetting;
use App\Models\Item;

class IdGenerator
{
    /**
     * ID Internal/Spasial — contoh: MEJA-G1-01-01
     * (nama barang, gedung-lantai-ruangan, urutan barang ke- di ruangan).
     */
    public static function internalId(string $itemName, string $roomCode, ?int $excludeItemId = null): string
    {
        $words = preg_split('/\s+/', trim($itemName));
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $words[0] ?? 'BRG'));
        $prefix = $prefix !== '' ? $prefix : 'BRG';

        $seq = 1;
        do {
            $candidate = sprintf('%s-%s-%02d', $prefix, $roomCode, $seq);
            $exists = Item::where('internal_id', $candidate)
                ->when($excludeItemId, fn ($q) => $q->where('id', '!=', $excludeItemId))
                ->exists();
            $seq++;
        } while ($exists);

        return $candidate;
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
        $roomCode = $item->room()->value('code') ?? $item->room?->code;
        $newId = self::internalId($item->name, $roomCode, $item->id);
        if ($newId !== $item->internal_id) {
            $item->internal_id = $newId;
            $item->save();
        }

        return $newId;
    }
}
