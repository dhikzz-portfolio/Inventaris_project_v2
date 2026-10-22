<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalisasi kode ruangan & ID internal barang mengikuti master gedung.
     *
     * Kode unit inventaris (mis. `H1-01-03`) sudah memakai identitas master
     * gedung + lantai + nomor ruangan, sementara `rooms.code` masih memakai
     * penamaan lama (mis. `G2-01` untuk gedung H lantai 1 ruang 01). Kali ini
     * kedua skema disatukan:
     *
     * - `rooms.code` dihitung ulang menjadi `{huruf gedung}{lantai}-{nomor ruang}`
     *   sesuai identitas master gedung. Nilai lama disimpan pada
     *   `rooms.previous_code` sebagai prosedur pemulihan.
     * - `item_catalogs.internal_id` ditulis ulang bila prefix ruangannya berubah
     *   agar tetap konsisten dengan `IdGenerator::roomPrefix()`.
     *
     * Tidak ada data yang dibuang: seluruh relasi memakai foreign key
     * (`room_id`), sehingga riwayat peminjaman, mutasi, dan penugasan unit
     * tetap menunjuk ke ruangan yang sama.
     */
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        if (! Schema::hasColumn('rooms', 'previous_code')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->string('previous_code', 50)->nullable()->after('code');
            });
        }

        $buildings = Schema::hasTable('buildings')
            ? DB::table('buildings')->pluck('code', 'id')
            : collect();

        $canonicalByRoom = [];

        foreach (DB::table('rooms')->orderBy('id')->get() as $room) {
            $letter = strtoupper(trim((string) ($room->building_letter ?? '')));

            if ($letter === '' && $room->building_id !== null) {
                $letter = strtoupper(trim((string) ($buildings[$room->building_id] ?? '')));
            }

            if ($letter === '' || ! preg_match('/^[A-Z]$/', $letter)) {
                continue;
            }

            $canonical = sprintf('%s%d-%02d', $letter, (int) $room->floor, (int) $room->room_no);

            if ($canonical !== $room->code) {
                $canonicalByRoom[(int) $room->id] = $canonical;
            }
        }

        if ($canonicalByRoom !== []) {
            // Fase 1: kosongkan dulu kode lama yang akan dipakai ruangan lain
            // (mis. kode "G2-02" milik ruangan lama berpindah ke ruangan G2-02
            // yang baru) supaya penulisan ulang tidak menabrak unique index.
            foreach (array_keys($canonicalByRoom) as $roomId) {
                DB::table('rooms')->where('id', $roomId)->update([
                    'previous_code' => DB::raw('code'),
                    'code' => 'TMP-'.str_pad((string) $roomId, 6, '0', STR_PAD_LEFT),
                ]);
            }

            // Fase 2: tulis kode kanonik.
            foreach ($canonicalByRoom as $roomId => $canonical) {
                DB::table('rooms')->where('id', $roomId)->update(['code' => $canonical]);
            }
        }

        $this->regenerateInternalIds();
    }

    /**
     * Tulis ulang `internal_id` katalog yang prefix ruangannya berubah.
     */
    private function regenerateInternalIds(): void
    {
        $table = Schema::hasTable('item_catalogs') ? 'item_catalogs' : 'items';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'internal_id')) {
            return;
        }

        $rooms = DB::table('rooms')->orderBy('id')->get()->keyBy('id');
        $items = DB::table($table)->orderBy('id')->get(['id', 'room_id']);

        // Fase 1: bebaskan nilai lama agar penulisan ulang tidak menabrak unique index.
        foreach ($items as $item) {
            DB::table($table)->where('id', $item->id)->update(['internal_id' => 'TMP-'.$item->id]);
        }

        // Fase 2: tulis ulang memakai prefix kanonik dari master gedung.
        $taken = [];

        foreach ($items as $item) {
            $prefix = $this->roomPrefix($rooms->get($item->room_id));
            $seq = 1;

            do {
                $candidate = $prefix.str_pad((string) $seq++, 2, '0', STR_PAD_LEFT);
            } while (isset($taken[$candidate]));

            $taken[$candidate] = true;

            DB::table($table)->where('id', $item->id)->update(['internal_id' => $candidate]);
        }
    }

    /**
     * Prefix ID internal: `{huruf gedung}{lantai}-{nomor ruang}-`.
     */
    private function roomPrefix(?object $room): string
    {
        if ($room === null) {
            return 'g0-00-';
        }

        $letter = strtoupper(trim((string) ($room->building_letter ?? '')));

        return sprintf('%s%d-%02d-', $letter !== '' ? strtolower($letter) : 'g', (int) $room->floor, (int) $room->room_no);
    }

    public function down(): void
    {
        if (Schema::hasTable('rooms') && Schema::hasColumn('rooms', 'previous_code')) {
            $rooms = DB::table('rooms')->whereNotNull('previous_code')->orderBy('id')->get();

            // Kosongkan dulu supaya kode lama yang kini dipakai ruangan lain
            // tidak bentrok saat dipulihkan.
            foreach ($rooms as $room) {
                DB::table('rooms')->where('id', $room->id)
                    ->update(['code' => 'TMP-'.str_pad((string) $room->id, 6, '0', STR_PAD_LEFT)]);
            }

            foreach ($rooms as $room) {
                DB::table('rooms')->where('id', $room->id)
                    ->update(['code' => $room->previous_code]);
            }

            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('previous_code');
            });
        }
    }
};
