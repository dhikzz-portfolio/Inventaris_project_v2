<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi V8.
 *
 * - Kondisi fisik dan keterangan disimpan per alokasi ruangan, bukan satu
 *   nilai global untuk seluruh pengadaan.
 * - Tahun perolehan dan sumber dana dapat berbeda antar kelompok spesifikasi
 *   pada katalog yang sama.
 * - Laporan kerusakan otomatis dapat ditautkan ke alokasi pengadaan sumbernya.
 * - Kewajiban ganti password pada login pertama dinonaktifkan; flag lama
 *   dibersihkan agar akun yang sudah ada tidak tetap dialihkan ke profil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acquisition_allocations')) {
            if (! Schema::hasColumn('acquisition_allocations', 'condition')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->string('condition', 20)->nullable()->after('received_quantity');
                });
            }

            if (! Schema::hasColumn('acquisition_allocations', 'note')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->text('note')->nullable()->after('condition');
                });
            }

            if (! Schema::hasColumn('acquisition_allocations', 'acquisition_year')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->unsignedSmallInteger('acquisition_year')->nullable()->after('note');
                });
            }

            if (! Schema::hasColumn('acquisition_allocations', 'funding_source')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->string('funding_source', 100)->nullable()->after('acquisition_year');
                });
            }
        }

        if (Schema::hasTable('damage_reports') && ! Schema::hasColumn('damage_reports', 'acquisition_allocation_id')) {
            Schema::table('damage_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('acquisition_allocation_id')->nullable()->after('asset_unit_id');
            });

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('damage_reports', function (Blueprint $table) {
                    $table->foreign('acquisition_allocation_id', 'damage_reports_allocation_fk')
                        ->references('id')->on('acquisition_allocations')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'must_change_password')) {
            DB::table('users')->where('must_change_password', true)->update(['must_change_password' => false]);
        }

        // Cakupan Operator lama yang menunjuk laboratorium jurusan lain (atau
        // laboratorium tanpa jurusan) tidak sah menurut aturan V8. Hanya baris
        // yang jelas melanggar yang dibersihkan; operator tanpa major_id dan
        // data inventaris/transaksi tidak disentuh.
        if (Schema::hasTable('user_room_scopes')
            && Schema::hasTable('users')
            && Schema::hasTable('rooms')
            && Schema::hasColumn('users', 'major_id')
            && Schema::hasColumn('rooms', 'major_id')) {
            $invalidScopeIds = DB::table('user_room_scopes as scope')
                ->join('users', 'users.id', '=', 'scope.user_id')
                ->join('rooms', 'rooms.id', '=', 'scope.room_id')
                ->where('users.role', 'OPERATOR')
                ->whereNotNull('users.major_id')
                ->where(function ($query): void {
                    $query->whereNull('rooms.major_id')
                        ->orWhereColumn('rooms.major_id', '!=', 'users.major_id');
                })
                ->pluck('scope.id');

            if ($invalidScopeIds->isNotEmpty()) {
                DB::table('user_room_scopes')->whereIn('id', $invalidScopeIds)->delete();
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('damage_reports') && Schema::hasColumn('damage_reports', 'acquisition_allocation_id')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('damage_reports', function (Blueprint $table) {
                    $table->dropForeign('damage_reports_allocation_fk');
                });
            }

            Schema::table('damage_reports', function (Blueprint $table) {
                $table->dropColumn('acquisition_allocation_id');
            });
        }

        if (Schema::hasTable('acquisition_allocations')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->dropColumn(['condition', 'note', 'acquisition_year', 'funding_source']);
            });
        }
    }
};
