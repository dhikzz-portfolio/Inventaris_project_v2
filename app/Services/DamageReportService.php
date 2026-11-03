<?php

namespace App\Services;

use App\Enums\DamageClassification;
use App\Enums\DamageReportStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\AssetUnit;
use App\Models\DamageReport;
use App\Models\DamageReportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pelaporan kondisi barang rusak.
 *
 * Hanya mencatat kondisi dan riwayat penanganan. Tidak menghapus unit,
 * mengurangi stok, memindahkan barang, atau membuat transaksi penghapusan.
 * Tahap persetujuan perbaikan belum diaktifkan (PENDING DECISION 3).
 */
class DamageReportService
{
    public function __construct(private readonly AssetUnitService $units) {}

    public function report(
        User $actor,
        AssetUnit $unit,
        DamageClassification $classification,
        string $description,
        ?string $photoPath = null,
        array $photoPaths = [],
    ): DamageReport {
        return DB::transaction(function () use ($actor, $unit, $classification, $description, $photoPath, $photoPaths): DamageReport {
            $locked = AssetUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['room', 'catalog']);
            $previous = $locked->condition;

            $openReport = DamageReport::query()
                ->where('asset_unit_id', $locked->id)
                ->where('status', '!=', DamageReportStatus::SELESAI->value)
                ->exists();

            if ($openReport) {
                throw ValidationException::withMessages([
                    'asset_unit_id' => 'Unit ini masih memiliki laporan kerusakan yang belum selesai ditangani.',
                ]);
            }

            $report = DamageReport::create([
                'asset_unit_id' => $locked->id,
                'reported_by' => $actor->id,
                'classification' => $classification->value,
                'previous_condition' => $previous->value,
                'reported_condition' => $classification->condition()->value,
                'description' => $description,
                'photo_path' => $photoPath,
                'status' => DamageReportStatus::DILAPORKAN->value,
            ]);

            foreach (array_values(array_unique(array_filter($photoPaths))) as $path) {
                $report->photos()->create(['path' => $path]);
            }

            // Kondisi unit mengikuti klasifikasi laporan dan tetap dapat
            // ditelusuri karena kondisi sebelumnya disimpan pada laporan.
            $this->units->updateCondition($locked, $classification->condition(), $actor);

            $this->log($report, $actor, 'Membuat laporan kerusakan', null, DamageReportStatus::DILAPORKAN->value, $description);

            activity('kerusakan')
                ->performedOn($report)
                ->causedBy($actor)
                ->withProperties([
                    'unit' => $locked->inventory_code,
                    'klasifikasi' => $classification->value,
                    'kondisi_sebelum' => $previous->value,
                ])
                ->log('Melaporkan kerusakan unit inventaris');

            $this->notifyNewReport($locked, $report, $classification);

            return $report->fresh(['unit.catalog', 'unit.room', 'reporter']);
        });
    }

    public function updateStatus(
        DamageReport $report,
        DamageReportStatus $status,
        ?string $note,
        User $actor,
    ): DamageReport {
        return DB::transaction(function () use ($report, $status, $note, $actor): DamageReport {
            $locked = DamageReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['unit', 'reporter']);
            $before = $locked->status;

            if ($before === $status) {
                throw ValidationException::withMessages([
                    'status' => 'Status penanganan sudah bernilai sama.',
                ]);
            }

            if (! $before->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => "Perubahan status dari {$before->label()} ke {$status->label()} tidak diizinkan.",
                ]);
            }

            $locked->forceFill([
                'status' => $status->value,
                'handled_by' => $actor->id,
                'handled_at' => now(),
                'resolution_note' => $note ?: $locked->resolution_note,
            ])->save();

            $this->log($locked, $actor, 'Memperbarui status penanganan', $before->value, $status->value, $note);

            $this->notifyStatusChange($locked, $before, $status, $actor);

            activity('kerusakan')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['status_sebelum' => $before->value, 'status_sesudah' => $status->value])
                ->log('Memperbarui status penanganan kerusakan');

            return $locked->fresh(['unit.catalog', 'unit.room', 'reporter', 'handler']);
        });
    }

    /**
     * Penerima ditentukan oleh cakupan unit dan klasifikasi kerusakan:
     * Ketua Laboran untuk unit laboratorium, Wakasek Sarpras untuk laporan
     * rusak berat yang memerlukan tindak lanjut. Admin akun tidak menerima
     * notifikasi transaksi inventaris.
     */
    private function notifyNewReport(AssetUnit $unit, DamageReport $report, DamageClassification $classification): void
    {
        $link = route('damage-reports.show', $report);
        $label = $unit->inventory_code.' — '.($unit->catalog?->name ?? 'unit inventaris');

        if ($unit->room?->is_lab) {
            NotifyApp::toRoles([Role::KETUA_LABORAN->value], NotificationType::KERUSAKAN,
                "Laporan kerusakan baru ({$classification->label()}): {$label}.", $link);
        }

        if ($classification === DamageClassification::RUSAK_BERAT) {
            NotifyApp::toRoles([Role::SUPER_ADMIN->value], NotificationType::KERUSAKAN,
                "Laporan rusak berat: {$label} memerlukan peninjauan tindak lanjut.", $link);
        }
    }

    private function notifyStatusChange(
        DamageReport $report,
        DamageReportStatus $before,
        DamageReportStatus $after,
        User $actor,
    ): void {
        $link = route('damage-reports.show', $report);
        $label = $report->unit?->inventory_code ?? 'unit inventaris';

        if ($report->reporter !== null && (int) $report->reporter->id !== (int) $actor->id) {
            NotifyApp::to($report->reporter, NotificationType::KERUSAKAN,
                "Status laporan kerusakan {$label} berubah dari {$before->label()} menjadi {$after->label()}.", $link);
        }

        if ($after === DamageReportStatus::MENUNGGU_PENGHAPUSAN) {
            NotifyApp::toRoles([Role::SUPER_ADMIN->value], NotificationType::KERUSAKAN,
                "Laporan kerusakan {$label} menunggu tindak lanjut penghapusan.", $link);
        }
    }

    private function log(
        DamageReport $report,
        User $actor,
        string $action,
        ?string $before,
        ?string $after,
        ?string $note,
    ): void {
        DamageReportLog::create([
            'damage_report_id' => $report->id,
            'user_id' => $actor->id,
            'action' => $action,
            'status_before' => $before,
            'status_after' => $after,
            'note' => $note,
        ]);
    }
}
