<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\AssetUnitMovement;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCodeService
{
    public const PATTERN = '/^[A-Z][1-9]-[0-9]{2}-[0-9]{2}$/';

    public const MAX_UNIT_NO = 99;

    private ?bool $connected = null;

    /**
     * Layanan ini "tersambung" jika Laravel DB connection dapat membuat PDO.
     * Simple flag dari env cukup mudah mengarah ke false-positive di lingkungan
     * testing (mis. :memory: SQLite) atau proses yang db-nya baru tersedia
     * setelah boot, jadi kita uji koneksi secara langsung.
     */
    public function isConnected(): bool
    {
        if ($this->connected !== null) {
            return $this->connected;
        }

        try {
            DB::connection()->getPdo();

            $this->connected = true;
        } catch (Throwable $e) {
            $this->connected = false;
        }

        return $this->connected;
    }

    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isValidFormat(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    public function format(Room $room, int $unitNo): string
    {
        $resolved = $this->assertOrResolveRoomIdentity($room);

        if ($unitNo < 1 || $unitNo > self::MAX_UNIT_NO) {
            throw ValidationException::withMessages([
                'unit_no' => 'Nomor unit harus berada di antara 01 dan 99.',
            ]);
        }

        $prefix = $resolved['prefix'];
        $roomNo = (int) $resolved['room_no'];

        return sprintf('%s-%02d-%02d', $prefix, $roomNo, $unitNo);
    }

    /**
     * Serupa assertRoomIdentity, tetapi jika ruangan tidak memiliki identitas spasial
     * yang lengkap secara lokal, coba kembalikan dari data kode katalog terbaru
     * yang merujuk ke ruangan ini (mis. popup store tanpa properti gedung/loket
     * lengkap). Jika tetap tidak dapat ditentukan, lempar exception seperti asli.
     *
     * Identitas gedung selalu diambil dari master gedung — bukan teks bebas
     * yang diketik pada formulir ruangan.
     *
     * @return array{prefix: string, room_no: int}
     */
    private function assertOrResolveRoomIdentity(Room $room): array
    {
        $letter = $room->buildingCode();
        $floor = (int) $room->floor;
        $roomNo = (int) $room->room_no;

        if (preg_match('/^[A-Z]$/', $letter) === 1
            && $floor >= 1 && $floor <= 9
            && $roomNo >= 1 && $roomNo <= 99) {
            return ['prefix' => $letter.$floor, 'room_no' => $roomNo];
        }

        if (! $this->isConnected()) {
            throw ValidationException::withMessages([
                'room_id' => 'Identitas ruangan belum lengkap dan status katalog tidak tersedia untuk resolusi.',
            ]);
        }

        $activeCode = AssetUnitCode::query()
            ->where('room_id', $room->id)
            ->whereNull('revoked_at')
            ->orderByDesc('issued_at')
            ->first();

        if ($activeCode === null) {
            throw ValidationException::withMessages([
                'room_id' => 'Identitas ruangan belum lengkap dan tidak ada kode unit aktif untuk resolusi.',
            ]);
        }

        return ['prefix' => $activeCode->codePrefix(), 'room_no' => (int) $activeCode->room_no];
    }

    private function codePrefix(string $code): string
    {
        return substr($code, 0, strrpos($code, '-'));
    }

    private function roomNoFromCode(string $code): int
    {
        $parts = explode('-', $code);
        if (count($parts) !== 3) {
            return 0;
        }

        return (int) $parts[1];
    }

    public function assertRoomIdentity(Room $room): void
    {
        $letter = $room->buildingCode();

        if (preg_match('/^[A-Z]$/', $letter) !== 1
            || (int) $room->floor < 1 || (int) $room->floor > 9
            || (int) $room->room_no < 1 || (int) $room->room_no > 99) {
            throw ValidationException::withMessages([
                'room_id' => 'Identitas ruangan belum lengkap. Tetapkan gedung (A-Z), lantai 1-9, dan nomor ruangan 1-99.',
            ]);
        }
    }

    /**
     * Nomor berikutnya dihitung dari seluruh kode yang pernah diterbitkan,
     * bukan hanya unit aktif, agar nomor pensiun tidak dipakai ulang.
     */
    public function nextUnitNo(Room $room): int
    {
        $resolved = $this->assertOrResolveRoomIdentity($room);

        $max = (int) AssetUnitCode::query()
            ->where('room_id', $room->id)
            ->max('unit_no');

        $next = $max + 1;

        if ($next > self::MAX_UNIT_NO) {
            throw ValidationException::withMessages([
                'unit_no' => "Ruangan {$room->code} sudah memakai 99 nomor unit. Pecah ruangan atau pindahkan unit ke ruangan lain.",
            ]);
        }

        return $next;
    }

    public function preview(Room $room): string
    {
        return $this->format($room, $this->nextUnitNo($room));
    }

    /**
     * Terbitkan kode baru untuk unit yang sudah tersimpan. Harus dipanggil
     * di dalam transaksi pemanggil atau akan membungkusnya sendiri.
     */
    public function issue(AssetUnit $unit, Room $room, string $reason = 'PENEMPATAN'): AssetUnitCode
    {
        return $this->withRetry(function () use ($unit, $room, $reason) {
            return DB::transaction(function () use ($unit, $room, $reason) {
                $this->lockRoom($room);
                $unitNo = $this->nextUnitNo($room);
                $code = $this->format($room, $unitNo);

                $unit->forceFill([
                    'room_id' => $room->id,
                    'unit_no' => $unitNo,
                    'inventory_code' => $code,
                    'last_label_printed_at' => null,
                ])->save();

                return AssetUnitCode::create([
                    'asset_unit_id' => $unit->id,
                    'inventory_code' => $code,
                    'room_id' => $room->id,
                    'unit_no' => $unitNo,
                    'issued_at' => now(),
                    'reason' => $reason,
                ]);
            }, 3);
        });
    }

    /**
     * Mutasi unit: tutup kode lama, terbitkan kode baru, simpan riwayat.
     */
    public function move(AssetUnit $unit, Room $toRoom, User $actor, ?string $note = null): AssetUnit
    {
        if ($unit->status === UnitStatus::DIHAPUS) {
            throw ValidationException::withMessages([
                'unit' => 'Unit yang sudah dihapus tidak dapat dipindahkan.',
            ]);
        }

        if ($unit->status === UnitStatus::DIPINJAM) {
            throw ValidationException::withMessages([
                'unit' => 'Unit yang sedang dipinjam harus dikembalikan sebelum dipindahkan.',
            ]);
        }

        if ((int) $unit->room_id === (int) $toRoom->id) {
            throw ValidationException::withMessages([
                'to_room_id' => 'Ruangan tujuan sama dengan lokasi saat ini.',
            ]);
        }

        return $this->withRetry(function () use ($unit, $toRoom, $actor, $note) {
            return DB::transaction(function () use ($unit, $toRoom, $actor, $note) {
                $locked = AssetUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
                $fromRoom = $locked->room;
                $oldCode = $locked->inventory_code;

                $this->lockRoom($toRoom);
                $unitNo = $this->nextUnitNo($toRoom);
                $newCode = $this->format($toRoom, $unitNo);

                AssetUnitCode::query()
                    ->where('asset_unit_id', $locked->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);

                AssetUnitCode::create([
                    'asset_unit_id' => $locked->id,
                    'inventory_code' => $newCode,
                    'room_id' => $toRoom->id,
                    'unit_no' => $unitNo,
                    'issued_at' => now(),
                    'reason' => 'MUTASI',
                ]);

                $locked->forceFill([
                    'room_id' => $toRoom->id,
                    'unit_no' => $unitNo,
                    'inventory_code' => $newCode,
                    'last_label_printed_at' => null,
                ])->save();

                AssetUnitMovement::create([
                    'asset_unit_id' => $locked->id,
                    'from_room_id' => $fromRoom?->id,
                    'to_room_id' => $toRoom->id,
                    'old_code' => $oldCode,
                    'new_code' => $newCode,
                    'moved_by' => $actor->id,
                    'moved_at' => now(),
                    'reason' => 'MUTASI',
                    'note' => $note,
                ]);

                activity('inventaris')
                    ->performedOn($locked)
                    ->causedBy($actor)
                    ->withProperties(['old_code' => $oldCode, 'new_code' => $newCode])
                    ->log('Mutasi unit: kode inventaris diterbitkan ulang');

                return $locked->fresh(['room', 'catalog']);
            }, 3);
        });
    }

    /**
     * Cari unit berdasarkan kode aktif atau kode pensiun.
     */
    public function resolve(string $code): ?AssetUnit
    {
        $code = $this->normalize($code);

        if (! $this->isValidFormat($code)) {
            return null;
        }

        $active = AssetUnit::query()->where('inventory_code', $code)->first();
        if ($active !== null) {
            return $active;
        }

        $retired = AssetUnitCode::query()
            ->where('inventory_code', $code)
            ->whereNotNull('revoked_at')
            ->latest('revoked_at')
            ->first();

        return $retired?->unit;
    }

    private function withRetry(callable $work): mixed
    {

        $last = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $work();
            } catch (Throwable $e) {
                $last = $e;

                if ($attempt === 3) {
                    throw $e;
                }

                usleep(200000);
            }
        }

        throw $last;
    }

    private function lockRoom(Room $room): void
    {
        DB::table('rooms')->where('id', $room->id)->lockForUpdate()->first();
    }

    /**
     * Alias kompatibel untuk calon pengguna yang memanggil tanpa resolusi lokal.
     */
    public function formatFromCode(string $existingCode, int $unitNo): string
    {
        if ($unitNo < 1 || $unitNo > self::MAX_UNIT_NO) {
            throw ValidationException::withMessages([
                'unit_no' => 'Nomor unit harus berada di antara 01 dan 99.',
            ]);
        }

        return sprintf('%s-%02d-%02d', $this->codePrefix($existingCode), $this->roomNoFromCode($existingCode), $unitNo);
    }
}
