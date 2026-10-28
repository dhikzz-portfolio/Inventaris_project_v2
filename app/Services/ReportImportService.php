<?php

namespace App\Services;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray as ToArrayConcern;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Impor laporan unit untuk pemulihan/migrasi data.
 *
 * Ini BUKAN pengganti backup database. Impor hanya memulihkan baris unit yang
 * identitasnya stabil (kode inventaris) dan tidak pernah menghapus data lain.
 */
class ReportImportService
{
    /**
     * Baca berkas CSV/XLSX menjadi baris ternormalisasi.
     *
     * @return list<array<string, mixed>>
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $sheets = Excel::toArray(new class implements ToArrayConcern, WithHeadingRow
            {
                public function array(array $array): void
                {
                    // Data dibaca langsung oleh Excel::toArray; callback ini
                    // hanya memenuhi kontrak impor.
                }
            }, $file);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak dapat dibaca. Gunakan CSV atau XLSX dengan baris header yang sesuai.',
            ]);
        }

        $rows = collect($sheets)->first() ?? [];

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->map(fn (array $row) => $this->normalizeRow($row))
            ->values()
            ->all();
    }

    /**
     * Analisis baris tanpa mengubah database.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>, can_import: bool}
     */
    public function analyze(array $rows, string $mode): array
    {
        $seen = [];
        $analyzed = [];

        foreach ($rows as $index => $row) {
            $errors = [];
            $code = $this->normalizeCode((string) ($row['kode_inventaris'] ?? ''));

            if ($code === '') {
                $errors[] = 'Kode inventaris kosong.';
            } elseif (! preg_match(InventoryCodeService::PATTERN, $code)) {
                $errors[] = "Format kode tidak valid: {$code} (contoh: G1-02-12).";
            } elseif (isset($seen[$code])) {
                $errors[] = 'Kode duplikat di dalam berkas (baris '.($seen[$code] + 2).').';
            }

            if ($code !== '') {
                $seen[$code] = $index;
            }

            $condition = $this->resolveCondition((string) ($row['kondisi'] ?? ''));
            if ($condition === null) {
                $errors[] = 'Kondisi tidak dikenali. Gunakan Baik / Rusak Ringan / Rusak Berat.';
            }

            $status = $this->resolveStatus((string) ($row['status'] ?? ''));
            if ($status === null) {
                $errors[] = 'Status tidak dikenali. Gunakan Aktif atau Hilang.';
            }

            $existing = $code !== '' ? AssetUnit::where('inventory_code', $code)->first() : null;
            $retired = $code !== '' && ! $existing
                ? AssetUnitCode::where('inventory_code', $code)->whereNotNull('revoked_at')->exists()
                : false;

            if ($retired) {
                $errors[] = "Kode {$code} sudah pensiun (pernah dipakai unit lain) dan tidak dapat dipulihkan.";
            }

            $action = $existing ? 'PERBARUI' : 'BUAT';
            $catalog = null;
            $room = null;
            $unitNo = null;

            if ($mode === 'update' && ! $existing) {
                $errors[] = "Mode perbarui: unit {$code} tidak ditemukan.";
            }

            if ($mode === 'create' && $existing) {
                $errors[] = "Mode buat: kode {$code} sudah terdaftar. Gunakan mode perbarui untuk mengubah unit yang ada.";
                $action = 'DITOLAK';
            }

            if ($mode === 'create' && ! $existing) {
                $catalogName = trim((string) ($row['nama_barang'] ?? ''));
                $catalog = $catalogName !== ''
                    ? Item::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($catalogName)])->first()
                    : null;

                if ($catalog === null) {
                    $errors[] = $catalogName === ''
                        ? 'Nama barang wajib diisi untuk membuat unit baru.'
                        : "Katalog \\\"{$catalogName}\\\" tidak ditemukan; buat katalog terlebih dahulu.";
                }

                $room = $this->resolveRoom($row);
                if ($room === null) {
                    $errors[] = 'Ruangan tidak ditemukan. Isi Gedung (huruf), Lantai, dan No. Ruangan yang sesuai master data.';
                }

                if ($room !== null && preg_match(InventoryCodeService::PATTERN, $code)) {
                    $expectedPrefix = $room->buildingCode().(int) $room->floor;
                    $parts = explode('-', $code);
                    $unitNo = (int) $parts[2];

                    if ($parts[0] !== $expectedPrefix || (int) $parts[1] !== (int) $room->room_no) {
                        $errors[] = "Kode {$code} tidak sesuai dengan lokasi ruangan {$room->code}.";
                    }

                    if (AssetUnitCode::where('room_id', $room->id)->where('unit_no', $unitNo)->exists()) {
                        $errors[] = "Nomor unit {$unitNo} di ruangan {$room->code} sudah pernah dipakai.";
                    }
                }
            }

            if ($existing) {
                $room = $existing->room;
                $catalog = $existing->catalog;
                $unitNo = (int) $existing->unit_no;
            }

            $analyzed[] = [
                'line' => $index + 2,
                'code' => $code,
                'name' => trim((string) ($row['nama_barang'] ?? '')),
                'room_label' => $room?->roomLabel() ?? '—',
                'condition' => $condition?->label(),
                'status' => $status?->label(),
                'action' => $action,
                'catalog_id' => $catalog?->id,
                'room_id' => $room?->id,
                'unit_no' => $unitNo,
                'condition_value' => $condition?->value,
                'status_value' => $status?->value,
                'acquisition_year' => is_numeric($row['tahun'] ?? null) ? (int) $row['tahun'] : null,
                'funding_source' => trim((string) ($row['sumber_dana'] ?? '')) ?: null,
                'register_no' => trim((string) ($row['no_register'] ?? '')) ?: null,
                'notes' => null,
                'errors' => $errors,
            ];
        }

        $invalid = collect($analyzed)->filter(fn (array $row) => $row['errors'] !== [])->count();

        return [
            'rows' => $analyzed,
            'mode' => $mode,
            'summary' => [
                'total' => count($analyzed),
                'valid' => count($analyzed) - $invalid,
                'invalid' => $invalid,
                'create' => collect($analyzed)->where('action', 'BUAT')->count(),
                'update' => collect($analyzed)->where('action', 'PERBARUI')->count(),
            ],
            'can_import' => $analyzed !== [] && $invalid === 0,
        ];
    }

    /**
     * Terapkan hasil analisis secara atomik. Semua baris harus valid; bila ada
     * kegagalan, seluruh perubahan dibatalkan.
     *
     * @param  array{rows: list<array<string, mixed>>, mode: string}  $analysis
     * @return array{created: int, updated: int}
     */
    public function apply(array $analysis, User $actor, string $filename): array
    {
        if (! ($analysis['rows'] ?? [])) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris yang dapat diimpor.']);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($analysis, $actor, $filename, &$created, &$updated) {
            foreach ($analysis['rows'] as $row) {
                if (($row['errors'] ?? []) !== []) {
                    throw ValidationException::withMessages([
                        'file' => 'Impor dibatalkan: masih ada baris bermasalah. Perbaiki berkas lalu ulangi pratinjau.',
                    ]);
                }

                $existing = AssetUnit::where('inventory_code', $row['code'])->lockForUpdate()->first();

                if ($existing && $analysis['mode'] === 'create') {
                    throw ValidationException::withMessages([
                        'file' => "Mode buat tidak boleh mengubah unit yang sudah ada ({$row['code']}).",
                    ]);
                }

                if ($existing) {
                    $existing->update([
                        'condition' => $row['condition_value'],
                        'status' => $row['status_value'],
                        'acquisition_year' => $row['acquisition_year'] ?? $existing->acquisition_year,
                        'funding_source' => $row['funding_source'] ?? $existing->funding_source,
                        'register_no' => $row['register_no'] ?? $existing->register_no,
                    ]);
                    $updated++;

                    continue;
                }

                if ($analysis['mode'] !== 'create') {
                    throw ValidationException::withMessages([
                        'file' => "Mode perbarui tidak boleh membuat unit baru ({$row['code']}).",
                    ]);
                }

                $unit = AssetUnit::create([
                    'item_catalog_id' => $row['catalog_id'],
                    'inventory_code' => $row['code'],
                    'room_id' => $row['room_id'],
                    'unit_no' => $row['unit_no'],
                    'condition' => $row['condition_value'],
                    'status' => $row['status_value'],
                    'acquisition_year' => $row['acquisition_year'],
                    'funding_source' => $row['funding_source'],
                    'register_no' => $row['register_no'],
                    'notes' => 'Dipulihkan melalui impor laporan '.$filename,
                ]);

                AssetUnitCode::create([
                    'asset_unit_id' => $unit->id,
                    'inventory_code' => $row['code'],
                    'room_id' => $row['room_id'],
                    'unit_no' => $row['unit_no'],
                    'issued_at' => now(),
                    'reason' => 'IMPOR',
                ]);

                $created++;
            }

            activity('laporan')
                ->causedBy($actor)
                ->withProperties([
                    'file' => $filename,
                    'mode' => $analysis['mode'],
                    'created' => $created,
                    'updated' => $updated,
                ])
                ->log('Impor laporan unit');
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $aliases = [
            'kode_inventaris' => ['kode_inventaris', 'kode', 'inventory_code', 'kode_unit'],
            'nama_barang' => ['nama_barang', 'nama', 'barang', 'name'],
            'kategori' => ['kategori', 'category'],
            'ruangan' => ['ruangan', 'room', 'nama_ruangan'],
            'gedung' => ['gedung', 'building', 'building_letter'],
            'lantai' => ['lantai', 'floor'],
            'no_ruangan' => ['no_ruangan', 'nomor_ruangan', 'room_no'],
            'kondisi' => ['kondisi', 'condition'],
            'status' => ['status'],
            'tahun' => ['tahun', 'tahun_perolehan', 'acquisition_year'],
            'sumber_dana' => ['sumber_dana', 'funding_source'],
            'no_register' => ['no_register', 'nomor_register', 'register_no'],
        ];

        $normalized = [];

        foreach ($aliases as $target => $keys) {
            $normalized[$target] = null;

            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $normalized[$target] = $row[$key];
                    break;
                }
            }
        }

        return $normalized;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function resolveCondition(string $value): ?Condition
    {
        $value = trim($value);

        if ($value === '') {
            return Condition::BAIK;
        }

        foreach (Condition::cases() as $condition) {
            if (strcasecmp($condition->value, $value) === 0 || strcasecmp($condition->label(), $value) === 0) {
                return $condition;
            }
        }

        return null;
    }

    private function resolveStatus(string $value): ?UnitStatus
    {
        $value = trim($value);

        if ($value === '') {
            return UnitStatus::AKTIF;
        }

        $allowed = [UnitStatus::AKTIF, UnitStatus::HILANG];

        foreach ($allowed as $status) {
            if (strcasecmp($status->value, $value) === 0 || strcasecmp($status->label(), $value) === 0) {
                return $status;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function resolveRoom(array $row): ?Room
    {
        $building = strtoupper(trim((string) ($row['gedung'] ?? '')));
        $building = preg_replace('/^GEDUNG\s+/', '', $building) ?: $building;
        $floor = (int) ($row['lantai'] ?? 0);
        $roomNo = (int) ($row['no_ruangan'] ?? 0);

        if ($building !== '' && $floor > 0 && $roomNo > 0) {
            $room = Room::query()
                ->whereHas('building', fn ($query) => $query->whereRaw('UPPER(code) = ?', [substr($building, 0, 1)]))
                ->where('floor', $floor)
                ->where('room_no', $roomNo)
                ->first();

            if ($room !== null) {
                return $room;
            }
        }

        $name = trim((string) ($row['ruangan'] ?? ''));

        return $name !== '' ? Room::where('name', $name)->first() : null;
    }
}
