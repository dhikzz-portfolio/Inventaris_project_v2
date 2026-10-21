<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi lama bertipe STOK_MENIPIS tidak lagi dikenali enum NotificationType.
        DB::table('notifications_app')->where('type', 'STOK_MENIPIS')->delete();

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('min_stock');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_consumable');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_consumable')->default(false)->after('code');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('min_stock')->nullable()->after('unit');
        });
    }
};
