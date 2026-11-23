<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi hierarki barang: Kategori → Katalog → Spesifikasi → Unit.
     *
     * Migration ini tidak menghapus data. Setiap katalog lama yang belum punya
     * spesifikasi dibuatkan satu spesifikasi dari kolom `brand`/`model_type`
     * (atau nilai khusus "Tanpa Merek"/"Tidak Ditentukan"), lalu seluruh unit
     * dan alokasi pengadaan dihubungkan ke spesifikasi tersebut. Kode
     * inventaris unit tidak disentuh.
     */
    public function up(): void
    {
        $this->mergeDuplicateSpecifications();

        if (Schema::hasTable('specifications')) {
            $indexes = collect(Schema::getIndexes('specifications'))->pluck('name');

            if (! $indexes->contains('specifications_identity_unique')) {
                Schema::table('specifications', function (Blueprint $table) {
                    $table->unique(['item_catalog_id', 'brand', 'model_type'], 'specifications_identity_unique');
                });
            }
        }

        if (Schema::hasTable('asset_units')) {
            $indexes = collect(Schema::getIndexes('asset_units'))->pluck('name');

            if (! $indexes->contains('asset_units_specification_index')) {
                Schema::table('asset_units', function (Blueprint $table) {
                    $table->index('specification_id', 'asset_units_specification_index');
                });
            }
        }

        if (Schema::hasTable('acquisition_allocations')) {
            $indexes = collect(Schema::getIndexes('acquisition_allocations'))->pluck('name');

            // Satu ruangan kini boleh menerima beberapa spesifikasi (merek/tipe)
            // dari katalog yang sama. Duplikasi dalam satu form tetap dicegah
            // oleh validasi `distinct` pada room_id.
            //
            // Index pengganti dipasang LEBIH DULU: pada MySQL/MariaDB unique
            // lama masih dipakai sebagai index foreign key `item_catalog_id`,
            // sehingga tidak boleh dihapus sebelum ada index lain yang menutupinya.
            if (! $indexes->contains('acquisition_allocations_catalog_room_index')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->index(['item_catalog_id', 'room_id'], 'acquisition_allocations_catalog_room_index');
                });
            }

            if ($indexes->contains('acquisition_allocations_catalog_room_unique')) {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->dropUnique('acquisition_allocations_catalog_room_unique');
                });
            }
        }

        if (Schema::hasTable('acquisition_allocations') && ! Schema::hasColumn('acquisition_allocations', 'specification_id')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->unsignedBigInteger('specification_id')->nullable()->after('item_catalog_id');
            });

            // SQLite tidak dapat menambahkan foreign key pada tabel yang sudah ada.
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('acquisition_allocations', function (Blueprint $table) {
                    $table->foreign('specification_id')->references('id')->on('specifications')->nullOnDelete();
                });
            }

            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->index(['specification_id', 'room_id'], 'acquisition_allocations_spec_room_index');
            });
        }

        if (Schema::hasTable('acquisition_allocations') && ! Schema::hasColumn('acquisition_allocations', 'provisioned_at')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->timestamp('provisioned_at')->nullable()->after('quantity');
            });

            // Alokasi lama sudah menerbitkan unit pada saat persetujuan.
            DB::table('acquisition_allocations')->update(['provisioned_at' => now()]);
        }

        if (! Schema::hasTable('distributions')) {
            Schema::create('distributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_catalog_id')->constrained('item_catalogs')->cascadeOnDelete();
                $table->foreignId('specification_id')->constrained('specifications')->cascadeOnDelete();
                $table->foreignId('to_room_id')->constrained('rooms');
                $table->unsignedInteger('quantity');
                $table->foreignId('distributed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('distributed_at');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['specification_id', 'distributed_at'], 'distributions_spec_time_index');
                $table->index(['item_catalog_id', 'distributed_at'], 'distributions_catalog_time_index');
            });
        }

        if (! Schema::hasTable('distribution_units')) {
            Schema::create('distribution_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distribution_id')->constrained('distributions')->cascadeOnDelete();
                $table->foreignId('asset_unit_id')->constrained('asset_units');
                $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
                $table->foreignId('to_room_id')->constrained('rooms');
                $table->string('old_code', 20);
                $table->string('new_code', 20);
                $table->timestamps();

                // Satu unit hanya boleh dialokasikan sekali lewat distribusi.
                $table->unique('asset_unit_id', 'distribution_units_unit_unique');
            });
        }

        $this->backfillSpecifications();
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_units');
        Schema::dropIfExists('distributions');

        if (Schema::hasTable('acquisition_allocations') && Schema::hasColumn('acquisition_allocations', 'specification_id')) {
            Schema::table('acquisition_allocations', function (Blueprint $table) {
                $table->dropForeign(['specification_id']);
                $table->dropColumn(['specification_id', 'provisioned_at']);
            });
        }

        if (Schema::hasTable('asset_units')) {
            $indexes = collect(Schema::getIndexes('asset_units'))->pluck('name');
            if ($indexes->contains('asset_units_specification_index')) {
                Schema::table('asset_units', function (Blueprint $table) {
                    $table->dropIndex('asset_units_specification_index');
                });
            }
        }

        if (Schema::hasTable('specifications')) {
            $indexes = collect(Schema::getIndexes('specifications'))->pluck('name');
            if ($indexes->contains('specifications_identity_unique')) {
                Schema::table('specifications', function (Blueprint $table) {
                    $table->dropUnique('specifications_identity_unique');
                });
            }
        }
    }

    /**
     * Data lama bisa memuat lebih dari satu spesifikasi untuk kombinasi
     * merek/model yang sama. Gabungkan ke baris dengan id terkecil agar unique
     * index dapat dipasang tanpa kehilangan unit.
     */
    private function mergeDuplicateSpecifications(): void
    {
        if (! Schema::hasTable('specifications')) {
            return;
        }

        DB::table('specifications')->whereNull('brand')->update(['brand' => '']);
        DB::table('specifications')->whereNull('model_type')->update(['model_type' => '']);

        $duplicates = DB::table('specifications')
            ->select('item_catalog_id', 'brand', 'model_type', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('item_catalog_id', 'brand', 'model_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $mergeIds = DB::table('specifications')
                ->where('item_catalog_id', $duplicate->item_catalog_id)
                ->where('brand', $duplicate->brand)
                ->where('model_type', $duplicate->model_type)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id');

            if ($mergeIds->isEmpty()) {
                continue;
            }

            if (Schema::hasColumn('asset_units', 'specification_id')) {
                DB::table('asset_units')->whereIn('specification_id', $mergeIds)
                    ->update(['specification_id' => $duplicate->keep_id]);
            }

            if (Schema::hasColumn('acquisition_allocations', 'specification_id')) {
                DB::table('acquisition_allocations')->whereIn('specification_id', $mergeIds)
                    ->update(['specification_id' => $duplicate->keep_id]);
            }

            DB::table('specifications')->whereIn('id', $mergeIds)->delete();
        }
    }

    /**
     * Pastikan setiap katalog memiliki spesifikasi dan setiap unit/alokasi
     * terhubung ke spesifikasi yang benar.
     */
    private function backfillSpecifications(): void
    {
        if (! Schema::hasTable('item_catalogs') || ! Schema::hasTable('specifications')) {
            return;
        }

        $hasUnitSpec = Schema::hasColumn('asset_units', 'specification_id');
        $hasAllocationSpec = Schema::hasColumn('acquisition_allocations', 'specification_id');

        foreach (DB::table('item_catalogs')->orderBy('id')->get() as $catalog) {
            $defaultId = $this->specificationId(
                (int) $catalog->id,
                (string) ($catalog->brand ?? ''),
                (string) ($catalog->model_type ?? ''),
                $catalog->specification ?? null,
            );

            if (! $hasUnitSpec) {
                continue;
            }

            DB::table('asset_units')
                ->where('item_catalog_id', $catalog->id)
                ->whereNull('specification_id')
                ->update(['specification_id' => $defaultId, 'updated_at' => now()]);

            if ($hasAllocationSpec) {
                DB::table('acquisition_allocations')
                    ->where('item_catalog_id', $catalog->id)
                    ->whereNull('specification_id')
                    ->update(['specification_id' => $defaultId, 'updated_at' => now()]);
            }
        }
    }

    private function specificationId(int $catalogId, string $brand, string $model, ?string $description): int
    {
        $brand = trim($brand) !== '' ? trim($brand) : 'Tanpa Merek';
        $model = trim($model) !== '' ? trim($model) : 'Tidak Ditentukan';

        $existing = DB::table('specifications')
            ->where('item_catalog_id', $catalogId)
            ->where('brand', $brand)
            ->where('model_type', $model)
            ->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        $sequence = (int) DB::table('specifications')->where('item_catalog_id', $catalogId)->count() + 1;

        do {
            $code = sprintf('SPK-C%d-%03d', $catalogId, $sequence);
            $sequence++;
        } while (DB::table('specifications')->where('spec_code', $code)->exists());

        return (int) DB::table('specifications')->insertGetId([
            'item_catalog_id' => $catalogId,
            'brand' => $brand,
            'model_type' => $model,
            'description' => $description,
            'spec_code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
