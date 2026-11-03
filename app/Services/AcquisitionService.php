<?php

namespace App\Services;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\NotificationType;
use App\Models\AcquisitionAllocation;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine pengadaan tiga tahap.
 *
 * Laboran (DRAFT → MENUNGGU_KONFIRMASI) → Ketua Laboran
 * (MENUNGGU_KONFIRMASI → MENUNGGU_PERSETUJUAN) → Wakasek Sarpras
 * (MENUNGGU_PERSETUJUAN → DISETUJUI | DITOLAK) → pengadaan berjalan →
 * barang diterima → inventaris tercatat.
 *
 * Semua perubahan status dilakukan di sini, tidak pernah dari input frontend
 * mentah. Setiap transisi mencatat status sebelum/sesudah, pelaku, role, dan
 * catatan ke audit trail.
 */
class AcquisitionService
{
    public function __construct(private readonly AssetUnitService $units) {}

    /** Tahap 1 — Laboran mengirim draft kepada Ketua Laboran. */
    public function submit(Item $item, User $actor): Item
    {
        $this->assert($this->isRequesterOrSuperAdmin($item, $actor), 'Hanya pembuat pengajuan yang dapat mengirim pengajuan ini.');

        $submitted = $this->transition(
            $item,
            [AcquisitionStatus::DRAFT],
            AcquisitionStatus::MENUNGGU_KONFIRMASI,
            $actor,
            'Mengirim pengajuan pengadaan ke Ketua Laboran',
            ['acquisition_submitted_at' => now()],
        );

        // Ketua Laboran hanya menerima pengajuan yang berada dalam cakupan
        // laboratorium; pengajuan barang umum menjadi kewenangan Wakasek.
        if ($this->isLabScoped($submitted)) {
            NotifyApp::toRoles(['KETUA_LABORAN'], NotificationType::PENGADAAN,
                "Pengajuan pengadaan \"{$submitted->name}\" menunggu konfirmasi Anda.", route('items.acquisitions'));
        } else {
            NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGADAAN,
                "Pengajuan pengadaan \"{$submitted->name}\" di luar cakupan laboratorium dan menunggu keputusan Anda.", route('items.acquisitions'));
        }

        return $submitted;
    }

    /** Tahap 2 — Ketua Laboran mengonfirmasi (bukan menyetujui). */
    public function confirm(Item $item, User $actor): Item
    {
        $this->assert($actor->isKetuaLaboran(), 'Hanya Ketua Laboran yang dapat mengonfirmasi pengajuan ini.');

        $confirmed = $this->transition(
            $item,
            [AcquisitionStatus::MENUNGGU_KONFIRMASI],
            AcquisitionStatus::MENUNGGU_PERSETUJUAN,
            $actor,
            'Mengonfirmasi pengajuan telah diketahui (bukan persetujuan akhir)',
            [
                'acquisition_confirmed_by' => $actor->id,
                'acquisition_confirmed_at' => now(),
            ],
        );

        NotifyApp::toRoles(['SUPER_ADMIN'], NotificationType::PENGADAAN,
            "Pengajuan \"{$item->name}\" telah dikonfirmasi Ketua Laboran dan menunggu keputusan Wakasek Sarpras.",
            route('items.acquisitions'));

        $this->notifyRequester($confirmed,
            "Pengajuan \"{$confirmed->name}\" telah dikonfirmasi Ketua Laboran; menunggu keputusan Wakasek Sarpras.");

        return $confirmed;
    }

    /** Tahap 3 — Wakasek Sarpras menyetujui. */
    public function approve(Item $item, User $actor): Item
    {
        $this->assert($actor->isApproverLevel2(), 'Hanya Wakasek Sarpras yang dapat menyetujui pengadaan.');

        $approved = $this->transition(
            $item,
            [AcquisitionStatus::MENUNGGU_PERSETUJUAN],
            AcquisitionStatus::DISETUJUI,
            $actor,
            'Menyetujui pengajuan pengadaan (belum menjadi inventaris)',
            [
                'acquisition_approved_by' => $actor->id,
                'acquisition_decided_at' => now(),
                'acquisition_note' => null,
            ],
        );

        $this->notifyRequester($approved,
            "Pengadaan \"{$approved->name}\" DISETUJUI Wakasek Sarpras. Lanjutkan proses pengadaan dan catat barang setelah diterima.");

        return $approved;
    }

    /** Tahap 3 — Wakasek Sarpras menolak; alasan wajib tersimpan. */
    public function reject(Item $item, User $actor, ?string $note): Item
    {
        $this->assert($actor->isApproverLevel2(), 'Hanya Wakasek Sarpras yang dapat menolak pengadaan.');

        $reason = trim((string) $note);
        $this->assert($reason !== '', 'Alasan penolakan wajib diisi.');

        $rejected = $this->transition(
            $item,
            [AcquisitionStatus::MENUNGGU_PERSETUJUAN],
            AcquisitionStatus::DITOLAK,
            $actor,
            'Menolak pengajuan pengadaan',
            [
                'acquisition_approved_by' => $actor->id,
                'acquisition_decided_at' => now(),
                'acquisition_note' => $reason,
            ],
            $reason,
        );

        if ($this->isLabScoped($rejected)) {
            NotifyApp::toRoles(['KETUA_LABORAN'], NotificationType::PENGADAAN,
                "Pengadaan \"{$rejected->name}\" DITOLAK Wakasek Sarpras — {$reason}.",
                route('items.acquisitions'));
        }

        $this->notifyRequester($rejected,
            "Pengadaan \"{$rejected->name}\" DITOLAK Wakasek Sarpras — {$reason}.");

        return $rejected;
    }

    /** Laboran memulai proses pengadaan setelah disetujui. */
    public function start(Item $item, User $actor): Item
    {
        $this->assert($this->isRequesterOrSuperAdmin($item, $actor), 'Hanya pembuat pengajuan yang dapat memulai proses pengadaan.');
        $this->assert(
            in_array($item->acquisition_status, [AcquisitionStatus::DISETUJUI->value], true),
            'Pengadaan harus disetujui Wakasek sebelum diproses.'
        );

        return $this->transition(
            $item,
            [AcquisitionStatus::DISETUJUI],
            AcquisitionStatus::DALAM_PROSES,
            $actor,
            'Memulai proses pengadaan barang',
            ['acquisition_started_at' => now()],
        );
    }

    /**
     * Laboran mencatat barang yang benar-benar diterima.
     *
     * @param  array<int, int>  $received  [room_id => jumlah aktual diterima]
     */
    public function receive(Item $item, User $actor, array $received, ?string $condition, ?string $note = null): Item
    {
        $this->assert($this->isRequesterOrSuperAdmin($item, $actor), 'Hanya pembuat pengajuan yang dapat mencatat penerimaan barang.');
        $this->assert(
            in_array($item->acquisition_status, [
                AcquisitionStatus::DISETUJUI->value,
                AcquisitionStatus::DALAM_PROSES->value,
            ], true),
            'Barang hanya dapat dicatat setelah pengadaan disetujui dan belum pernah diterima.'
        );

        $conditionEnum = Condition::tryFrom((string) $condition);
        $this->assert($conditionEnum !== null, 'Kondisi barang yang diterima tidak valid.');

        return DB::transaction(function () use ($item, $actor, $received, $conditionEnum, $note): Item {
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->acquisition_received_at !== null) {
                throw ValidationException::withMessages([
                    'received' => 'Penerimaan barang untuk pengajuan ini sudah pernah dicatat.',
                ]);
            }

            $allocations = $this->resolveAllocations($locked);

            $total = 0;

            foreach ($allocations as $allocation) {
                $qty = array_key_exists((int) $allocation->room_id, $received)
                    ? (int) $received[(int) $allocation->room_id]
                    : (int) $allocation->quantity;

                if ($qty < 0 || $qty > (int) $allocation->quantity) {
                    throw ValidationException::withMessages([
                        'received' => "Jumlah diterima untuk ruangan {$allocation->room?->code} harus antara 0 dan {$allocation->quantity}.",
                    ]);
                }

                $total += $qty;

                if ($allocation->exists) {
                    $allocation->forceFill(['received_quantity' => $qty])->save();
                }
            }

            if ($total < 1) {
                throw ValidationException::withMessages([
                    'received' => 'Minimal satu unit harus benar-benar diterima.',
                ]);
            }

            $before = $locked->acquisition_status;

            $locked->forceFill([
                'acquisition_status' => AcquisitionStatus::BARANG_DITERIMA->value,
                'acquisition_received_by' => $actor->id,
                'acquisition_received_at' => now(),
                'acquisition_received_quantity' => $total,
                'acquisition_received_condition' => $conditionEnum->value,
                'acquisition_received_note' => $note,
            ])->save();

            $this->log($locked, $before, AcquisitionStatus::BARANG_DITERIMA, $actor, 'Mencatat barang yang diterima', $note, [
                'jumlah_diterima' => $total,
                'jumlah_diajukan' => (int) $locked->quantity,
                'selisih' => $total - (int) $locked->quantity,
                'kondisi' => $conditionEnum->value,
            ]);

            return $locked->fresh(['allocations.room', 'allocations.specification', 'room', 'category']);
        });
    }

    /** Menerbitkan unit inventaris dari barang yang sudah diterima. */
    public function recordInventory(Item $item, User $actor): Item
    {
        $this->assert($this->isRequesterOrSuperAdmin($item, $actor), 'Hanya pembuat pengajuan yang dapat mencatat inventaris.');

        return DB::transaction(function () use ($item, $actor): Item {
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->acquisition_status !== AcquisitionStatus::BARANG_DITERIMA->value) {
                throw ValidationException::withMessages([
                    'inventory' => 'Inventaris hanya dapat dicatat setelah barang benar-benar diterima.',
                ]);
            }

            if ($locked->units()->exists()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Unit inventaris untuk pengajuan ini sudah pernah diterbitkan.',
                ]);
            }

            $allocations = $this->resolveAllocations($locked);
            $defaultSpecificationId = Specification::resolveFor(
                $locked,
                $locked->brand,
                $locked->model_type,
            )->id;

            $condition = Condition::tryFrom((string) $locked->acquisition_received_condition) ?? Condition::BAIK;
            $created = 0;

            foreach ($allocations as $allocation) {
                $room = $allocation->room ?: Room::find($allocation->room_id);

                if ($room === null) {
                    continue;
                }

                $qty = $allocation->received_quantity ?? $allocation->quantity;

                for ($i = 0; $i < (int) $qty; $i++) {
                    $this->units->create($locked, $room, [
                        'specification_id' => $allocation->specification_id ?: $defaultSpecificationId,
                        'condition' => $condition,
                    ], $actor);
                    $created++;
                }

                if ($allocation->exists) {
                    $allocation->forceFill(['provisioned_at' => now()])->save();
                }
            }

            if ($created < 1) {
                throw ValidationException::withMessages([
                    'inventory' => 'Tidak ada unit yang dapat diterbitkan dari penerimaan ini.',
                ]);
            }

            $this->units->syncCatalogCounters($locked);

            $before = $locked->acquisition_status;

            $locked->forceFill([
                'acquisition_status' => AcquisitionStatus::INVENTARIS_TERCATAT->value,
            ])->save();

            $this->log($locked, $before, AcquisitionStatus::INVENTARIS_TERCATAT, $actor, 'Mencatat barang menjadi inventaris resmi', null, [
                'unit_dibuat' => $created,
            ]);

            $roles = $this->isLabScoped($locked) ? ['KETUA_LABORAN', 'SUPER_ADMIN'] : ['SUPER_ADMIN'];

            NotifyApp::toRoles($roles, NotificationType::PENGADAAN,
                "Pengadaan \"{$locked->name}\" telah tercatat sebagai inventaris ({$created} unit).",
                route('items.acquisitions'));

            return $locked->fresh(['allocations.room', 'room', 'category']);
        });
    }

    /**
     * Alokasi pengadaan; katalog lama tanpa alokasi diperlakukan sebagai satu
     * alokasi pada ruangan katalognya (tidak membuat alokasi duplikat).
     *
     * @return Collection<int, AcquisitionAllocation>
     */
    private function resolveAllocations(Item $item)
    {
        $allocations = $item->allocations()->with(['room', 'specification'])->get();

        if ($allocations->isNotEmpty()) {
            return $allocations;
        }

        return collect([
            new AcquisitionAllocation([
                'item_catalog_id' => $item->id,
                'room_id' => $item->room_id,
                'quantity' => (int) $item->quantity,
            ]),
        ]);
    }

    /**
     * @param  list<AcquisitionStatus>  $from
     * @param  array<string, mixed>  $extra
     */
    private function transition(
        Item $item,
        array $from,
        AcquisitionStatus $to,
        User $actor,
        string $action,
        array $extra = [],
        ?string $note = null,
        array $properties = [],
    ): Item {
        return DB::transaction(function () use ($item, $from, $to, $actor, $action, $extra, $note, $properties): Item {
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = (string) $locked->acquisition_status;
            $allowed = array_map(fn (AcquisitionStatus $status) => $status->value, $from);

            if (! in_array($before, $allowed, true)) {
                throw ValidationException::withMessages([
                    'acquisition' => 'Status pengajuan sudah berubah. Muat ulang halaman sebelum melanjutkan.',
                ]);
            }

            $locked->forceFill(array_merge($extra, [
                'acquisition_status' => $to->value,
            ]))->save();

            $this->log($locked, $before, $to, $actor, $action, $note, $properties);

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $properties */
    private function log(
        Item $item,
        string $before,
        AcquisitionStatus $after,
        User $actor,
        string $action,
        ?string $note = null,
        array $properties = [],
    ): void {
        activity('pengadaan')
            ->performedOn($item)
            ->causedBy($actor)
            ->withProperties(array_merge([
                'pengajuan_id' => $item->id,
                'aksi' => $action,
                'role' => $actor->role,
                'status_sebelum' => $before,
                'status_sesudah' => $after->value,
                'catatan' => $note,
            ], $properties))
            ->log($action.($note ? ": {$note}" : ''));
    }

    private function notifyRequester(Item $item, string $message): void
    {
        $requester = $item->requester;

        if ($requester !== null) {
            NotifyApp::to($requester, NotificationType::PENGADAAN, $message, route('items.acquisitions'));
        }
    }

    /** Pengajuan berlokasi laboratorium (bukan barang umum/gudang). */
    private function isLabScoped(Item $item): bool
    {
        $item->loadMissing('room');

        if ((bool) $item->room?->is_lab) {
            return true;
        }

        return $item->allocations()
            ->whereHas('room', fn ($query) => $query->where('is_lab', true))
            ->exists();
    }

    private function isRequesterOrSuperAdmin(Item $item, User $actor): bool
    {
        return $actor->isSuperAdmin() || (int) $item->acquisition_requested_by === (int) $actor->id;
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['acquisition' => $message]);
        }
    }
}
