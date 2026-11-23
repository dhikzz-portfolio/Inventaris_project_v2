<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan kondisi barang rusak. Tabel ini hanya mencatat dan memantau kondisi;
 * tidak menghapus unit, tidak mengurangi stok, dan tidak membuat transaksi
 * penghapusan. Penghapusan tetap memakai alur `write_offs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_unit_id')->constrained('asset_units')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            // RUSAK_RINGAN | RUSAK_BERAT
            $table->string('classification');
            // Kondisi unit sebelum dan sesudah laporan — riwayat tidak ditimpa.
            $table->string('previous_condition');
            $table->string('reported_condition');
            $table->text('description');
            $table->string('photo_path')->nullable();
            // DILAPORKAN | DIPERIKSA | DALAM_PERBAIKAN | SELESAI | MENUNGGU_PENGHAPUSAN
            $table->string('status')->default('DILAPORKAN');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_unit_id', 'status'], 'damage_reports_unit_status_index');
            $table->index(['classification', 'status'], 'damage_reports_classification_status_index');
        });

        Schema::create('damage_report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damage_report_id')->constrained('damage_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_report_logs');
        Schema::dropIfExists('damage_reports');
    }
};
