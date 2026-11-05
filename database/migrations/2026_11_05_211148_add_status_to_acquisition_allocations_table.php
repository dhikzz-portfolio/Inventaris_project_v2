<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status operasional awal untuk setiap alokasi pengadaan. Unit yang
     * diterbitkan dari alokasi mewarisi status ini agar barang hilang atau
     * dipinjam dapat dicatat sejak pengadaan.
     */
    public function up(): void
    {
        if (Schema::hasTable('acquisition_allocations')
            && ! Schema::hasColumn('acquisition_allocations', 'status')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->string('status', 20)->default('AKTIF')->after('condition');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acquisition_allocations')
            && Schema::hasColumn('acquisition_allocations', 'status')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
