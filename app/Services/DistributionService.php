<?php

namespace App\Services;

use App\Models\AssetUnit;
use App\Models\Distribution;
use App\Models\DistributionUnit;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionService
{
    public function __construct(private readonly InventoryCodeService $codes) {}

    /**
     * Distribusikan unit fisik dari satu spesifikasi ke ruangan tujuan.
     *
     * Stok dihitung dari unit aktual spesifikasi tersebut, bukan angka input,
     * dan unit yang sudah pernah didistribusikan atau sedang dipinjam tidak
     * boleh dipakai lagi. Setiap unit dicatat sehingga riwayat distribusi
     * dapat ditelusuri hingga kode inventarisnya.
     */
    public function distribute(
        Specification $specification,
        Room $toRoom,
        int $quantity,
        User $actor,
        ?string $note = null,
        ?Room $fromRoom = null,
    ): Distribution {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah distribusi minimal 1 unit.',
            ]);
        }

        $query = $specification->distributableQuery($actor, $toRoom->id)
            ->when($fromRoom !== null, fn ($builder) => $builder->where('room_id', $fromRoom->id))
            ->orderBy('inventory_code');

        $available = (clone $query)->count();

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => "Stok spesifikasi {$specification->displayName()} hanya {$available} unit yang dapat didistribusikan. Merek/tipe lain tidak ikut dihitung.",
            ]);
        }

        return DB::transaction(function () use ($specification, $toRoom, $quantity, $actor, $note, $query) {
            $units = $query->lockForUpdate()->limit($quantity)->get();

            if ($units->count() < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok berubah saat distribusi diproses. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            $distribution = Distribution::create([
                'item_catalog_id' => $specification->item_catalog_id,
                'specification_id' => $specification->id,
                'to_room_id' => $toRoom->id,
                'quantity' => $units->count(),
                'distributed_by' => $actor->id,
                'distributed_at' => now(),
                'note' => $note,
            ]);

            foreach ($units as $unit) {
                /** @var AssetUnit $unit */
                $fromRoomId = $unit->room_id;
                $oldCode = $unit->inventory_code;

                $moved = $this->codes->move($unit, $toRoom, $actor, 'Distribusi spesifikasi '.$specification->displayName());

                DistributionUnit::create([
                    'distribution_id' => $distribution->id,
                    'asset_unit_id' => $moved->id,
                    'from_room_id' => $fromRoomId,
                    'to_room_id' => $toRoom->id,
                    'old_code' => $oldCode,
                    'new_code' => $moved->inventory_code,
                ]);
            }

            activity('distribusi')
                ->performedOn($distribution)
                ->causedBy($actor)
                ->withProperties([
                    'katalog' => $specification->itemCatalog?->name,
                    'spesifikasi' => $specification->displayName(),
                    'jumlah' => $distribution->quantity,
                    'tujuan' => $toRoom->code,
                ])
                ->log('Mendistribusikan unit berdasarkan merek dan tipe/model');

            return $distribution->load(['specification', 'toRoom', 'units.unit']);
        });
    }
}
