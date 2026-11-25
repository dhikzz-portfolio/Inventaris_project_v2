<?php

namespace App\Services;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitStatusLog;
use App\Models\Distribution;
use App\Models\DistributionUnit;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionService
{
    public function __construct(
        private readonly InventoryCodeService $codes,
        private readonly AssetUnitService $units,
    ) {}

    /**
     * Distribusikan unit fisik dari satu spesifikasi ke ruangan tujuan.
     *
     * Stok dihitung dari unit aktual spesifikasi tersebut, bukan angka input,
     * dan unit yang sudah pernah didistribusikan atau sedang dipinjam tidak
     * boleh dipakai lagi. Setiap unit dicatat sehingga riwayat distribusi
     * dapat ditelusuri hingga kode inventarisnya.
     */
    /**
     * Distribusikan stok yang sudah diterima ke satu atau beberapa ruangan.
     *
     * Setiap baris alokasi menentukan sendiri kelompok spesifikasi (merek,
     * tipe/model, tahun, sumber dana), jumlah, kondisi fisik, dan status
     * operasional. Unit inventaris diterbitkan per baris sehingga satu katalog
     * dapat memiliki beberapa kelompok spesifikasi yang berbeda per ruangan.
     *
     * Stok dihitung dari penerimaan aktual dikurangi distribusi bersumber stok
     * sebelumnya; baris yang sudah menjadi unit tidak pernah dihitung ulang.
     *
     * @param  array<int, array{
     *     room_id: int|string,
     *     quantity: int|string,
     *     specification_id?: int|string|null,
     *     brand?: string|null,
     *     model_type?: string|null,
     *     acquisition_year?: int|string|null,
     *     funding_source?: string|null,
     *     condition?: string|null,
     *     status?: string|null,
     *     reason?: string|null,
     *     note?: string|null,
     * }>  $allocations
     * @return Collection<int, Distribution>
     */
    public function distributeFromStock(Item $catalog, array $allocations, User $actor, ?string $defaultNote = null): Collection
    {
        $rows = collect($allocations)
            ->filter(fn ($row) => is_array($row) && filled($row['room_id'] ?? null) && filled($row['quantity'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Tambahkan minimal satu alokasi ruangan untuk didistribusikan.',
            ]);
        }

        $totalRequested = (int) $rows->sum(fn (array $row): int => (int) $row['quantity']);

        if ($totalRequested < 1) {
            throw ValidationException::withMessages([
                'allocations' => 'Jumlah distribusi minimal 1 unit.',
            ]);
        }

        return DB::transaction(function () use ($catalog, $rows, $actor, $defaultNote): Collection {
            /** @var Item $locked */
            $locked = Item::query()->whereKey($catalog->id)->lockForUpdate()->firstOrFail();

            $remaining = $locked->remainingStockQuantity();
            $requested = (int) $rows->sum(fn (array $row): int => (int) $row['quantity']);

            if ($requested > $remaining) {
                throw ValidationException::withMessages([
                    'allocations' => "Sisa stok yang belum didistribusikan hanya {$remaining} unit. Kurangi jumlah distribusi atau catat penerimaan baru.",
                ]);
            }

            $created = collect();

            foreach ($rows as $index => $row) {
                $room = Room::query()->find((int) $row['room_id']);

                if ($room === null) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.room_id" => 'Ruangan tujuan tidak ditemukan.',
                    ]);
                }

                $this->assertRoomAllowed($room, $actor, $index);

                $quantity = (int) $row['quantity'];

                if ($quantity < 1) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.quantity" => 'Jumlah setiap alokasi minimal 1 unit.',
                    ]);
                }

                $condition = Condition::tryFrom((string) ($row['condition'] ?? Condition::BAIK->value));

                if ($condition === null) {
                    throw ValidationException::withMessages([
                        "allocations.{$index}.condition" => 'Kondisi fisik yang dipilih tidak valid.',
                    ]);
                }

                [$status, $reason] = $this->resolveInitialStatus($row, $index);
                $specification = $this->resolveSpecification($locked, $row, $index);

                $note = filled($row['note'] ?? null) ? trim((string) $row['note']) : $defaultNote;
                $acquisitionYear = filled($row['acquisition_year'] ?? null)
                    ? (int) $row['acquisition_year']
                    : $locked->acquisition_year;
                $fundingSource = filled($row['funding_source'] ?? null)
                    ? (string) $row['funding_source']
                    : $locked->funding_source;

                $distribution = Distribution::create([
                    'item_catalog_id' => $locked->id,
                    'specification_id' => $specification->id,
                    'to_room_id' => $room->id,
                    'quantity' => $quantity,
                    'source' => Distribution::SOURCE_STOCK,
                    'condition' => $condition->value,
                    'status' => $status->value,
                    'reason' => $reason,
                    'distributed_by' => $actor->id,
                    'distributed_at' => now(),
                    'note' => $note,
                ]);

                for ($i = 0; $i < $quantity; $i++) {
                    // Unit selalu dibuat BAIK agar laporan kerusakan otomatis
                    // menyimpan kondisi awal sebelum diperbarui secara terlacak.
                    $unit = $this->units->create($locked, $room, [
                        'specification_id' => $specification->id,
                        'condition' => Condition::BAIK,
                        'status' => $status,
                        'acquisition_year' => $acquisitionYear,
                        'funding_source' => $fundingSource,
                        'notes' => $note,
                    ], $actor);

                    if ($status !== UnitStatus::AKTIF) {
                        AssetUnitStatusLog::create([
                            'asset_unit_id' => $unit->id,
                            'from_status' => UnitStatus::AKTIF->value,
                            'to_status' => $status->value,
                            'source' => AssetUnitStatusLog::SOURCE_DISTRIBUTION,
                            'reason' => $reason,
                            'changed_by' => $actor->id,
                        ]);
                    }

                    if ($condition->isDamaged()) {
                        $classification = $condition === Condition::RUSAK_BERAT
                            ? DamageClassification::RUSAK_BERAT
                            : DamageClassification::RUSAK_RINGAN;

                        app(DamageReportService::class)->report(
                            $actor,
                            $unit,
                            $classification,
                            $note ?: 'Kerusakan tercatat otomatis saat distribusi barang.',
                        );
                    }
                }

                $created->push($distribution);
            }

            if (! $locked->isRecordedInInventory()) {
                $locked->forceFill(['acquisition_status' => AcquisitionStatus::INVENTARIS_TERCATAT->value])->save();
            }

            $this->units->syncCatalogCounters($locked->refresh());

            activity('distribusi')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties([
                    'katalog' => $locked->name,
                    'jumlah' => (int) $rows->sum(fn (array $row): int => (int) $row['quantity']),
                    'sisa_stok' => $locked->fresh()->remainingStockQuantity(),
                    'alokasi' => $rows->map(fn (array $row): array => [
                        'ruangan_id' => (int) $row['room_id'],
                        'jumlah' => (int) $row['quantity'],
                        'merek' => $row['brand'] ?? null,
                        'model' => $row['model_type'] ?? null,
                        'kondisi' => $row['condition'] ?? Condition::BAIK->value,
                        'status' => $row['status'] ?? UnitStatus::AKTIF->value,
                    ])->all(),
                ])
                ->log('Mendistribusikan stok penerimaan ke alokasi ruangan');

            return $created;
        });
    }

    /**
     * Status awal unit saat distribusi. Status khusus wajib memiliki alasan;
     * status yang membutuhkan transaksi lain ditolak agar tidak ada status
     * tanpa pencatatan pendukung.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: UnitStatus, 1: ?string}
     */
    private function resolveInitialStatus(array $row, int $index): array
    {
        $status = UnitStatus::tryFrom((string) ($row['status'] ?? UnitStatus::AKTIF->value));

        if ($status === null) {
            throw ValidationException::withMessages([
                "allocations.{$index}.status" => 'Status operasional yang dipilih tidak valid.',
            ]);
        }

        if (in_array($status, [UnitStatus::DIPINJAM, UnitStatus::DIHAPUS], true)) {
            throw ValidationException::withMessages([
                "allocations.{$index}.status" => 'Status '.$status->label().' tidak dapat dipilih saat distribusi. Gunakan menu peminjaman atau penghapusan agar transaksinya tercatat.',
            ]);
        }

        $reason = filled($row['reason'] ?? null) ? trim((string) $row['reason']) : null;

        if ($status !== UnitStatus::AKTIF && $reason === null) {
            throw ValidationException::withMessages([
                "allocations.{$index}.reason" => 'Alasan wajib diisi untuk status operasional selain Aktif.',
            ]);
        }

        return [$status, $status === UnitStatus::AKTIF ? null : $reason];
    }

    /**
     * Spesifikasi tujuan: pilihan eksplisit milik katalog, atau dibuat/dicari
     * dari merek + model yang diisi pada baris alokasi.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveSpecification(Item $catalog, array $row, int $index): Specification
    {
        if (filled($row['specification_id'] ?? null)) {
            $specification = Specification::query()
                ->where('item_catalog_id', $catalog->id)
                ->whereKey((int) $row['specification_id'])
                ->first();

            if ($specification === null) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.specification_id" => 'Spesifikasi yang dipilih bukan milik katalog ini.',
                ]);
            }

            return $specification;
        }

        return Specification::resolveFor(
            $catalog,
            $row['brand'] ?? null,
            $row['model_type'] ?? null,
        );
    }

    /** Ruangan tujuan harus berada dalam cakupan pengguna. */
    private function assertRoomAllowed(Room $room, User $actor, int $index): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        if (! $room->is_lab) {
            throw ValidationException::withMessages([
                "allocations.{$index}.room_id" => 'Ruangan tujuan harus laboratorium.',
            ]);
        }

        if ($actor->isOperator() && ! $actor->hasRoomScope($room->id)) {
            throw ValidationException::withMessages([
                "allocations.{$index}.room_id" => 'Ruangan tujuan berada di luar cakupan laboratorium Anda.',
            ]);
        }
    }

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
