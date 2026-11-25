<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data gedung dipisah dari master data ruangan.
     *
     * - `buildings` menyimpan identitas gedung (nama + kode/huruf) yang menjadi
     *   sumber kebenaran generator kode lokasi & kode inventaris.
     * - `rooms.building_id` menautkan ruangan ke master gedung.
     * - `rooms.legacy_building` menyimpan teks gedung lama (mis. "G1") hanya
     *   untuk penelusuran historis; nilainya tidak lagi dipakai membentuk kode.
     *
     * Kode inventaris lama tidak berubah: `rooms.building_letter` tetap
     * bernilai sama seperti sebelum migrasi.
     */
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        if (! Schema::hasTable('buildings')) {
            Schema::create('buildings', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->char('code', 1)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('rooms', 'building') && ! Schema::hasColumn('rooms', 'legacy_building')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('building', 'legacy_building');
            });
        }

        // Teks gedung lama menjadi data historis saja — identitas gedung kini
        // diambil dari master gedung, sehingga kolom ini boleh kosong.
        if (Schema::hasColumn('rooms', 'legacy_building')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->string('legacy_building')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('rooms', 'building_id')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->foreignId('building_id')->nullable()->after('major_id')->constrained('buildings')->nullOnDelete();
            });
        }

        $this->backfillBuildings();
    }

    /**
     * Bangun master gedung dari data ruangan yang sudah ada.
     *
     * Identitas gedung diambil dari `rooms.building_letter` (huruf yang sudah
     * dipakai untuk kode inventaris) sehingga kode lama tidak berubah.
     * Ruangan tanpa huruf gedung memakai huruf pertama dari teks gedung lama.
     */
    private function backfillBuildings(): void
    {
        if (! Schema::hasTable('buildings')) {
            return;
        }

        $rooms = DB::table('rooms')->orderBy('id')->get();

        foreach ($rooms as $room) {
            $letter = strtoupper(trim((string) ($room->building_letter ?? '')));

            if ($letter === '') {
                $legacy = trim((string) ($room->legacy_building ?? ''));
                $letters = preg_replace('/[^A-Za-z]/', '', $legacy);
                $letter = $letters !== null && $letters !== '' ? strtoupper($letters[0]) : '';
            }

            if ($letter === '' || ! preg_match('/^[A-Z]$/', $letter)) {
                continue;
            }

            $building = DB::table('buildings')->where('code', $letter)->first();

            if ($building === null) {
                // Nama gedung mengikuti identitas kodenya (mis. "Gedung C" → C).
                $name = 'Gedung '.$letter;

                // Nama gedung harus unik; hindari bentrok dengan nama yang sudah ada.
                $base = $name;
                $suffix = 1;
                while (DB::table('buildings')->where('name', $name)->exists()) {
                    $name = $base.' '.(++$suffix);
                }

                $buildingId = DB::table('buildings')->insertGetId([
                    'name' => $name,
                    'code' => $letter,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $buildingId = $building->id;
            }

            DB::table('rooms')->where('id', $room->id)->update([
                'building_id' => $buildingId,
                'building_letter' => $letter,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'building_id')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropConstrainedForeignId('building_id');
            });
        }

        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'legacy_building')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('legacy_building', 'building');
            });
        }

        Schema::dropIfExists('buildings');
    }
};
