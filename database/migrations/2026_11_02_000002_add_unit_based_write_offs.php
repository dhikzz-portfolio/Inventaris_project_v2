<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penghapusan inventaris dapat berupa unit individual atau seluruh katalog.
     * `write_off_units` menyimpan snapshot identitas unit pada saat pengajuan
     * agar histori tetap terbaca meskipun unit/lokasi berubah kemudian.
     */
    public function up(): void
    {
        if (Schema::hasTable('write_offs') && ! Schema::hasColumn('write_offs', 'mode')) {
            Schema::table('write_offs', function (Blueprint $table) {
                $table->string('mode', 20)->default('CATALOG')->after('quantity');
            });
        }

        if (Schema::hasTable('write_off_units')) {
            return;
        }

        Schema::create('write_off_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('write_off_id')->constrained('write_offs')->cascadeOnDelete();
            $table->foreignId('asset_unit_id')->constrained('asset_units')->restrictOnDelete();
            $table->foreignId('item_catalog_id')->constrained('item_catalogs');
            $table->foreignId('specification_id')->nullable()->constrained('specifications')->nullOnDelete();
            $table->string('inventory_code', 12);
            $table->string('brand', 100)->nullable();
            $table->string('model_type', 100)->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('condition', 30)->nullable();
            $table->string('status_before', 30)->nullable();
            $table->timestamps();

            // Satu unit tidak boleh tercatat dua kali dalam pengajuan yang sama.
            $table->unique(['write_off_id', 'asset_unit_id']);
            $table->index(['asset_unit_id']);
            $table->index(['item_catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_off_units');

        if (Schema::hasTable('write_offs') && Schema::hasColumn('write_offs', 'mode')) {
            Schema::table('write_offs', function (Blueprint $table) {
                $table->dropColumn('mode');
            });
        }
    }
};
