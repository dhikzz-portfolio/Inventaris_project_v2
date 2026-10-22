<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status dokumen peminjaman (surat peminjaman / BAST) dipisahkan dari
     * status transaksi peminjaman.
     *
     * Status dokumen tidak menentukan kelayakan transaksi: dokumen yang belum
     * dibuat atau belum ditandatangani tidak memblokir serah terima, dan
     * pencetakan dokumen tidak membuat transaksi menjadi disetujui.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loans')) {
            return;
        }

        if (! Schema::hasColumn('loans', 'document_status')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->string('document_status', 30)->default('BELUM_DIBUAT')->after('bast_path');
            });
        }

        // Transaksi lama tanpa status dokumen diperlakukan sebagai "belum dibuat";
        // statusnya tidak diubah menjadi disetujui/ditandatangani.
        DB::table('loans')->whereNull('document_status')->update(['document_status' => 'BELUM_DIBUAT']);
    }

    public function down(): void
    {
        if (Schema::hasTable('loans') && Schema::hasColumn('loans', 'document_status')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropColumn('document_status');
            });
        }
    }
};
