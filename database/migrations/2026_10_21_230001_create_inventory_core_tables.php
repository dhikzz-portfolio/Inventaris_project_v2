<?php

use App\Services\InventoryBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi 2.1 — katalog jenis barang + unit inventaris fisik.
     *
     * Tabel `items` lama menjadi `item_catalogs` (identitas jenis barang).
     * Setiap baris katalog dipecah menjadi `quantity` baris `asset_units`,
     * masing-masing dengan kode inventaris `G1-02-12` dan riwayat kode.
     */
    public function up(): void
    {
        if (Schema::hasTable('items') && ! Schema::hasTable('item_catalogs')) {
            Schema::rename('items', 'item_catalogs');
        }

        if (Schema::hasTable('item_catalogs')) {
            Schema::table('item_catalogs', function (Blueprint $table) {
                if (! Schema::hasColumn('item_catalogs', 'brand')) {
                    $table->string('brand', 100)->default('')->after('name');
                }
                if (! Schema::hasColumn('item_catalogs', 'model_type')) {
                    $table->string('model_type', 100)->default('')->after('brand');
                }
                if (! Schema::hasColumn('item_catalogs', 'description')) {
                    $table->text('description')->nullable()->after('specification');
                }
            });

            $catalogIndexes = collect(Schema::getIndexes('item_catalogs'))->pluck('name');
            if (! $catalogIndexes->contains('item_catalogs_identity_unique')) {
                Schema::table('item_catalogs', function (Blueprint $table) {
                    $table->unique(['category_id', 'name', 'brand', 'model_type'], 'item_catalogs_identity_unique');
                });
            }
        }

        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (! Schema::hasColumn('rooms', 'building_letter')) {
                    $table->char('building_letter', 1)->nullable()->after('major_id');
                }
                if (! Schema::hasColumn('rooms', 'room_no')) {
                    $table->unsignedSmallInteger('room_no')->nullable()->after('floor');
                }
                if (! Schema::hasColumn('rooms', 'is_lab')) {
                    $table->boolean('is_lab')->default(false)->after('room_no');
                }
            });
        }

        if (! Schema::hasTable('asset_units')) {
            Schema::create('asset_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_catalog_id')->constrained('item_catalogs');
                $table->string('inventory_code', 8)->unique();
                $table->foreignId('room_id')->constrained('rooms');
                $table->unsignedTinyInteger('unit_no');
                $table->string('condition', 20)->default('BAIK');
                $table->string('status', 20)->default('AKTIF');
                $table->date('acquisition_date')->nullable();
                $table->unsignedSmallInteger('acquisition_year')->nullable();
                $table->decimal('acquisition_value', 15, 2)->nullable();
                $table->string('funding_source', 100)->nullable();
                $table->string('acquisition_source', 100)->nullable();
                $table->string('register_no', 50)->nullable();
                $table->string('photo_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('last_label_printed_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();

                $table->unique(['room_id', 'unit_no'], 'asset_units_room_unit_unique');
                $table->index(['item_catalog_id', 'status'], 'asset_units_catalog_status_index');
                $table->index(['status', 'condition'], 'asset_units_status_condition_index');
                $table->index('acquisition_year', 'asset_units_year_index');
            });
        }

        if (! Schema::hasTable('asset_unit_codes')) {
            Schema::create('asset_unit_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_unit_id')->constrained('asset_units');
                $table->string('inventory_code', 8)->unique();
                $table->foreignId('room_id')->constrained('rooms');
                $table->unsignedTinyInteger('unit_no');
                $table->timestamp('issued_at');
                $table->timestamp('revoked_at')->nullable();
                $table->string('reason', 30)->default('PENEMPATAN');
                $table->timestamps();

                $table->unique(['room_id', 'unit_no'], 'asset_unit_codes_room_unit_unique');
                $table->index(['asset_unit_id', 'revoked_at'], 'asset_unit_codes_unit_active_index');
            });
        }

        if (! Schema::hasTable('asset_unit_movements')) {
            Schema::create('asset_unit_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_unit_id')->constrained('asset_units');
                $table->foreignId('from_room_id')->constrained('rooms');
                $table->foreignId('to_room_id')->constrained('rooms');
                $table->string('old_code', 8);
                $table->string('new_code', 8);
                $table->foreignId('moved_by')->constrained('users');
                $table->timestamp('moved_at');
                $table->string('reason', 100)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['asset_unit_id', 'moved_at'], 'asset_unit_movements_unit_time_index');
            });
        }

        if (! Schema::hasTable('maintenance_records')) {
            Schema::create('maintenance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_unit_id')->constrained('asset_units');
                $table->date('performed_at');
                $table->string('type', 30)->default('PERBAIKAN');
                $table->text('description');
                $table->decimal('cost', 15, 2)->nullable();
                $table->string('performed_by', 150)->nullable();
                $table->string('document_path')->nullable();
                $table->timestamps();

                $table->index(['asset_unit_id', 'performed_at'], 'maintenance_unit_time_index');
            });
        }

        if (! Schema::hasTable('user_room_scopes')) {
            Schema::create('user_room_scopes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->foreignId('room_id')->constrained('rooms');
                $table->foreignId('assigned_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->unique(['user_id', 'room_id'], 'user_room_scopes_user_room_unique');
            });
        }

        $backfill = new InventoryBackfillService;
        $backfill->normalizeRooms();
        $backfill->backfillUnits();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_room_scopes');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('asset_unit_movements');
        Schema::dropIfExists('asset_unit_codes');
        Schema::dropIfExists('asset_units');

        if (Schema::hasTable('rooms')) {
            $indexes = collect(Schema::getIndexes('rooms'))->pluck('name');
            Schema::table('rooms', function (Blueprint $table) use ($indexes) {
                if ($indexes->contains('rooms_identity_unique')) {
                    $table->dropUnique('rooms_identity_unique');
                }
                $table->dropColumn(['building_letter', 'room_no', 'is_lab']);
            });
        }

        if (Schema::hasTable('item_catalogs')) {
            $indexes = collect(Schema::getIndexes('item_catalogs'))->pluck('name');
            Schema::table('item_catalogs', function (Blueprint $table) use ($indexes) {
                if ($indexes->contains('item_catalogs_identity_unique')) {
                    $table->dropUnique('item_catalogs_identity_unique');
                }
                $table->dropColumn(['brand', 'model_type', 'description']);
            });
            Schema::rename('item_catalogs', 'items');
        }
    }
};
