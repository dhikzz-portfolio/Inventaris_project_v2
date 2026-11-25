<?php

namespace App\Console\Commands;

use App\Models\AcquisitionAllocation;
use App\Models\AssetUnit;
use App\Models\Specification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillUnitSpecifications extends Command
{
    protected $signature = 'inventory:backfill-specifications
        {--dry-run : Analisis saja tanpa mengubah database (default)}
        {--apply : Simpan hasil backfill yang buktinya tunggal}
        {--catalog= : Batasi ke satu ID katalog}
        {--report= : Path CSV laporan relatif terhadap storage/app (opsional)}';

    protected $description = 'Lengkapi specification_id unit lama hanya bila kandidatnya dapat dipastikan dari data yang ada.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $catalogId = $this->option('catalog');
        $rows = [];
        $counts = [
            'fixed' => 0,
            'unresolved' => 0,
            'already' => 0,
            'missing_catalog' => 0,
            'failed' => 0,
        ];

        AssetUnit::query()
            ->when($catalogId !== null && $catalogId !== '', fn ($query) => $query->where('item_catalog_id', (int) $catalogId))
            ->with(['catalog', 'specification'])
            ->orderBy('id')
            ->chunkById(200, function ($units) use (&$rows, &$counts, $apply): void {
                foreach ($units as $unit) {
                    $catalog = $unit->catalog;
                    $before = $unit->specification_id;

                    if ($unit->specification_id !== null) {
                        $counts['already']++;
                        $rows[] = [
                            $unit->id,
                            $unit->inventory_code,
                            $catalog?->name,
                            $unit->specification?->spec_code,
                            $before,
                            null,
                            'SUDAH_BENAR',
                            'Spesifikasi unit sudah terisi; tidak diubah.',
                            'TIDAK',
                        ];

                        continue;
                    }

                    if ($catalog === null) {
                        $counts['missing_catalog']++;
                        $rows[] = [
                            $unit->id,
                            $unit->inventory_code,
                            null,
                            null,
                            $before,
                            null,
                            'KATALOG_HILANG',
                            'Katalog induk tidak ditemukan.',
                            'YA',
                        ];

                        continue;
                    }

                    [$specification, $reason, $candidateIds] = $this->resolveCandidate($unit, $catalog);

                    if ($specification === null) {
                        $counts['unresolved']++;
                        $rows[] = [
                            $unit->id,
                            $unit->inventory_code,
                            $catalog->name,
                            null,
                            $before,
                            $candidateIds,
                            'TIDAK_DIPETAKAN',
                            $reason,
                            'YA',
                        ];

                        continue;
                    }

                    if ($apply) {
                        try {
                            $unit->forceFill(['specification_id' => $specification->id])->save();
                        } catch (\Throwable $exception) {
                            $counts['failed']++;
                            $rows[] = [
                                $unit->id,
                                $unit->inventory_code,
                                $catalog->name,
                                null,
                                $before,
                                (string) $specification->id,
                                'GAGAL',
                                'Gagal menyimpan: '.$exception->getMessage(),
                                'YA',
                            ];

                            continue;
                        }
                    }

                    $counts['fixed']++;
                    $rows[] = [
                        $unit->id,
                        $unit->inventory_code,
                        $catalog->name,
                        $specification->spec_code,
                        $before,
                        (string) $specification->id,
                        'DIPETAKAN',
                        $reason,
                        'TIDAK',
                    ];
                }
            });

        $reportPath = (string) ($this->option('report') ?: 'reports/specification-backfill-'.now()->format('Ymd-His').'.csv');
        Storage::disk('local')->makeDirectory(dirname($reportPath));
        $handle = fopen(Storage::disk('local')->path($reportPath), 'w');

        fputcsv($handle, [
            'asset_unit_id',
            'inventory_code',
            'catalog_name',
            'specification_found',
            'specification_id_before',
            'candidate_specification_id',
            'status',
            'reason',
            'manual_check',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->table(
            ['Hasil', 'Jumlah'],
            [
                ['Dapat dipetakan', $counts['fixed']],
                ['Tidak dapat dipastikan', $counts['unresolved']],
                ['Katalog induk hilang', $counts['missing_catalog']],
                ['Sudah memiliki spesifikasi', $counts['already']],
                ['Gagal diproses', $counts['failed']],
            ]
        );

        $this->line(($apply ? 'Perubahan disimpan.' : 'Simulasi saja — jalankan dengan --apply untuk menyimpan.').' Laporan: storage/app/'.$reportPath);

        return self::SUCCESS;
    }

    /**
     * Kandidat hanya diterima bila buktinya tunggal:
     * 1) katalog hanya memiliki satu spesifikasi;
     * 2) merek/tipe katalog cocok tepat dengan satu spesifikasi;
     * 3) alokasi pengadaan unit pada ruangan tersebut menunjuk satu spesifikasi.
     *
     * @return array{0: Specification|null, 1: string, 2: string}
     */
    private function resolveCandidate(AssetUnit $unit, $catalog): array
    {
        $specifications = Specification::query()
            ->where('item_catalog_id', $catalog->id)
            ->get();

        $candidateIds = $specifications->pluck('id')->implode('|');

        if ($specifications->count() === 1) {
            return [$specifications->first(), 'satu-satunya spesifikasi katalog', $candidateIds];
        }

        $brand = Specification::normalizeBrand($catalog->brand);
        $model = Specification::normalizeModel($catalog->model_type);

        $identityMatches = $specifications->filter(
            fn (Specification $specification) => Specification::normalizeBrand($specification->brand) === $brand
                && Specification::normalizeModel($specification->model_type) === $model
        );

        if ($identityMatches->count() === 1) {
            return [$identityMatches->first(), 'merek/tipe katalog cocok dengan satu spesifikasi', $identityMatches->first()->id];
        }

        $allocationSpecificationIds = AcquisitionAllocation::query()
            ->where('item_catalog_id', $catalog->id)
            ->where('room_id', $unit->room_id)
            ->whereNotNull('specification_id')
            ->pluck('specification_id')
            ->unique()
            ->values();

        if ($allocationSpecificationIds->count() === 1) {
            return [
                $specifications->firstWhere('id', $allocationSpecificationIds->first()),
                'alokasi pengadaan ruangan menunjuk satu spesifikasi',
                (string) $allocationSpecificationIds->first(),
            ];
        }

        if ($specifications->isEmpty()) {
            return [null, 'katalog belum memiliki spesifikasi', ''];
        }

        return [null, 'lebih dari satu kandidat ('.$specifications->count().' spesifikasi); perlu verifikasi manual', $candidateIds];
    }
}
