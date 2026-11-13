<?php

namespace App\Services;

use App\Enums\AcquisitionStatus;
use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Exports\MonthlyReportExport;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray as ToArrayConcern;
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
            $sheets = Excel::toArray(new class implements ToArrayConcern
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

        $sheets = collect($sheets)
            ->filter(fn ($sheet) => is_array($sheet) && $sheet !== [])
            ->values();

        // Header dicari, bukan diasumsikan di baris pertama: file ekspor
        // laporan bulanan diawali kop surat. Sheet dipilih bila memuat seluruh
        // header wajib, sehingga sheet "Petunjuk" dan sheet transaksi lain
        // tidak dianggap sebagai data.
        foreach ($sheets as $sheet) {
            $headingIndex = $this->headingRowIndex($sheet);

            if ($headingIndex !== null) {
                return $this->rowsAfterHeading($sheet, $headingIndex);
            }
        }

        $this->assertHeaders($sheets->first() !== null ? $this->firstRowKeys($sheets->first()) : null);

        return [];
    }

    /**
     * Baris header pertama pada sheet yang memuat seluruh kolom wajib.
     *
     * @param  array<int, mixed>  $sheet
     */
    private function headingRowIndex(array $sheet): ?int
    {
        foreach (array_slice($sheet, 0, 40, true) as $index => $row) {
            if (is_array($row) && $this->hasRequiredHeaders($this->headingKeys($row))) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return list<string>
     */
    private function headingKeys(array $row): array
    {
        return array_map(
            fn ($cell): string => Str::slug(trim((string) $cell), '_'),
            array_values($row),
        );
    }

    /**
     * Baris data setelah header, berhenti di penanda blok tanda tangan.
     *
     * @param  array<int, mixed>  $sheet
     * @return list<array<string, mixed>>
     */
    private function rowsAfterHeading(array $sheet, int $headingIndex): array
    {
        $keys = $this->headingKeys($sheet[$headingIndex]);
        $columnCount = count($keys);
        $marker = Str::slug(MonthlyReportExport::SIGNATURE_MARKER, '_');
        $rows = [];

        foreach (array_slice($sheet, $headingIndex + 1) as $offset => $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = array_slice(array_pad(array_values($row), $columnCount, null), 0, $columnCount);

            if (Str::slug(trim((string) ($cells[0] ?? '')), '_') === $marker) {
                break;
            }

            if (collect($cells)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $normalized = [];

            foreach ($keys as $index => $key) {
                if ($key !== '') {
                    $normalized[$key] = $cells[$index] ?? null;
                }
            }

            // Nomor baris asli pada file, dipakai pesan kesalahan pratinjau.
            $normalized['__line'] = $headingIndex + $offset + 2;
            $rows[] = $this->normalizeRow($normalized);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $sheet
     * @return list<string>|null
     */
    private function firstRowKeys(array $sheet): ?array
    {
        foreach ($sheet as $row) {
            if (is_array($row) && collect($row)->filter(fn ($value) => filled($value))->isNotEmpty()) {
                return $this->headingKeys($row);
            }
        }

        return null;
    }

    /**
     * Tolak berkas dengan header yang tidak sesuai template sebelum satu baris
     * pun dianalisis. Pesan menyebut kolom wajib yang hilang.
     *
     * @param  list<string>|null  $available
     */
    private function assertHeaders(?array $available): void
    {
        $available ??= [];

        foreach ($this->requiredHeaders() as $label => $aliases) {
            if (array_intersect($aliases, $available) === []) {
                throw ValidationException::withMessages([
                    'file' => "Header kolom tidak sesuai template: kolom \"{$label}\" tidak ditemukan. Unduh Template Tabel Impor dan Panduan Impor, lalu gunakan header yang sama.",
                ]);
            }
        }
    }

    /** @param list<string> $keys */
    private function hasRequiredHeaders(array $keys): bool
    {
        foreach ($this->requiredHeaders() as $aliases) {
            if (array_intersect($aliases, $keys) === []) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, list<string>> */
    private function requiredHeaders(): array
    {
        return [
            'kondisi' => ['kondisi', 'condition'],
            'status' => ['status'],
        ];
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
        $unitCounters = [];
        $newCatalogCodes = [];
        $catalogCodeOwners = Item::query()
            ->whereNotNull('item_type_code')
            ->pluck('name', 'item_type_code')
            ->all();
        $knownCategories = Category::query()
            ->pluck('name')
            ->map(fn ($name): string => mb_strtolower(trim((string) $name)))
            ->all();

        foreach ($rows as $index => $row) {
            $errors = [];
            $warnings = [];
            $line = (int) ($row['__line'] ?? $index + 2);
            $code = $this->normalizeCode((string) ($row['kode_inventaris'] ?? ''));
            $autoCode = false;

            if ($code === '') {
                if ($mode !== 'create') {
                    $errors[] = 'Kode inventaris kosong.';
                }
            } elseif (! preg_match(InventoryCodeService::PATTERN, $code)) {
                $errors[] = "Format kode {$code} tidak valid (contoh: G1-02-012-01).";
            } elseif (isset($seen[$code])) {
                $errors[] = 'Kode ganda dengan baris '.$seen[$code].'.';
            }

            if ($code !== '') {
                $seen[$code] = $line;
            }

            $condition = $this->resolveCondition((string) ($row['kondisi'] ?? ''));
            if ($condition === null) {
                $errors[] = 'Kondisi tidak dikenali (Baik / Rusak Ringan / Rusak Berat).';
            }

            $status = $this->resolveStatus((string) ($row['status'] ?? ''));
            if ($status === null) {
                $errors[] = 'Status tidak dikenali (Aktif / Sedang Dipinjam / Hilang).';
            }

            $existing = $code !== '' ? AssetUnit::where('inventory_code', $code)->first() : null;
            $retired = $code !== '' && ! $existing
                ? AssetUnitCode::where('inventory_code', $code)->whereNotNull('revoked_at')->exists()
                : false;

            if ($retired) {
                $errors[] = "Kode {$code} sudah pensiun dan tidak bisa dipulihkan.";
            }

            $action = $existing ? 'PERBARUI' : 'BUAT';
            $catalog = null;
            $catalogName = '';
            $categoryName = trim((string) ($row['kategori'] ?? ''));
            $itemTypeCode = null;
            $room = null;
            $unitNo = null;
            $autoCatalog = false;

            if ($mode === 'update' && ! $existing) {
                if ($code !== '') {
                    $errors[] = "Unit {$code} belum terdaftar, tidak bisa diperbarui.";
                }
            }

            if ($mode === 'create' && $existing) {
                $errors[] = "Kode {$code} sudah terdaftar — pakai mode perbarui.";
                $action = 'DITOLAK';
            }

            if ($mode === 'create' && ! $existing) {
                $catalogName = trim((string) ($row['nama_barang'] ?? ''));

                if ($catalogName !== '' && str_contains(mb_strtolower($catalogName), '(contoh)')) {
                    $errors[] = 'Baris contoh template: ganti nama barang "(contoh)" atau hapus baris.';
                }

                $catalog = $catalogName !== ''
                    ? Item::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($catalogName)])->first()
                    : null;

                if ($catalogName === '') {
                    $errors[] = 'Nama barang wajib diisi.';
                }

                $autoCatalog = $catalog === null && $catalogName !== '';

                if ($autoCatalog && $categoryName !== '' && ! in_array(mb_strtolower($categoryName), $knownCategories, true)) {
                    $warnings[] = "Kategori \"{$categoryName}\" belum ada dan akan dibuat.";
                }

                $room = $this->resolveRoom($row);
                if ($room === null) {
                    $hints = $this->availableRoomHints();
                    $errors[] = 'Ruangan tidak ditemukan (cek Gedung, Lantai, No. Ruangan).'
                        .($hints !== '' ? " Tersedia: {$hints}." : ' Master data ruangan masih kosong.');
                }

                $parts = preg_match(InventoryCodeService::PATTERN, $code) ? explode('-', $code) : [];
                $importedTypeCode = $parts[2] ?? null;
                $itemTypeCode = $importedTypeCode;
                $canReissueUnitCode = $catalog === null || $catalog->item_type_code === null;

                if ($catalog !== null && $catalog->item_type_code !== null) {
                    $itemTypeCode = $catalog->item_type_code;
                } elseif ($catalog !== null || $autoCatalog) {
                    $key = mb_strtolower($catalog?->name ?? $catalogName);
                    $candidate = $newCatalogCodes[$key] ?? $itemTypeCode;
                    $codeOwner = $candidate !== null
                        ? $this->existingTypeCodeOwner($candidate, $newCatalogCodes, $catalogCodeOwners, $key)
                        : null;

                    if ($codeOwner !== null) {
                        $warnings[] = "Kode jenis {$candidate} sudah dipakai {$codeOwner}; sistem membuat kode jenis baru.";
                        $candidate = null;
                    }

                    if ($candidate === null) {
                        try {
                            $candidate = app(ItemTypeCodeService::class)->generate($catalogName !== '' ? $catalogName : $catalog->name, array_values($newCatalogCodes));
                        } catch (Throwable $exception) {
                            $errors[] = 'Kode jenis gagal dibuat: '.$exception->getMessage();
                        }
                    }

                    $itemTypeCode = $candidate;

                    if ($itemTypeCode !== null) {
                        $newCatalogCodes[$key] = $itemTypeCode;
                    }
                }

                $parsedTypeCode = $parts[2] ?? null;
                $needsGeneratedCode = $code === ''
                    || ($canReissueUnitCode && $parsedTypeCode !== null && $parsedTypeCode !== $itemTypeCode);

                if ($needsGeneratedCode && $room !== null && $itemTypeCode !== null) {
                    $originalCode = $code;

                    try {
                        $code = $this->generateUnitCode($room, $itemTypeCode, $unitCounters, $seen);
                        $autoCode = true;
                        $seen[$code] = $index;

                        if ($originalCode !== '') {
                            $warnings[] = "Kode {$originalCode} disesuaikan menjadi {$code}.";
                        }
                    } catch (Throwable $exception) {
                        $errors[] = 'Kode otomatis gagal dibuat: '.$exception->getMessage();
                    }
                }

                if ($room !== null && preg_match(InventoryCodeService::PATTERN, $code)) {
                    $expectedPrefix = $room->buildingCode().(int) $room->floor;
                    $parts = explode('-', $code);
                    $unitNo = (int) end($parts);

                    if (! $autoCode && $itemTypeCode !== null && $parts[2] !== $itemTypeCode) {
                        $errors[] = "Kode {$code} beda jenis dengan katalog {$catalogName} ({$itemTypeCode}).";
                    }

                    if (! $autoCode && ($parts[0] !== $expectedPrefix || (int) $parts[1] !== (int) $room->room_no)) {
                        $errors[] = "Kode {$code} tidak cocok dengan ruangan {$room->code}.";
                    }

                    if (! $autoCode && AssetUnitCode::where('room_id', $room->id)->where('unit_no', $unitNo)->exists()) {
                        $errors[] = "Nomor unit {$unitNo} di {$room->code} sudah dipakai.";
                    }
                }
            }

            if ($existing) {
                $room = $existing->room;
                $catalog = $existing->catalog;
                $catalogName = $catalog?->name ?? $catalogName;
                $itemTypeCode = $catalog?->item_type_code
                    ?? (preg_match(InventoryCodeService::PATTERN, $code) ? explode('-', $code)[2] : $itemTypeCode);
                $unitNo = (int) $existing->unit_no;
            }

            // Kolom kategori hanya mengisi katalog yang belum punya kategori;
            // kategori yang sudah terisi tidak ditimpa oleh berkas.
            if ($catalog !== null && $categoryName !== '' && $this->hasPlaceholderCategory($catalog)) {
                $warnings[] = in_array(mb_strtolower($categoryName), $knownCategories, true)
                    ? "Kategori {$catalog->name} akan diisi \"{$categoryName}\"."
                    : "Kategori \"{$categoryName}\" belum ada dan akan dibuat.";
            }

            $analyzed[] = [
                'line' => $line,
                'code' => $code,
                'name' => trim((string) ($row['nama_barang'] ?? '')),
                'room_label' => $room?->roomLabel() ?? '—',
                'condition' => $condition?->label(),
                'status' => $status?->label(),
                'action' => $action,
                'catalog_id' => $catalog?->id,
                'catalog_name' => $catalog?->name ?? $catalogName,
                'category_name' => $categoryName,
                'item_type_code' => $itemTypeCode,
                'auto_code' => $autoCode,
                'auto_catalog' => $autoCatalog,
                'room_id' => $room?->id,
                'unit_no' => $unitNo,
                'condition_value' => $condition?->value,
                'status_value' => $status?->value,
                'acquisition_year' => is_numeric($row['tahun'] ?? null) ? (int) $row['tahun'] : null,
                'funding_source' => trim((string) ($row['sumber_dana'] ?? '')) ?: null,
                'register_no' => trim((string) ($row['no_register'] ?? '')) ?: null,
                'brand' => trim((string) ($row['brand'] ?? '')) ?: null,
                'model_type' => trim((string) ($row['model_type'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'errors' => $errors,
                'warnings' => $warnings,
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

        $newCatalogs = collect($analysis['rows'])
            ->filter(fn (array $row) => ($row['auto_catalog'] ?? false) && filled($row['catalog_name'] ?? null))
            ->groupBy(fn (array $row) => mb_strtolower((string) $row['catalog_name']))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'name' => (string) $first['catalog_name'],
                    'category' => filled($first['category_name'] ?? null) ? (string) $first['category_name'] : 'Tanpa Kategori',
                    'item_type_code' => $first['item_type_code'] ?? null,
                    'acquisition_year' => $first['acquisition_year'] ?? (int) now()->year,
                    'funding_source' => $first['funding_source'] ?? '-',
                    'quantity' => $rows->count(),
                    'damaged' => $rows->filter(fn (array $row) => in_array(
                        $row['condition_value'],
                        [Condition::RUSAK_RINGAN->value, Condition::RUSAK_BERAT->value],
                        true,
                    ))->count(),
                    'lost' => $rows->where('status_value', UnitStatus::HILANG->value)->count(),
                ];
            });

        try {
            DB::transaction(function () use ($analysis, $actor, $filename, $newCatalogs, &$created, &$updated) {
                $createdCatalogs = [];

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
                        if ($existing->catalog !== null) {
                            $this->ensureCatalogCategory($existing->catalog, $row['category_name'] ?? null);
                        }

                        $existing->update([
                            'condition' => $row['condition_value'],
                            'status' => $row['status_value'],
                            'acquisition_year' => $row['acquisition_year'] ?? $existing->acquisition_year,
                            'funding_source' => $row['funding_source'] ?? $existing->funding_source,
                            'register_no' => $row['register_no'] ?? $existing->register_no,
                            'notes' => filled($row['notes'] ?? null) ? $row['notes'] : $existing->notes,
                        ]);

                        // Unit lama hasil impor sebelumnya bisa belum memiliki
                        // spesifikasi sehingga tidak muncul di daftar merek katalog.
                        // Lengkapi tanpa menimpa spesifikasi yang sudah ada.
                        if ($existing->specification_id === null && $existing->catalog !== null) {
                            $existing->forceFill([
                                'specification_id' => Specification::resolveFor(
                                    $existing->catalog,
                                    filled($row['brand'] ?? null) ? $row['brand'] : $existing->catalog->brand,
                                    filled($row['model_type'] ?? null) ? $row['model_type'] : $existing->catalog->model_type,
                                )->id,
                            ])->save();
                        }

                        $updated++;

                        continue;
                    }

                    if ($analysis['mode'] !== 'create') {
                        throw ValidationException::withMessages([
                            'file' => "Mode perbarui tidak boleh membuat unit baru ({$row['code']}).",
                        ]);
                    }

                    $catalogId = $row['catalog_id'] ?? null;
                    $catalog = $catalogId !== null ? Item::find($catalogId) : null;

                    if (($row['auto_catalog'] ?? false) && filled($row['catalog_name'] ?? null)) {
                        $key = mb_strtolower((string) $row['catalog_name']);
                        $catalog = $createdCatalogs[$key] ?? null;

                        if ($catalog === null) {
                            $stats = $newCatalogs[$key] ?? null;

                            if ($stats === null) {
                                throw ValidationException::withMessages([
                                    'file' => "Data katalog untuk baris {$row['code']} tidak lengkap.",
                                ]);
                            }

                            $category = $this->resolveCategory((string) $stats['category']);

                            $room = Room::find($row['room_id']);

                            $catalog = Item::create([
                                'internal_id' => $room !== null ? IdGenerator::internalId($room) : IdGenerator::stockInternalId(),
                                'location_code' => InstitutionSetting::current()->location_code,
                                'register_no' => IdGenerator::register()['register_no'],
                                'name' => $stats['name'],
                                'category_id' => $category->id,
                                'room_id' => $row['room_id'],
                                'acquisition_year' => $stats['acquisition_year'],
                                'funding_source' => $stats['funding_source'],
                                'quantity' => $stats['quantity'],
                                'qty_damaged' => $stats['damaged'],
                                'qty_lost' => $stats['lost'],
                                'unit' => 'unit',
                                'status' => ItemStatus::BAIK->value,
                                'acquisition_status' => AcquisitionStatus::INVENTARIS_TERCATAT->value,
                                'acquisition_requested_by' => $actor->id,
                                'acquisition_approved_by' => $actor->id,
                                'acquisition_decided_at' => now(),
                                'acquisition_received_by' => $actor->id,
                                'acquisition_received_at' => now(),
                                'acquisition_received_quantity' => $stats['quantity'],
                            ]);

                            $createdCatalogs[$key] = $catalog;
                        }

                        $catalogId = $catalog->id;
                    }

                    if ($catalog !== null) {
                        $this->ensureCatalogCategory($catalog, $row['category_name'] ?? null);

                        if (blank($catalog->item_type_code) && filled($row['item_type_code'] ?? null)) {
                            $owner = Item::query()
                                ->where('item_type_code', $row['item_type_code'])
                                ->whereKeyNot($catalog->getKey())
                                ->value('name');

                            if ($owner !== null) {
                                throw ValidationException::withMessages([
                                    'file' => "Kode jenis {$row['item_type_code']} sudah dipakai katalog {$owner}. Unggah ulang berkas agar pratinjau menyesuaikan kode.",
                                ]);
                            }

                            $catalog->forceFill(['item_type_code' => $row['item_type_code']])->save();
                        }

                        app(ItemTypeCodeService::class)->ensureCode($catalog);
                    }

                    if ($catalogId === null) {
                        throw ValidationException::withMessages([
                            'file' => "Katalog untuk baris {$row['code']} tidak tersedia.",
                        ]);
                    }

                    $specification = Specification::resolveFor(
                        $catalog,
                        filled($row['brand'] ?? null) ? $row['brand'] : $catalog->brand,
                        filled($row['model_type'] ?? null) ? $row['model_type'] : $catalog->model_type,
                    );

                    $unit = AssetUnit::create([
                        'item_catalog_id' => $catalogId,
                        'specification_id' => $specification->id,
                        'inventory_code' => $row['code'],
                        'room_id' => $row['room_id'],
                        'unit_no' => $row['unit_no'],
                        'condition' => $row['condition_value'],
                        'status' => $row['status_value'],
                        'acquisition_year' => $row['acquisition_year'],
                        'funding_source' => $row['funding_source'],
                        'register_no' => $row['register_no'],
                        'notes' => filled($row['notes'] ?? null) ? $row['notes'] : 'Dipulihkan melalui impor laporan '.$filename,
                    ]);

                    AssetUnitCode::create([
                        'asset_unit_id' => $unit->id,
                        'inventory_code' => $row['code'],
                        'room_id' => $row['room_id'],
                        'item_type_code' => $row['item_type_code'] ?? null,
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
        } catch (UniqueConstraintViolationException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'Impor dibatalkan karena ada kode yang sudah dipakai data lain. Periksa kembali kode inventaris dan kode jenis barang, lalu unggah ulang berkas.',
            ]);
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Isi kategori katalog yang belum punya kategori (atau masih memakai
     * penanda "Tanpa Kategori") dari kolom kategori pada berkas. Katalog yang
     * sudah punya kategori tidak ditimpa.
     */
    private function ensureCatalogCategory(Item $catalog, ?string $categoryName): void
    {
        $categoryName = trim((string) $categoryName);

        if ($categoryName === '' || ! $this->hasPlaceholderCategory($catalog)) {
            return;
        }

        $category = $this->resolveCategory($categoryName);

        // Kunci unik katalog (kategori, nama, merek, tipe) dapat bentrok bila
        // katalog yang sama sudah ada di kategori tujuan. Biarkan katalog tetap
        // memakai kategorinya sekarang daripada membatalkan seluruh impor.
        $conflict = Item::query()
            ->where('category_id', $category->id)
            ->where('name', $catalog->name)
            ->where('brand', (string) $catalog->brand)
            ->where('model_type', (string) $catalog->model_type)
            ->whereKeyNot($catalog->getKey())
            ->exists();

        if ($conflict) {
            return;
        }

        $catalog->forceFill(['category_id' => $category->id])->save();
    }

    private function hasPlaceholderCategory(Item $catalog): bool
    {
        $current = trim((string) ($catalog->category?->name ?? ''));

        return $current === '' || mb_strtolower($current) === 'tanpa kategori';
    }

    /**
     * Ambil kategori berdasarkan nama tanpa membedakan huruf besar/kecil, dan
     * buat kategori baru bila belum terdaftar.
     */
    private function resolveCategory(string $name): Category
    {
        $name = trim($name);

        return Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Category::create(['name' => $name]);
    }

    /**
     * Cari katalog yang sudah memakai kode jenis tersebut, baik di database
     * maupun katalog baru lain dalam berkas yang sedang dianalisis.
     *
     * @param  array<string, string>  $newCatalogCodes
     * @param  array<string, string>  $catalogCodeOwners
     */
    private function existingTypeCodeOwner(
        string $candidate,
        array $newCatalogCodes,
        array $catalogCodeOwners,
        string $currentKey,
    ): ?string {
        foreach ($newCatalogCodes as $catalogKey => $code) {
            if ($catalogKey !== $currentKey && $code === $candidate) {
                return $catalogKey;
            }
        }

        return $catalogCodeOwners[$candidate] ?? null;
    }

    /**
     * Buat kode unit berikutnya yang belum dipakai di ruangan tersebut.
     *
     * @param  array<string, int>  $counters
     * @param  array<string, int>  $seen
     */
    private function generateUnitCode(Room $room, string $itemTypeCode, array &$counters, array $seen): string
    {
        $key = (string) $room->id;
        $counters[$key] ??= app(InventoryCodeService::class)->nextUnitNo($room);

        do {
            $unitNo = $counters[$key]++;
            $code = app(InventoryCodeService::class)->format($room, $unitNo, $itemTypeCode);
        } while (isset($seen[$code])
            || AssetUnit::where('inventory_code', $code)->exists()
            || AssetUnit::where('room_id', $room->id)->where('unit_no', $unitNo)->exists()
            || AssetUnitCode::where('room_id', $room->id)->where('unit_no', $unitNo)->exists());

        return $code;
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
            'brand' => ['merek', 'brand'],
            'model_type' => ['tipe_model', 'tipe', 'model', 'model_type'],
            'notes' => ['keterangan', 'notes'],
        ];

        $normalized = [];

        foreach ($aliases as $target => $keys) {
            $normalized[$target] = null;

            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $value = $row[$key];
                    $normalized[$target] = is_string($value) ? $this->cleanCell($value) : $value;
                    break;
                }
            }
        }

        $normalized['__line'] = $row['__line'] ?? null;

        return $normalized;
    }

    /**
     * Bersihkan nilai sel dari sisa salinan halaman (tag HTML dan karakter
     * tak terlihat) agar nama barang, catatan, dan label tidak menyimpan markup.
     */
    private function cleanCell(string $value): string
    {
        $value = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], ' ', $value);
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
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

        $allowed = [UnitStatus::AKTIF, UnitStatus::DIPINJAM, UnitStatus::HILANG];

        foreach ($allowed as $status) {
            if (strcasecmp($status->value, $value) === 0 || strcasecmp($status->label(), $value) === 0) {
                return $status;
            }
        }

        return null;
    }

    private function availableRoomHints(): string
    {
        return Room::query()
            ->whereNotNull('room_no')
            ->where('room_no', '>', 0)
            ->where('floor', '>', 0)
            ->orderBy('floor')
            ->orderBy('room_no')
            ->limit(5)
            ->get()
            ->map(fn (Room $room): string => $room->locationCode())
            ->implode('; ');
    }

    /** @param array<string, mixed> $row */
    private function resolveRoom(array $row): ?Room
    {
        $building = strtoupper(trim((string) ($row['gedung'] ?? '')));
        $building = preg_replace('/^GEDUNG\s+/', '', $building) ?: $building;
        $floor = (int) ($row['lantai'] ?? 0);
        $roomNo = (int) ($row['no_ruangan'] ?? 0);

        if ($building !== '' && $floor > 0 && $roomNo > 0) {
            $letter = substr($building, 0, 1);

            $room = Room::query()
                ->where(function ($query) use ($letter): void {
                    $query->whereHas('building', fn ($buildingQuery) => $buildingQuery->whereRaw('UPPER(code) = ?', [$letter]))
                        ->orWhere(fn ($legacyQuery) => $legacyQuery
                            ->whereNull('building_id')
                            ->whereRaw('UPPER(building_letter) = ?', [$letter]));
                })
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
