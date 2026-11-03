<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor mutasi mengelompokkan beberapa unit yang dipindahkan dalam satu
 * transaksi. Struktur detail per unit (from_room_id/to_room_id) sudah ada;
 * kolom ini hanya menambahkan identitas transaksi tanpa tabel header baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('asset_unit_movements', 'movement_no')) {
            return;
        }

        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->string('movement_no', 40)->nullable()->after('new_code');
            $table->index('movement_no');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asset_unit_movements', 'movement_no')) {
            return;
        }

        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->dropIndex(['movement_no']);
            $table->dropColumn('movement_no');
        });
    }
};
