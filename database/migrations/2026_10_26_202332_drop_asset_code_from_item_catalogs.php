<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode aset terpisah (`asset_code`) tidak dipakai lagi: kode inventaris
     * unit (`G1-02-12`) adalah satu-satunya identitas barang. Kolom dihapus
     * hanya setelah seluruh tampilan, laporan, label, dan ekspor berhenti
     * memakainya. `register_no` dipertahankan sebagainomor register
     * administratif opsional, bukan kode aset kedua.
     */
    public function up(): void
    {
        if (! Schema::hasTable('item_catalogs') || ! Schema::hasColumn('item_catalogs', 'asset_code')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('item_catalogs'));

        $legacyIndex = $indexes->first(fn (array $index) => ($index['columns'] ?? []) === ['asset_code', 'register_no']);

        if ($legacyIndex !== null) {
            Schema::table('item_catalogs', function (Blueprint $table) use ($legacyIndex) {
                $table->dropUnique($legacyIndex['name']);
            });
        }

        Schema::table('item_catalogs', function (Blueprint $table) {
            $table->dropColumn('asset_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_catalogs') || Schema::hasColumn('item_catalogs', 'asset_code')) {
            return;
        }

        Schema::table('item_catalogs', function (Blueprint $table) {
            $table->string('asset_code', 50)->nullable()->after('location_code');
        });
    }
};
