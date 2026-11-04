<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor unit tidak lagi dibatasi 99. Tipe `unsignedTinyInteger` hanya
     * menampung sampai 255, sehingga dinaikkan ke `unsignedInteger` agar
     * penomoran per ruangan benar-benar tanpa batas praktis.
     */
    public function up(): void
    {
        Schema::table('asset_units', function (Blueprint $table): void {
            $table->unsignedInteger('unit_no')->change();
            $table->string('inventory_code', 32)->change();
        });

        Schema::table('asset_unit_codes', function (Blueprint $table): void {
            $table->unsignedInteger('unit_no')->change();
            $table->string('inventory_code', 32)->change();
        });

        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->string('old_code', 32)->change();
            $table->string('new_code', 32)->change();
        });

        if (Schema::hasTable('distribution_units')) {
            Schema::table('distribution_units', function (Blueprint $table): void {
                $table->string('old_code', 32)->change();
                $table->string('new_code', 32)->change();
            });
        }
    }

    /**
     * Rollback hanya aman bila belum ada nomor unit di atas 255 atau kode
     * inventaris lebih panjang dari batas kolom lama.
     */
    public function down(): void
    {
        if (Schema::hasTable('distribution_units')) {
            Schema::table('distribution_units', function (Blueprint $table): void {
                $table->string('old_code', 20)->change();
                $table->string('new_code', 20)->change();
            });
        }

        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->string('old_code', 16)->change();
            $table->string('new_code', 16)->change();
        });

        Schema::table('asset_unit_codes', function (Blueprint $table): void {
            $table->unsignedTinyInteger('unit_no')->change();
            $table->string('inventory_code', 16)->change();
        });

        Schema::table('asset_units', function (Blueprint $table): void {
            $table->unsignedTinyInteger('unit_no')->change();
            $table->string('inventory_code', 16)->change();
        });
    }
};
