<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sidik jari perubahan data untuk fitur refresh otomatis.
 *
 * Halaman membandingkan sidik jari ini secara berkala; halaman hanya dimuat
 * ulang ketika sidik jari berubah, bukan setiap kali polling. Perhitungan
 * disimpan singkat di cache agar beberapa pengguna tidak menghitung ulang.
 */
final class LiveDataVersion
{
    public const CACHE_KEY = 'live-data-version';

    /**
     * Tabel yang perubahannya memengaruhi isi halaman.
     *
     * @var list<string>
     */
    private const TABLES = [
        'item_catalogs',
        'asset_units',
        'asset_unit_movements',
        'acquisition_allocations',
        'procurement_receipts',
        'loans',
        'loan_item_units',
        'write_offs',
        'write_off_units',
        'damage_reports',
        'distributions',
        'distribution_units',
        'events',
        'specifications',
        'rooms',
        'categories',
        'majors',
        'buildings',
        'users',
    ];

    public function current(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(5), function (): string {
            $signature = [];

            foreach (self::TABLES as $table) {
                $signature[$table] = (array) DB::table($table)
                    ->selectRaw('COUNT(*) as total, MAX(updated_at) as changed_at, MAX(id) as latest_id')
                    ->first();
            }

            return hash('xxh128', (string) json_encode($signature));
        });
    }
}
