<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field profil untuk registrasi mandiri:
     * - `email` wajib diisi akun baru (domain gmail.com divalidasi aplikasi),
     *   tetapi tetap nullable agar akun lama/seeder yang belum punya email
     *   tidak kehilangan akses.
     * - `degree` menyimpan gelar terpisah dari nama lengkap.
     * - `class_name` menyimpan kelas siswa untuk identitas surat peminjaman.
     * - `email_verified_at` disediakan untuk verifikasi email bila SMTP siap.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'degree')) {
                $table->string('degree', 100)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'class_name')) {
                $table->string('class_name', 50)->nullable()->after('major_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['class_name', 'degree', 'email_verified_at', 'email'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
