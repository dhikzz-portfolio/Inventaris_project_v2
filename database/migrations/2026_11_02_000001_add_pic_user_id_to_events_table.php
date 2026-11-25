<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanggung jawab kegiatan dapat berupa akun pengguna terdaftar atau nama
     * khusus (panitia/organisasi). `pic_name` tetap menyimpan teks tampilan;
     * `pic_user_id` menyimpan relasi bila penanggung jawab adalah akun.
     */
    public function up(): void
    {
        if (! Schema::hasTable('events') || Schema::hasColumn('events', 'pic_user_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('pic_user_id')
                ->nullable()
                ->after('pic_name')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'pic_user_id')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pic_user_id');
        });
    }
};
