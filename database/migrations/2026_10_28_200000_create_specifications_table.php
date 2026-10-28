<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_catalog_id')->constrained('item_catalogs')->cascadeOnDelete();
            $table->string('brand', 100)->nullable()->default('');
            $table->string('model_type', 100)->nullable()->default('');
            $table->text('description')->nullable();
            $table->string('spec_code', 50)->unique();
            $table->timestamps();

            $table->index(['item_catalog_id', 'brand', 'model_type']);
            $table->unique(['item_catalog_id', 'spec_code']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('specifications');
    }
};
