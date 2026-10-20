<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('internal_id')->unique(); // ID Internal/Spasial, contoh: MEJA-G1-01-01
            $table->string('location_code');         // Standar aset baris 1: Kode Lokasi/Instansi
            $table->string('asset_code');            // Standar aset baris 2: Kode Barang (klasifikasi)
            $table->string('register_no');           // Nomor Register
            $table->string('name');
            $table->foreignId('category_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->text('specification')->nullable();
            $table->unsignedSmallInteger('acquisition_year');
            $table->string('funding_source');        // contoh: BOS, komite, hibah
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->default('unit'); // satuan: unit, pak, lembar, dll
            $table->unsignedInteger('min_stock')->nullable(); // ambang alert stok menipis
            $table->boolean('is_high_value')->default(false); // wajib approval berjenjang
            $table->string('status')->default('BAIK'); // BAIK, RUSAK_RINGAN, RUSAK_BERAT, HILANG, DIPINJAM, DIHAPUS
            $table->string('photo_path')->nullable();
            $table->timestamp('last_label_printed_at')->nullable();
            $table->timestamps();
            $table->unique(['asset_code', 'register_no']);
        });

        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('from_room_id')->constrained('rooms');
            $table->foreignId('to_room_id')->constrained('rooms');
            $table->foreignId('moved_by')->constrained('users');
            $table->unsignedInteger('quantity')->default(1); // sebagian (untuk barang habis pakai)
            $table->string('note')->nullable();
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_movements');
        Schema::dropIfExists('items');
    }
};
