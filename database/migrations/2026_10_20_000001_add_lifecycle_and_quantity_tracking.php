<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Kuantitas unit yang tidak dapat dipinjam lagi karena rusak / hilang.
            $table->unsignedInteger('qty_damaged')->default(0)->after('quantity');
            $table->unsignedInteger('qty_lost')->default(0)->after('qty_damaged');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('handed_over_at')->nullable()->after('returned_by');
            $table->foreignId('handed_over_by')->nullable()->after('handed_over_at')->constrained('users');
            $table->timestamp('cancelled_at')->nullable()->after('handed_over_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users');
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index(['status', 'start_date', 'due_date'], 'loans_status_period_index');
        });

        Schema::table('loan_items', function (Blueprint $table) {
            // Akumulasi unit yang sudah kembali dalam kondisi rusak.
            $table->unsignedInteger('qty_damaged')->default(0)->after('qty_returned');
            // Selisih yang belum kembali (pending). Dipindahkan ke items.qty_lost saat pinjaman ditutup.
            $table->unsignedInteger('qty_lost')->default(0)->after('qty_damaged');
        });

        Schema::table('write_offs', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('notifications_app', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_user_read_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_app', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_index');
        });

        Schema::table('write_offs', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });

        Schema::table('loan_items', function (Blueprint $table) {
            $table->dropColumn(['qty_damaged', 'qty_lost']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_status_period_index');
            $table->dropConstrainedForeignId('handed_over_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['handed_over_at', 'cancelled_at', 'cancel_reason']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['qty_damaged', 'qty_lost']);
        });
    }
};
