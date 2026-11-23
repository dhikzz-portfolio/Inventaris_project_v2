<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('acquisition_status')->default('APPROVED'); // PENDING, APPROVED, REJECTED
            $table->foreignId('acquisition_requested_by')->nullable()->constrained('users');
            $table->foreignId('acquisition_approved_by')->nullable()->constrained('users');
            $table->timestamp('acquisition_decided_at')->nullable();
            $table->text('acquisition_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acquisition_requested_by');
            $table->dropConstrainedForeignId('acquisition_approved_by');
            $table->dropColumn(['acquisition_status', 'acquisition_decided_at', 'acquisition_note']);
        });
    }
};
