<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssetUnitService
{
    public function __construct(private readonly InventoryCodeService $codes) {}

    /**
     * Buat satu unit fisik dan terbitkan kode inventarisnya secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Item $catalog, Room $room, array $data = [], ?User $actor = null): AssetUnit
    {
        $this->codes->assertRoomIdentity($room);

        return DB::transaction(function () use ($catalog, $room, $data, $actor) {
            DB::table('rooms')->where('id', $room->id)->lockForUpdate()->first();

            app(ItemTypeCodeService::class)->ensureCode($catalog);
            $unitNo = $this->codes->nextUnitNo($room);
            $code = $this->codes->format($room, $unitNo, $catalog->item_type_code);

            $unit = AssetUnit::create([
                'item_catalog_id' => $catalog->id,
                'inventory_code' => $code,
                'room_id' => $room->id,
                'unit_no' => $unitNo,
                'condition' => $data['condition'] ?? Condition::BAIK,
                'status' => $data['status'] ?? UnitStatus::AKTIF,
                'acquisition_date' => $data['acquisition_date'] ?? null,
                'acquisition_year' => $data['acquisition_year'] ?? $catalog->acquisition_year,
                'acquisition_value' => $data['acquisition_value'] ?? null,
                'funding_source' => $data['funding_source'] ?? $catalog->funding_source,
                'acquisition_source' => $data['acquisition_source'] ?? null,
                'register_no' => $data['register_no'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            AssetUnitCode::create([
                'asset_unit_id' => $unit->id,
                'inventory_code' => $code,
                'room_id' => $room->id,
                'item_type_code' => $catalog->item_type_code,
                'unit_no' => $unitNo,
                'issued_at' => now(),
                'reason' => 'PENEMPATAN',
            ]);

            $this->syncCatalogCounters($catalog);

            if ($actor !== null) {
                activity('inventaris')
                    ->performedOn($unit)
                    ->causedBy($actor)
                    ->withProperties(['inventory_code' => $code])
                    ->log('Menambah unit inventaris');
            }

            return $unit->fresh(['catalog', 'room']);
        }, 3);
    }

    public function updateCondition(AssetUnit $unit, Condition $condition, ?User $actor = null): AssetUnit
    {
        $unit->update(['condition' => $condition]);
        $this->syncCatalogCounters($unit->catalog);

        if ($actor !== null) {
            activity('inventaris')->performedOn($unit)->causedBy($actor)
                ->log('Mengubah kondisi unit menjadi '.$condition->label());
        }

        return $unit->fresh(['catalog', 'room']);
    }

    public function archive(AssetUnit $unit, ?User $actor = null): AssetUnit
    {
        $unit->update([
            'status' => UnitStatus::DIHAPUS,
            'archived_at' => now(),
        ]);

        $this->syncCatalogCounters($unit->catalog);

        if ($actor !== null) {
            activity('inventaris')->performedOn($unit)->causedBy($actor)
                ->log('Menghapus unit dari inventaris aktif (riwayat dipertahankan)');
        }

        return $unit->fresh();
    }

    /**
     * Samakan kolom kuantitas legacy dengan data unit agar modul peminjaman
     * yang masih berbasis katalog tetap konsisten selama masa transisi.
     */
    public function syncCatalogCounters(Item $catalog): void
    {
        $catalog->forceFill([
            'quantity' => $catalog->units()->where('status', '!=', UnitStatus::DIHAPUS->value)->count(),
            'qty_damaged' => $catalog->units()
                ->where('status', UnitStatus::AKTIF->value)
                ->where('condition', '!=', Condition::BAIK->value)
                ->count(),
            'qty_lost' => $catalog->units()->where('status', UnitStatus::HILANG->value)->count(),
        ])->save();
    }

    /**
     * Lengkapi unit untuk katalog yang belum memiliki unit (data demo/backfill).
     */
    public function provisionMissingUnits(Item $catalog, ?User $actor = null): int
    {
        $existing = $catalog->units()->count();
        $needed = max(0, (int) $catalog->quantity - $existing);

        if ($needed === 0) {
            return 0;
        }

        $room = $catalog->room;
        if ($room === null) {
            return 0;
        }

        for ($i = 0; $i < $needed; $i++) {
            $this->create($catalog, $room, [], $actor);
        }

        return $needed;
    }

    public function provisionAll(): int
    {
        $created = 0;

        Item::query()->with('room')->orderBy('id')->each(function (Item $catalog) use (&$created) {
            $created += $this->provisionMissingUnits($catalog);
        });

        return $created;
    }
}
