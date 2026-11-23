<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu unit fisik hanya boleh berada pada satu pengajuan penghapusan aktif.
     *
     * Unique `(asset_unit_id, is_active)` memanfaatkan perilaku MySQL/SQLite yang
     * mengizinkan banyak nilai NULL: unit yang pengajuannya ditolak mendapat
     * `is_active = NULL` sehingga boleh diajukan kembali, sedangkan pengajuan
     * PENDING/APPROVED memakai `is_active = 1` dan tidak dapat diduplikasi.
     */
    public function up(): void
    {
        if (! Schema::hasTable('write_off_units') || Schema::hasColumn('write_off_units', 'is_active')) {
            return;
        }

        Schema::table('write_off_units', function (Blueprint $table) {
            $table->boolean('is_active')->nullable()->after('asset_unit_id');
        });

        $rows = DB::table('write_off_units')
            ->join('write_offs', 'write_offs.id', '=', 'write_off_units.write_off_id')
            ->select('write_off_units.id', 'write_off_units.asset_unit_id', 'write_offs.status')
            ->orderBy('write_off_units.id')
            ->get();

        // Data lama: pertahankan pengajuan terbaru sebagai aktif, tandai baris
        // lain sebagai non-aktif tanpa menghapus histori.
        $keepers = $rows
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->groupBy('asset_unit_id')
            ->map(fn ($group) => $group->max('id'))
            ->all();

        foreach ($rows as $row) {
            DB::table('write_off_units')->where('id', $row->id)->update([
                'is_active' => (int) ($keepers[$row->asset_unit_id] ?? 0) === (int) $row->id ? 1 : null,
            ]);
        }

        Schema::table('write_off_units', function (Blueprint $table) {
            $table->unique(['asset_unit_id', 'is_active'], 'write_off_units_unit_active_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('write_off_units') || ! Schema::hasColumn('write_off_units', 'is_active')) {
            return;
        }

        Schema::table('write_off_units', function (Blueprint $table) {
            $table->dropUnique('write_off_units_unit_active_unique');
            $table->dropColumn('is_active');
        });
    }
};
