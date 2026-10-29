<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus username dari seluruh sistem autentikasi.
     *
     * - `users.username` dihapus setelah seluruh dependensi (form, login,
     *   seeder, factory, test) beralih ke email.
     * - `password_reset_tokens.username` diubah menjadi `email` karena
     *   broker password reset Laravel selalu mengkunci kolom `email`.
     *
     * Data lama tidak dihapus: akun tetap teridentifikasi oleh `id` dan
     * `email` yang sudah ada pada setiap baris.
     */
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'username')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dropPrimary();
                $table->renameColumn('username', 'email');
                $table->primary('email');
            });
        }

        // Unique index harus dihapus lebih dulu: SQLite menolak drop kolom
        // yang masih dirujuk oleh index unik.
        $userIndexes = collect(Schema::getIndexes('users'))->pluck('name');

        Schema::table('users', function (Blueprint $table) use ($userIndexes) {
            if ($userIndexes->contains('users_username_unique')) {
                $table->dropUnique('users_username_unique');
            }

            $table->dropColumn('username');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
        });

        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'email')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dropPrimary();
                $table->renameColumn('email', 'username');
                $table->primary('username');
            });
        }
    }
};
