<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('major_id')->nullable()->constrained();
            $table->string('building');          // contoh: G1
            $table->unsignedTinyInteger('floor'); // lantai
            $table->string('code')->unique();     // contoh: G1-01
            $table->string('name');               // contoh: Lab Komputer 1
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_consumable')->default(false); // barang habis pakai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('categories');
    }
};
