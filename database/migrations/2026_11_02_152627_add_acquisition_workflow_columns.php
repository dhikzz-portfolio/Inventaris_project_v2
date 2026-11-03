<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur pengadaan tiga tahap: Laboran → Ketua Laboran → Wakasek Sarpras.
 *
 * Nilai status baru ditambahkan tanpa menghapus nilai lama. Pengajuan yang
 * masih berstatus PENDING pada data lama dianggap sudah dikirim ke Wakasek
 * (alur dua tahap sebelumnya), sehingga dipetakan ke MENUNGGU_PERSETUJUAN
 * tanpa kehilangan riwayat. Nilai APPROVED lama tetap dipertahankan sebagai
 * alias "sudah tercatat sebagai inventaris" agar unit lama tidak diproses ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_catalogs', function (Blueprint $table) {
            $table->timestamp('acquisition_submitted_at')->nullable();
            $table->foreignId('acquisition_confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('acquisition_confirmed_at')->nullable();
            $table->timestamp('acquisition_started_at')->nullable();
            $table->foreignId('acquisition_received_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('acquisition_received_at')->nullable();
            $table->unsignedInteger('acquisition_received_quantity')->nullable();
            $table->string('acquisition_received_condition')->nullable();
            $table->text('acquisition_received_note')->nullable();
        });

        Schema::table('acquisition_allocations', function (Blueprint $table) {
            // Jumlah aktual yang benar-benar diterima per lokasi. Null berarti
            // belum diterima; selisih terhadap `quantity` disimpan apa adanya.
            $table->unsignedInteger('received_quantity')->nullable();
        });

        // Role baru harus tersedia tanpa menunggu seeder dijalankan ulang.
        if (Schema::hasTable('roles')) {
            DB::table('roles')->insertOrIgnore([
                'name' => 'SUPER_ADMIN',
                'guard_name' => 'web',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Data lama: pengajuan PENDING sebelumnya langsung menunggu Wakasek.
        DB::table('item_catalogs')
            ->where('acquisition_status', 'PENDING')
            ->update([
                'acquisition_status' => 'MENUNGGU_PERSETUJUAN',
                'acquisition_submitted_at' => DB::raw('created_at'),
            ]);

        // Data lama yang sudah disetujui dianggap sudah menjadi inventaris.
        DB::table('item_catalogs')
            ->where('acquisition_status', 'APPROVED')
            ->whereNull('acquisition_received_at')
            ->update(['acquisition_received_at' => DB::raw('acquisition_decided_at')]);
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'SUPER_ADMIN')->delete();
        }

        DB::table('item_catalogs')
            ->where('acquisition_status', 'MENUNGGU_PERSETUJUAN')
            ->update(['acquisition_status' => 'PENDING']);

        Schema::table('acquisition_allocations', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });

        Schema::table('item_catalogs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acquisition_confirmed_by');
            $table->dropConstrainedForeignId('acquisition_received_by');
            $table->dropColumn([
                'acquisition_submitted_at',
                'acquisition_confirmed_at',
                'acquisition_started_at',
                'acquisition_received_at',
                'acquisition_received_quantity',
                'acquisition_received_condition',
                'acquisition_received_note',
            ]);
        });
    }
};
