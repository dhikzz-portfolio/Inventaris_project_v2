<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `asset_units.inventory_code` sudah diperlebar ke 32 karakter, tetapi
     * snapshot pada `write_off_units` tertinggal di 12 karakter sehingga
     * pengajuan penghapusan unit dengan nomor 100 ke atas gagal disimpan.
     */
    public function up(): void
    {
        if (Schema::hasTable('write_off_units') && Schema::hasColumn('write_off_units', 'inventory_code')) {
            Schema::table('write_off_units', function (Blueprint $table): void {
                $table->string('inventory_code', 32)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('write_off_units') && Schema::hasColumn('write_off_units', 'inventory_code')) {
            Schema::table('write_off_units', function (Blueprint $table): void {
                $table->string('inventory_code', 12)->change();
            });
        }
    }
};
