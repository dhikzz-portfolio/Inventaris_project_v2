<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_item_units', function (Blueprint $table) {
            $table->string('return_status', 30)
                ->default('BELUM_DIPERIKSA')
                ->after('condition_in');
            $table->text('return_notes')->nullable()->after('return_status');
            $table->foreignId('confirmed_by')
                ->nullable()
                ->after('return_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
        });

        // Data lama yang sudah kembali tetap dianggap terkonfirmasi.
        DB::table('loan_item_units')
            ->whereNotNull('returned_at')
            ->update([
                'return_status' => 'SUDAH_DIKONFIRMASI',
                'confirmed_at' => DB::raw('returned_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('loan_item_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['return_status', 'return_notes', 'confirmed_at']);
        });
    }
};
