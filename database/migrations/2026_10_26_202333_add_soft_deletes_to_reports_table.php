<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penghapusan arsip laporan memakai soft delete agar riwayat audit tetap
     * ada. File PDF/Excel dihapus terpisah dan data inventaris sumber tidak
     * pernah ikut terhapus.
     */
    public function up(): void
    {
        if (Schema::hasTable('reports') && ! Schema::hasColumn('reports', 'deleted_at')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'deleted_at')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
