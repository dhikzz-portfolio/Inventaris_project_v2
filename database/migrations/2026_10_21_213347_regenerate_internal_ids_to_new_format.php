<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rooms = DB::table('rooms')->orderBy('id')->get()->keyBy('id');
        $items = DB::table('items')->orderBy('id')->get(['id', 'room_id']);

        // Fase 1: bebaskan nilai lama agar penulisan ulang tidak menabrak unique index internal_id.
        foreach ($items as $item) {
            DB::table('items')->where('id', $item->id)->update(['internal_id' => 'TMP-'.$item->id]);
        }

        // Fase 2: tulis ulang dengan format {huruf gedung}{lantai}-{ruangan ke}-{urutan}.
        // Prefix bisa dipakai bersama oleh beberapa ruangan (mis. gedung G2 lt.2 dan gedung G1 lt.2),
        // sehingga urutan dijaga unik terhadap ID yang sudah terisi.
        $taken = [];
        foreach ($items as $item) {
            $prefix = $this->roomPrefix($rooms->get($item->room_id));
            $seq = 1;
            do {
                $candidate = $prefix.str_pad((string) $seq++, 2, '0', STR_PAD_LEFT);
            } while (isset($taken[$candidate]));

            $taken[$candidate] = true;
            DB::table('items')->where('id', $item->id)->update(['internal_id' => $candidate]);
        }
    }

    public function down(): void
    {
        $rooms = DB::table('rooms')->orderBy('id')->get()->keyBy('id');
        $items = DB::table('items')->orderBy('id')->get(['id', 'room_id', 'name']);

        foreach ($items as $item) {
            DB::table('items')->where('id', $item->id)->update(['internal_id' => 'TMP-'.$item->id]);
        }

        // Format lama: {NAMA}-{KODERUANGAN}-{URUTAN}, mis. MEJA-G1-01-01.
        $taken = [];
        foreach ($items as $item) {
            $room = $rooms->get($item->room_id);
            $words = preg_split('/\s+/', trim((string) $item->name));
            $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $words[0] ?? 'BRG'));
            $prefix = ($prefix !== '' ? $prefix : 'BRG').'-'.($room->code ?? 'TANPA-RUANG').'-';

            $seq = 1;
            do {
                $candidate = $prefix.str_pad((string) $seq++, 2, '0', STR_PAD_LEFT);
            } while (isset($taken[$candidate]));

            $taken[$candidate] = true;
            DB::table('items')->where('id', $item->id)->update(['internal_id' => $candidate]);
        }
    }

    /**
     * Prefix ID internal: `{huruf gedung}{lantai}-{ruangan ke}-`, contoh gedung "G1" Lt.1 ruang "G1-01" → "g1-01-".
     */
    private function roomPrefix(?object $room): string
    {
        if ($room === null) {
            return 'g0-00-';
        }

        $letters = preg_replace('/[^A-Za-z]/', '', (string) $room->building);
        $buildingLetter = $letters !== null && $letters !== '' ? strtolower($letters[0]) : 'g';

        preg_match('/(\d+)\s*$/', (string) $room->code, $matches);
        $roomNo = isset($matches[1]) ? (int) $matches[1] : 1;

        return sprintf('%s%d-%02d-', $buildingLetter, (int) $room->floor, $roomNo);
    }
};
