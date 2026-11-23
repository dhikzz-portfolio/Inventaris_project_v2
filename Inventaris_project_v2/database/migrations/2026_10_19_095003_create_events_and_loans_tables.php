<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pic_name');   // penanggung jawab
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('users');
            $table->foreignId('event_id')->nullable()->constrained();
            $table->string('purpose');
            $table->dateTime('start_date');
            $table->dateTime('due_date');
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, DIPINJAM, SELESAI, DITOLAK
            $table->boolean('needs_high_value_approval')->default(false);
            $table->string('bast_path')->nullable();      // PDF Surat Peminjaman / BAST
            $table->string('letter_path')->nullable();    // PDF Surat Peminjaman Resmi
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('qty_borrowed');
            $table->unsignedInteger('qty_returned')->nullable(); // kurang dari qty_borrowed: flag HILANG
            $table->string('condition_out');
            $table->string('condition_in')->nullable();          // wajib saat pengembalian
            $table->string('damage_photo_path')->nullable();     // wajib jika kondisi akhir RUSAK
            $table->foreignId('returned_room_id')->nullable()->constrained('rooms');
            $table->timestamps();
        });

        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->unsignedTinyInteger('level');  // 1 = Laboran, 2 = Wakasek
            $table->string('decision');            // APPROVED, REJECTED
            $table->text('note')->nullable();
            $table->string('signature_path')->nullable(); // TTD digital
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_approvals');
        Schema::dropIfExists('loan_items');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('events');
    }
};
