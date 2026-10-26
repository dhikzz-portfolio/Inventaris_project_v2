<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status kegiatan disimpan di database, bukan ditampilkan statis:
     * BELUM_BERJALAN → SEDANG_BERJALAN → SUDAH_BERJALAN.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (! Schema::hasColumn('events', 'status')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('status', 30)->default('BELUM_BERJALAN')->after('end_date');
            });
        }

        if (! Schema::hasColumn('events', 'updated_by')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            });
        }

        // Kegiatan lama mengikuti tanggal pelaksanaan agar status awalnya masuk akal.
        DB::table('events')->whereNull('status')->update(['status' => 'BELUM_BERJALAN']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('events', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
