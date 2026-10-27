<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('item_catalogs', 'item_type_code')) {
                $table->string('item_type_code', 3)->nullable()->unique()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('item_catalogs', 'item_type_code')) {
                $table->dropColumn('item_type_code');
            }
        });
    }
};
