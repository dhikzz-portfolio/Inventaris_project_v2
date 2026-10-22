<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryBackfillService
{
    /**
     * Normalisasi identitas ruangan lama menjadi huruf gedung + nomor ruangan
     * yang unik, tanpa menebak bahwa digit pada `building` adalah lantai.
     */
    public function normalizeRooms(): int
    {
        if (! Schema::hasTable('rooms')) {
            return 0;
        }

        $rooms = DB::table('rooms')->orderBy('id')->get();
        $letterByBuilding = [];
        $usedLetters = [];
        $alphabet = range('A', 'Z');

        foreach ($rooms->pluck('building')->filter()->unique()->sort()->values() as $building) {
            $letters = preg_replace('/[^A-Za-z]/', '', (string) $building);
            $base = $letters !== '' ? strtoupper($letters[0]) : 'G';
            $index = array_search($base, $alphabet, true);
            $index = $index === false ? 0 : $index;

            for ($step = 0; $step < 26; $step++) {
                $candidate = $alphabet[($index + $step) % 26];
                if (! in_array($candidate, $usedLetters, true)) {
                    $letterByBuilding[$building] = $candidate;
                    $usedLetters[] = $candidate;
                    break;
                }
            }
        }

        $usedRoomNumbers = [];
        $updated = 0;

        foreach ($rooms as $room) {
            if ($room->building_letter !== null && $room->room_no !== null) {
                $usedRoomNumbers[$room->building_letter.'-'.$room->floor.'-'.$room->room_no] = true;
            }
        }

        foreach ($rooms as $room) {
            $letter = $letterByBuilding[$room->building] ?? 'G';
            preg_match('/(\d+)\s*$/', (string) $room->code, $matches);
            $roomNo = isset($matches[1]) ? max(1, (int) $matches[1]) : 1;

            while (isset($usedRoomNumbers[$letter.'-'.$room->floor.'-'.$roomNo])
                && ! ($room->building_letter === $letter && (int) $room->room_no === $roomNo)) {
                $roomNo++;
            }
            $usedRoomNumbers[$letter.'-'.$room->floor.'-'.$roomNo] = true;

            DB::table('rooms')->where('id', $room->id)->update([
                'building_letter' => $letter,
                'room_no' => $roomNo,
                'is_lab' => (bool) ($room->is_lab
                    || $room->major_id !== null
                    || stripos((string) $room->name, 'lab') !== false
                    || stripos((string) $room->name, 'studio') !== false),
            ]);
            $updated++;
        }

        $indexes = collect(Schema::getIndexes('rooms'))->pluck('name');
        if (! $indexes->contains('rooms_identity_unique')) {
            Schema::table('rooms', function ($table) {
                $table->unique(['building_letter', 'floor', 'room_no'], 'rooms_identity_unique');
            });
        }

        return $updated;
    }

    /**
     * Lengkapi unit fisik untuk katalog lama berdasarkan `quantity`.
     * Idempoten: katalog yang jumlah unitnya sudah memadai dilewati.
     */
    public function backfillUnits(): int
    {
        if (! Schema::hasTable('item_catalogs') || ! Schema::hasTable('asset_units')) {
            return 0;
        }

        $rooms = DB::table('rooms')->get()->keyBy('id');
        $created = 0;

        foreach (DB::table('item_catalogs')->orderBy('id')->get() as $catalog) {
            $existing = (int) DB::table('asset_units')->where('item_catalog_id', $catalog->id)->count();
            $quantity = max(0, (int) $catalog->quantity);
            $needed = max(0, $quantity - $existing);

            if ($needed === 0) {
                continue;
            }

            $room = $rooms->get($catalog->room_id);
            if ($room === null || $room->building_letter === null || $room->room_no === null) {
                continue;
            }

            $baseStatus = (string) $catalog->status;
            $baseCondition = match ($baseStatus) {
                'RUSAK_RINGAN' => 'RUSAK_RINGAN',
                'RUSAK_BERAT' => 'RUSAK_BERAT',
                default => 'BAIK',
            };
            $unitStatus = match ($baseStatus) {
                'HILANG' => 'HILANG',
                'DIHAPUS' => 'DIHAPUS',
                'DIPINJAM' => 'DIPINJAM',
                default => 'AKTIF',
            };

            $lostQuota = min((int) $catalog->qty_lost, $quantity);
            $damagedQuota = min((int) $catalog->qty_damaged, max(0, $quantity - $lostQuota));

            $nextUnitNo = (int) DB::table('asset_unit_codes')->where('room_id', $room->id)->max('unit_no');

            for ($i = $existing; $i < $quantity; $i++) {
                $condition = $baseCondition;
                $status = $unitStatus;

                if ($unitStatus === 'AKTIF') {
                    if ($i < $lostQuota) {
                        $status = 'HILANG';
                    } elseif ($i < $lostQuota + $damagedQuota) {
                        $condition = 'RUSAK_BERAT';
                    }
                }

                $unitNo = ++$nextUnitNo;
                if ($unitNo > InventoryCodeService::MAX_UNIT_NO) {
                    break;
                }

                $code = sprintf(
                    '%s%d-%02d-%02d',
                    $room->building_letter,
                    (int) $room->floor,
                    (int) $room->room_no,
                    $unitNo
                );

                $unitId = DB::table('asset_units')->insertGetId([
                    'item_catalog_id' => $catalog->id,
                    'inventory_code' => $code,
                    'room_id' => $room->id,
                    'unit_no' => $unitNo,
                    'condition' => $condition,
                    'status' => $status,
                    'acquisition_year' => $catalog->acquisition_year,
                    'funding_source' => $catalog->funding_source,
                    'photo_path' => $catalog->photo_path,
                    'last_label_printed_at' => null,
                    'archived_at' => $status === 'DIHAPUS' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('asset_unit_codes')->insert([
                    'asset_unit_id' => $unitId,
                    'inventory_code' => $code,
                    'room_id' => $room->id,
                    'unit_no' => $unitNo,
                    'issued_at' => now(),
                    'revoked_at' => null,
                    'reason' => 'PENEMPATAN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }
        }

        return $created;
    }
}
