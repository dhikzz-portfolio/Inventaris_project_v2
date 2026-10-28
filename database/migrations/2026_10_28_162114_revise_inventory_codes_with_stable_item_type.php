<?php

use App\Models\AssetUnit;
use App\Models\Item;
use App\Services\ItemTypeCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_unit_codes', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_unit_codes', 'item_type_code')) {
                $table->string('item_type_code', 3)->nullable()->after('room_id');
            }
        });

        $codeService = app(ItemTypeCodeService::class);

        Item::query()->orderBy('id')->each(function (Item $catalog) use ($codeService): void {
            $codeService->ensureCode($catalog);
            $catalog->refresh();

            AssetUnit::query()->where('item_catalog_id', $catalog->id)->orderBy('id')->each(function (AssetUnit $unit) use ($catalog): void {
                $segments = explode('-', (string) $unit->inventory_code);
                $room = $unit->room;
                $prefix = $room?->buildingCode().(int) $room?->floor;
                $roomNo = (int) ($room?->room_no ?? 0);
                $serial = (int) ($unit->unit_no ?? (end($segments) ?: 0));
                $newCode = sprintf('%s-%02d-%s-%02d', $prefix, $roomNo, $catalog->item_type_code, $serial);

                if (DB::table('asset_units')->where('inventory_code', $newCode)->where('id', '!=', $unit->id)->exists()) {
                    throw new RuntimeException("Migrasi kode inventaris bentrok pada {$newCode}; hentikan migrasi dan rekonsiliasi nomor unit.");
                }

                DB::table('asset_units')->where('id', $unit->id)->update([
                    'inventory_code' => $newCode,
                    'updated_at' => now(),
                ]);

                DB::table('asset_unit_codes')->where('asset_unit_id', $unit->id)->whereNull('revoked_at')->update([
                    'inventory_code' => $newCode,
                    'item_type_code' => $catalog->item_type_code,
                    'updated_at' => now(),
                ]);

                DB::table('asset_unit_codes')->where('asset_unit_id', $unit->id)->whereNotNull('revoked_at')->update([
                    'item_type_code' => $catalog->item_type_code,
                    'updated_at' => now(),
                ]);

                DB::table('asset_unit_movements')->where('asset_unit_id', $unit->id)->orderBy('id')->each(function (object $movement) use ($catalog): void {
                    $oldSegments = explode('-', (string) $movement->old_code);
                    $newSegments = explode('-', (string) $movement->new_code);

                    if (count($oldSegments) === 3) {
                        $movement->old_code = sprintf('%s-%s-%s-%s', $oldSegments[0], $oldSegments[1], $catalog->item_type_code, $oldSegments[2]);
                    }
                    if (count($newSegments) === 3) {
                        $movement->new_code = sprintf('%s-%s-%s-%s', $newSegments[0], $newSegments[1], $catalog->item_type_code, $newSegments[2]);
                    }

                    DB::table('asset_unit_movements')->where('id', $movement->id)->update([
                        'old_code' => $movement->old_code,
                        'new_code' => $movement->new_code,
                    ]);
                });
            });
        });

        AssetUnit::query()->orderBy('id')->each(function (AssetUnit $unit): void {
            $unit->loadMissing('catalog');
            $catalog = $unit->catalog;
            if ($catalog === null) {
                return;
            }

            $old = (string) $unit->inventory_code;
            $segments = explode('-', $old);
            if (count($segments) === 4) {
                return;
            }

            $room = $unit->room;
            $newCode = sprintf(
                '%s-%02d-%s-%02d',
                $room?->codePrefix() ?? 'G1',
                (int) ($room?->room_no ?? 1),
                $catalog->item_type_code,
                (int) $unit->unit_no
            );

            if (DB::table('asset_units')->where('inventory_code', $newCode)->where('id', '!=', $unit->id)->exists()) {
                throw new RuntimeException("Migrasi kode inventaris bentrok pada {$newCode}; hentikan migrasi dan rekonsiliasi nomor unit.");
            }

            DB::table('asset_units')->where('id', $unit->id)->update(['inventory_code' => $newCode, 'updated_at' => now()]);
            DB::table('asset_unit_codes')->where('asset_unit_id', $unit->id)->whereNull('revoked_at')->update([
                'inventory_code' => $newCode,
                'item_type_code' => $catalog->item_type_code,
                'updated_at' => now(),
            ]);
        });

        Schema::table('asset_units', function (Blueprint $table): void {
            $table->string('inventory_code', 16)->change();
        });
        Schema::table('asset_unit_codes', function (Blueprint $table): void {
            $table->string('inventory_code', 16)->change();
        });
        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->string('old_code', 16)->change();
            $table->string('new_code', 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_unit_movements', function (Blueprint $table): void {
            $table->string('old_code', 12)->change();
            $table->string('new_code', 12)->change();
        });
        Schema::table('asset_unit_codes', function (Blueprint $table): void {
            $table->string('inventory_code', 12)->change();
            $table->dropColumn('item_type_code');
        });
        Schema::table('asset_units', function (Blueprint $table): void {
            $table->string('inventory_code', 12)->change();
        });
    }
};
