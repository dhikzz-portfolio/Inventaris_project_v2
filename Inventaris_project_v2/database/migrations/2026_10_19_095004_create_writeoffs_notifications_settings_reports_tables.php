<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('reason');
            $table->string('berita_acara_path');   // unggahan Berita Acara
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');       // TERLAMBAT, STOK_MENIPIS, PERSETUJUAN, PENGEMBALIAN, LAINNYA
            $table->string('message');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name'); // footer label, contoh: SMK NEGERI 1 ...
            $table->string('location_code');    // Kode Lokasi/Instansi default
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('headmaster_name')->nullable(); // Kepala sekolah (untuk BAST)
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('period');            // YYYY-MM
            $table->string('scope')->default('SEMUA'); // SEMUA, KATEGORI, RUANGAN
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('excel_path')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('institution_settings');
        Schema::dropIfExists('notifications_app');
        Schema::dropIfExists('write_offs');
    }
};
