<?php

namespace App\Services;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\DamageClassification;
use App\Enums\NotificationType;
use App\Models\AcquisitionAllocation;
use App\Models\AssetUnit;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\ProcurementReceipt;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $this->assert($this->isRequesterOrAdmin($item, $actor), 'Hanya pembuat pengajuan atau Admin yang dapat mengirim pengajuan ini.');

        $submitted = $this->transition(
            $item,
            [AcquisitionStatus::DRAFT],
            AcquisitionStatus::MENUNGGU_KONFIRMASI,
            $actor,
            'Mengirim pengajuan pengadaan ke Editor/Validator',
            ['acquisition_submitted_at' => now()],
        );

        // Editor/Validator hanya menerima pengajuan yang berada dalam cakupan
        // laboratorium; pengajuan barang umum menjadi kewenangan Admin.
        if ($this->isLabScoped($submitted)) {
            NotifyApp::toRoles(['EDITOR_VALIDATOR'], NotificationType::PENGADAAN,
                "Pengajuan pengadaan \"{$submitted->name}\" menunggu validasi Anda.", route('items.acquisitions'));
        } else {
            NotifyApp::toRoles(['ADMIN'], NotificationType::PENGADAAN,
                "Pengajuan pengadaan \"{$submitted->name}\" di luar cakupan laboratorium dan menunggu keputusan Anda.", route('items.acquisitions'));
        }

        return $submitted;
    }

    /** Tahap 2 — Ketua Laboran mengonfirmasi (bukan menyetujui). */
    public function confirm(Item $item, User $actor): Item
    {
        $this->assert($actor->isEditorValidator(), 'Hanya Editor/Validator yang dapat mengonfirmasi pengajuan ini.');

        $confirmed = $this->transition(
            $item,
            [AcquisitionStatus::MENUNGGU_KONFIRMASI],
            AcquisitionStatus::MENUNGGU_PERSETUJUAN,
            $actor,
            'Memvalidasi pengajuan (bukan persetujuan akhir)',
            [
                'acquisition_confirmed_by' => $actor->id,
                'acquisition_confirmed_at' => now(),
            ],
        );

        NotifyApp::toRoles(['ADMIN'], NotificationType::PENGADAAN,
            "Pengajuan \"{$item->name}\" telah dikonfirmasi Editor/Validator dan menunggu keputusan Admin.",
            route('items.acquisitions'));

        $this->notifyRequester($confirmed,
            "Pengajuan \"{$confirmed->name}\" telah divalidasi Editor/Validator; menunggu keputusan Admin.");

        return $confirmed;
    }

    /** Tahap 3 — Admin menyetujui. */
    public function approve(Item $item, User $actor): Item
    {
        $this->assert($actor->isApproverLevel2(), 'Hanya Admin yang dapat menyetujui pengadaan.');

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
            "Pengadaan \"{$approved->name}\" DISETUJUI Admin. Lanjutkan proses pengadaan dan catat barang setelah diterima.");

        return $approved;
    }

    /** Tahap 3 — Admin menolak; alasan wajib tersimpan. */
    public function reject(Item $item, User $actor, ?string $note): Item
    {
        $this->assert($actor->isApproverLevel2(), 'Hanya Admin yang dapat menolak pengadaan.');

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
            NotifyApp::toRoles(['EDITOR_VALIDATOR'], NotificationType::PENGADAAN,
                "Pengadaan \"{$rejected->name}\" DITOLAK Admin — {$reason}.",
                route('items.acquisitions'));
        }

        $this->notifyRequester($rejected,
            "Pengadaan \"{$rejected->name}\" DITOLAK Admin — {$reason}.");

        return $rejected;
    }

    /** Laboran memulai proses pengadaan setelah disetujui. */
    public function start(Item $item, User $actor): Item
    {
        $this->assert($this->isRequesterOrAdmin($item, $actor), 'Hanya pembuat pengajuan atau Admin yang dapat memulai proses pengadaan.');
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
     * Setiap alokasi ruangan memiliki jumlah, kondisi, dan keterangan sendiri.
     * `$receipts` boleh dikunci dengan id alokasi (form baru) atau id ruangan
     * (kompatibilitas form lama). Nilai boleh berupa array
     * `{quantity, condition, note}` atau angka jumlah diterima.
     *
     * @param  array<int, array{quantity?: int|string, condition?: string|null, note?: string|null}|int|string>  $receipts
     */
    public function receive(
        Item $item,
        User $actor,
        array $receipts,
        ?string $defaultCondition = null,
        ?string $defaultNote = null,
        ?int $totalReceived = null,
    ): Item {
        $this->assert($this->isRequesterOrAdmin($item, $actor), 'Hanya pembuat pengajuan atau Admin yang dapat mencatat penerimaan barang.');
        $this->assert(
            in_array($item->acquisition_status, [
                AcquisitionStatus::DISETUJUI->value,
                AcquisitionStatus::DALAM_PROSES->value,
            ], true),
            'Barang hanya dapat dicatat setelah pengadaan disetujui dan belum pernah diterima.'
        );

        return DB::transaction(function () use ($item, $actor, $receipts, $defaultCondition, $defaultNote, $totalReceived): Item {
            $locked = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($locked->acquisition_received_at !== null) {
                throw ValidationException::withMessages([
                    'received' => 'Penerimaan barang untuk pengajuan ini sudah pernah dicatat.',
                ]);
            }

            // Pengadaan tanpa alokasi ruangan (alur stok): penerimaan hanya
            // mencatat jumlah aktual. Spesifikasi dan lokasi ditentukan saat
            // distribusi, bukan di sini.
            if (! $locked->allocations()->exists()) {
                if ($totalReceived === null) {
                    throw ValidationException::withMessages([
                        'received_total' => 'Isi jumlah aktual barang yang diterima.',
                    ]);
                }

                return $this->receiveAsStock($locked, $actor, $totalReceived, $defaultCondition, $defaultNote);
            }

            $allocations = $this->resolveAllocations($locked);
            $total = 0;
            $summaryCondition = $defaultCondition;

            foreach ($allocations as $allocation) {
                $receipt = $receipts[(int) $allocation->id] ?? $receipts[(int) $allocation->room_id] ?? null;

                if (is_array($receipt)) {
                    $qty = filled($receipt['quantity'] ?? null) ? (int) $receipt['quantity'] : (int) $allocation->quantity;
                    $condition = $receipt['condition'] ?? $defaultCondition;
                    $note = filled($receipt['note'] ?? null) ? trim((string) $receipt['note']) : $defaultNote;
                } elseif ($receipt !== null && $receipt !== '') {
                    $qty = (int) $receipt;
                    $condition = $defaultCondition;
                    $note = $defaultNote;
                } else {
                    $qty = (int) $allocation->quantity;
                    $condition = $defaultCondition;
                    $note = $defaultNote;
                }

                if ($qty < 0 || $qty > (int) $allocation->quantity) {
                    throw ValidationException::withMessages([
                        'received' => "Jumlah diterima untuk ruangan {$allocation->room?->code} harus antara 0 dan {$allocation->quantity}.",
                    ]);
                }

                $conditionEnum = Condition::tryFrom((string) ($condition ?? Condition::BAIK->value));

                if ($conditionEnum === null) {
                    throw ValidationException::withMessages([
                        'received' => 'Kondisi barang yang diterima tidak valid.',
                    ]);
                }

                if ($qty > 0) {
                    $summaryCondition = $conditionEnum->value;
                }

                $total += $qty;

                if ($allocation->exists) {
                    $allocation->forceFill([
                        'received_quantity' => $qty,
                        'condition' => $conditionEnum->value,
                        'note' => $note,
                    ])->save();
                }
            }

            if ($total < 1) {
                throw ValidationException::withMessages([
                    'received' => 'Minimal satu unit harus benar-benar diterima.',
                ]);
            }

            $summaryConditionEnum = Condition::tryFrom((string) ($summaryCondition ?? Condition::BAIK->value)) ?? Condition::BAIK;
            $before = $locked->acquisition_status;

            $locked->forceFill([
                'acquisition_status' => AcquisitionStatus::BARANG_DITERIMA->value,
                'acquisition_received_by' => $actor->id,
                'acquisition_received_at' => now(),
                'acquisition_received_quantity' => $total,
                'acquisition_received_condition' => $summaryConditionEnum->value,
                'acquisition_received_note' => $defaultNote,
            ])->save();

            $this->log($locked, $before, AcquisitionStatus::BARANG_DITERIMA, $actor, 'Mencatat barang yang diterima', $defaultNote, [
                'jumlah_diterima' => $total,
                'jumlah_diajukan' => (int) $locked->quantity,
                'selisih' => $total - (int) $locked->quantity,
                'kondisi_default' => $summaryConditionEnum->value,
                'alokasi' => $allocations->map(fn (AcquisitionAllocation $allocation): array => [
                    'alokasi_id' => $allocation->exists ? $allocation->id : null,
                    'ruangan_id' => (int) $allocation->room_id,
                    'jumlah' => (int) ($allocation->received_quantity ?? 0),
                    'kondisi' => $allocation->condition?->value,
                    'keterangan' => $allocation->note,
                ])->all(),
            ]);

            return $locked->fresh(['allocations.room', 'allocations.specification', 'room', 'category']);
        });
    }

    /**
     * Catat penerimaan untuk pengadaan tanpa alokasi ruangan.
     *
     * Hanya jumlah aktual yang disimpan (sebagai stok diterima). Unit fisik
     * dibuat nanti oleh distribusi setelah pengguna menentukan merek, model,
     * tahun, sumber dana, ruangan, kondisi, dan status operasional.
     */
    private function receiveAsStock(
        Item $locked,
        User $actor,
        int $totalReceived,
        ?string $defaultCondition,
        ?string $defaultNote,
    ): Item {
        if ($totalReceived < 1) {
            throw ValidationException::withMessages([
                'received_total' => 'Minimal satu unit harus benar-benar diterima.',
            ]);
        }

        if ($totalReceived > (int) $locked->quantity) {
            throw ValidationException::withMessages([
                'received_total' => "Jumlah diterima ({$totalReceived}) melebihi jumlah pengadaan ({$locked->quantity}). Catat kelebihan melalui pengadaan baru.",
            ]);
        }

        $condition = Condition::tryFrom((string) ($defaultCondition ?? Condition::BAIK->value));

        if ($condition === null) {
            throw ValidationException::withMessages([
                'condition' => 'Kondisi barang yang diterima tidak valid.',
            ]);
        }

        ProcurementReceipt::create([
            'item_catalog_id' => $locked->id,
            'quantity' => $totalReceived,
            'condition' => $condition->value,
            'note' => $defaultNote,
            'received_by' => $actor->id,
            'received_at' => now(),
        ]);

        $before = $locked->acquisition_status;

        $locked->forceFill([
            'acquisition_status' => AcquisitionStatus::BARANG_DITERIMA->value,
            'acquisition_received_by' => $actor->id,
            'acquisition_received_at' => now(),
            'acquisition_received_quantity' => $totalReceived,
            'acquisition_received_condition' => $condition->value,
            'acquisition_received_note' => $defaultNote,
        ])->save();

        $this->log($locked, $before, AcquisitionStatus::BARANG_DITERIMA, $actor, 'Mencatat barang yang diterima sebagai stok', $defaultNote, [
            'jumlah_diterima' => $totalReceived,
            'jumlah_diajukan' => (int) $locked->quantity,
            'selisih' => $totalReceived - (int) $locked->quantity,
            'kondisi_default' => $condition->value,
        ]);

        return $locked->fresh(['receipts', 'room', 'category']);
    }

    /** Menerbitkan unit inventaris dari barang yang sudah diterima. */
    public function recordInventory(Item $item, User $actor): Item
    {
        $this->assert($this->isRequesterOrAdmin($item, $actor), 'Hanya pembuat pengajuan atau Admin yang dapat mencatat inventaris.');

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

            // Alur stok: unit hanya terbit dari distribusi yang menentukan
            // spesifikasi dan lokasi. Pencatatan langsung akan membuat unit
            // tanpa kelompok spesifikasi yang sah.
            if (! $locked->allocations()->exists() && $locked->receipts()->exists()) {
                throw ValidationException::withMessages([
                    'inventory' => 'Barang sudah tercatat sebagai stok diterima. Gunakan menu Distribusi untuk menentukan merek, model, ruangan, kondisi, dan status unit.',
                ]);
            }

            $allocations = $this->resolveAllocations($locked);
            $defaultSpecificationId = Specification::resolveFor(
                $locked,
                $locked->brand,
                $locked->model_type,
            )->id;

            $created = 0;
            $damaged = 0;

            foreach ($allocations as $allocation) {
                $room = $allocation->room ?: Room::find($allocation->room_id);

                if ($room === null) {
                    continue;
                }

                $qty = (int) ($allocation->received_quantity ?? $allocation->quantity);
                $condition = $allocation->conditionEnum();

                for ($i = 0; $i < $qty; $i++) {
                    // Unit selalu dibuat BAIK; laporan kerusakan otomatis
                    // menyimpan kondisi awal dan mengubahnya secara terlacak.
                    $unit = $this->units->create($locked, $room, [
                        'specification_id' => $allocation->specification_id ?: $defaultSpecificationId,
                        'condition' => Condition::BAIK,
                        'status' => $allocation->statusEnum(),
                        'acquisition_year' => $allocation->acquisition_year,
                        'funding_source' => $allocation->funding_source,
                        'notes' => $allocation->note,
                    ], $actor);
                    $created++;

                    if ($condition->isDamaged()) {
                        $this->reportReceivedDamage($unit, $condition, $allocation, $actor);
                        $damaged++;
                    }
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
                'laporan_kerusakan_otomatis' => $damaged,
            ]);

            $roles = $this->isLabScoped($locked) ? ['EDITOR_VALIDATOR', 'ADMIN'] : ['ADMIN'];

            NotifyApp::toRoles($roles, NotificationType::PENGADAAN,
                "Pengadaan \"{$locked->name}\" telah tercatat sebagai inventaris ({$created} unit).",
                route('items.acquisitions'));

            return $locked->fresh(['allocations.room', 'room', 'category']);
        });
    }

    /**
     * Laporan kerusakan otomatis untuk unit yang diterima dalam kondisi rusak.
     * Idempoten per unit + alokasi agar percobaan ulang tidak membuat duplikat.
     */
    private function reportReceivedDamage(
        AssetUnit $unit,
        Condition $condition,
        AcquisitionAllocation $allocation,
        User $actor,
    ): void {
        $alreadyReported = DamageReport::query()
            ->where('asset_unit_id', $unit->id)
            ->when($allocation->exists, fn (Builder $query) => $query->where('acquisition_allocation_id', $allocation->id))
            ->exists();

        if ($alreadyReported) {
            return;
        }

        $classification = $condition === Condition::RUSAK_BERAT
            ? DamageClassification::RUSAK_BERAT
            : DamageClassification::RUSAK_RINGAN;

        $description = trim((string) ($allocation->note
            ?: 'Kerusakan tercatat otomatis saat penerimaan barang pengadaan.'));

        app(DamageReportService::class)->report(
            $actor,
            $unit,
            $classification,
            $description,
            null,
            [],
            $allocation->exists ? $allocation->id : null,
        );
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

    private function isRequesterOrAdmin(Item $item, User $actor): bool
    {
        return $actor->isAdmin() || (int) $item->acquisition_requested_by === (int) $actor->id;
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['acquisition' => $message]);
        }
    }
}
