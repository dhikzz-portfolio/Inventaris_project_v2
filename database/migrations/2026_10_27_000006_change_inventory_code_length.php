<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_units', function (Blueprint $table) {
            $table->string('inventory_code', 12)->change();
        });
        Schema::table('asset_unit_codes', function (Blueprint $table) {
            $table->string('inventory_code', 12)->change();
        });
        Schema::table('asset_unit_movements', function (Blueprint $table) {
            $table->string('old_code', 12)->change();
            $table->string('new_code', 12)->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_units', function (Blueprint $table) {
            $table->string('inventory_code', 8)->change();
        });
        Schema::table('asset_unit_codes', function (Blueprint $table) {
            $table->string('inventory_code', 8)->change();
        });
        Schema::table('asset_unit_movements', function (Blueprint $table) {
            $table->string('old_code', 8)->change();
            $table->string('new_code', 8)->change();
        });
    }
};
