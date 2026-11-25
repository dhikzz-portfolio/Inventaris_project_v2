<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto bukti kerusakan sebelumnya hanya tersimpan pada baris katalog
     * (`loan_items.damage_photo_path`). Untuk transaksi berbasis unit fisik,
     * bukti harus melekat pada unit yang benar-benar rusak.
     *
     * Kolom ini nullable dan tidak melakukan backfill: data lama tidak boleh
     * dianggap sebagai bukti unit tertentu tanpa kepastian.
     */
    public function up(): void
    {
        Schema::table('loan_item_units', function (Blueprint $table) {
            $table->string('damage_photo_path')->nullable()->after('return_notes');
        });
    }

    public function down(): void
    {
        Schema::table('loan_item_units', function (Blueprint $table) {
            $table->dropColumn('damage_photo_path');
        });
    }
};
