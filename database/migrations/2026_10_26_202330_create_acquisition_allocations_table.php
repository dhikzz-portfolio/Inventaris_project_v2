<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acquisition_allocations')) {
            return;
        }

        Schema::create('acquisition_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_catalog_id')->constrained('item_catalogs')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // Satu ruangan hanya boleh muncul sekali per pengadaan; jumlah
            // tambahan harus diubah pada baris yang sama, bukan diduplikasi.
            $table->unique(['item_catalog_id', 'room_id'], 'acquisition_allocations_catalog_room_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_allocations');
    }
};
