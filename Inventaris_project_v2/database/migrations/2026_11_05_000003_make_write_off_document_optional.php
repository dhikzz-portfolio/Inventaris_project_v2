<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berita acara penghapusan tidak lagi wajib, sehingga path-nya harus
     * dapat bernilai kosong tanpa menghapus data dokumen yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('write_offs', function (Blueprint $table) {
            $table->string('berita_acara_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('write_offs', function (Blueprint $table) {
            $table->string('berita_acara_path')->nullable(false)->change();
        });
    }
};
