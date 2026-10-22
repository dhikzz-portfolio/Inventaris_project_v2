<?php

namespace App\Console\Commands;

use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Item;
use App\Models\Room;
use App\Services\InventoryCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryVerifyCodes extends Command
{
    protected $signature = 'inventory:verify-codes';

    protected $description = 'Verifikasi kode inventaris unit, ledger kode, identitas ruangan, dan jumlah unit katalog';

    /**
     * Periksa integritas kode tanpa mengubah data. Exit code 1 bila ada selisih.
     */
    public function handle(InventoryCodeService $codes): int
    {
        $issues = collect()
            ->merge($this->checkRooms($codes))
            ->merge($this->checkUnits($codes))
            ->merge($this->checkLedger())
            ->merge($this->checkCatalogQuantity());

        if ($issues->isEmpty()) {
            $this->components->info(sprintf(
                'Seluruh %d unit dan %d katalog konsisten.',
                AssetUnit::query()->count(),
                Item::query()->count(),
            ));

            return self::SUCCESS;
        }

        $this->components->error("Ditemukan {$issues->count()} masalah integritas kode inventaris.");
        $this->table(['Unit/Katalog', 'Masalah'], $issues->map(fn (array $issue) => [
            $issue['subject'],
            $issue['message'],
        ])->all());

        return self::FAILURE;
    }

    /**
     * @return Collection<int, array{subject: string, message: string}>
     */
    private function checkRooms(InventoryCodeService $codes): Collection
    {
        return Room::query()->orderBy('id')->get()
            ->flatMap(function (Room $room) use ($codes) {
                try {
                    $codes->assertRoomIdentity($room);
                } catch (ValidationException $exception) {
                    return [[
                        'subject' => "Ruangan #{$room->id} ({$room->code})",
                        'message' => $exception->validator->errors()->first('room_id'),
                    ]];
                }

                return [];
            });
    }

    /**
     * @return Collection<int, array{subject: string, message: string}>
     */
    private function checkUnits(InventoryCodeService $codes): Collection
    {
        return AssetUnit::query()->with('room')->orderBy('id')->get()
            ->flatMap(function (AssetUnit $unit) use ($codes) {
                $issues = [];
                $code = (string) $unit->inventory_code;
                $subject = "Unit #{$unit->id} ({$code})";

                if (! $codes->isValidFormat($code)) {
                    $issues[] = ['subject' => $subject, 'message' => 'Kode aktif tidak sesuai format G1-02-12.'];

                    return $issues;
                }

                if ($unit->room === null) {
                    $issues[] = ['subject' => $subject, 'message' => 'Unit tidak memiliki ruangan.'];

                    return $issues;
                }

                try {
                    $expected = $codes->format($unit->room, (int) $unit->unit_no);
                } catch (ValidationException) {
                    $expected = null;
                }

                if ($expected !== null && $expected !== $code) {
                    $issues[] = [
                        'subject' => $subject,
                        'message' => "Kode tidak cocok dengan lokasi ruangan/unit_no (seharusnya {$expected}).",
                    ];
                }

                return $issues;
            });
    }

    /**
     * @return Collection<int, array{subject: string, message: string}>
     */
    private function checkLedger(): Collection
    {
        $activeCodes = AssetUnitCode::query()
            ->whereNull('revoked_at')
            ->get()
            ->groupBy('asset_unit_id');

        $units = AssetUnit::query()->get()->keyBy('id');

        return collect($units->keys())
            ->flatMap(function (int $unitId) use ($activeCodes, $units) {
                $unit = $units[$unitId];
                $ledger = $activeCodes->get($unitId, collect());
                $subject = "Unit #{$unit->id} ({$unit->inventory_code})";

                if ($ledger->count() !== 1) {
                    return [[
                        'subject' => $subject,
                        'message' => "Jumlah kode aktif di ledger harus 1, ditemukan {$ledger->count()}.",
                    ]];
                }

                /** @var AssetUnitCode $active */
                $active = $ledger->first();
                $issues = [];

                if ($active->inventory_code !== $unit->inventory_code) {
                    $issues[] = ['subject' => $subject, 'message' => "Kode ledger ({$active->inventory_code}) berbeda dengan kode unit."];
                }

                if ((int) $active->room_id !== (int) $unit->room_id) {
                    $issues[] = ['subject' => $subject, 'message' => 'Ruangan pada kode ledger berbeda dengan ruangan unit.'];
                }

                if ((int) $active->unit_no !== (int) $unit->unit_no) {
                    $issues[] = ['subject' => $subject, 'message' => 'Nomor unit pada kode ledger berbeda dengan nomor unit.'];
                }

                return $issues;
            });
    }

    /**
     * @return Collection<int, array{subject: string, message: string}>
     */
    private function checkCatalogQuantity(): Collection
    {
        $counts = AssetUnit::query()
            ->selectRaw('item_catalog_id, COUNT(*) as total')
            ->where('status', '!=', 'DIHAPUS')
            ->groupBy('item_catalog_id')
            ->pluck('total', 'item_catalog_id');

        return Item::query()->orderBy('id')->get()
            ->flatMap(function (Item $catalog) use ($counts) {
                $actual = (int) ($counts[$catalog->id] ?? 0);
                $stored = (int) $catalog->quantity;

                if ($actual === $stored) {
                    return [];
                }

                return [[
                    'subject' => "Katalog #{$catalog->id} ({$catalog->name})",
                    'message' => "Kolom quantity={$stored}, jumlah unit aktif={$actual}.",
                ]];
            });
    }
}
