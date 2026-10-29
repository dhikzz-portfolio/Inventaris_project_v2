<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_units', function (Blueprint $table) {
            $table->foreignId('specification_id')->nullable()->after('item_catalog_id')->constrained('specifications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_units', function (Blueprint $table) {
            $table->dropForeign(['specification_id']);
            $table->dropColumn('specification_id');
        });
    }
};
