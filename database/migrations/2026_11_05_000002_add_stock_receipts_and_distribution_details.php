<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi V9 — distribusi berbasis stok penerimaan.
 *
 * - `procurement_receipts` mencatat jumlah barang yang benar-benar diterima
 *   per katalog. Penerimaan tidak lagi harus menentukan ruangan/spesifikasi;
 *   spesifikasi dan lokasi ditentukan saat distribusi.
 * - `distributions` menyimpan sumber distribusi (`STOK` membuat unit baru dari
 *   stok diterima, `UNIT` adalah perilaku lama yang memindahkan unit yang sudah
 *   ada), kondisi fisik, status operasional, dan alasan status khusus.
 * - `asset_unit_status_logs` menyimpan riwayat perubahan status operasional
 *   beserta sumbernya (manual, transaksi, distribusi) tanpa menghapus histori.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procurement_receipts')) {
            Schema::create('procurement_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_catalog_id')->constrained('item_catalogs')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('condition', 20)->nullable();
                $table->text('note')->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at');
                $table->timestamps();

                $table->index(['item_catalog_id', 'received_at'], 'procurement_receipts_catalog_time_index');
            });
        }

        if (Schema::hasTable('distributions')) {
            if (! Schema::hasColumn('distributions', 'source')) {
                Schema::table('distributions', function (Blueprint $table) {
                    $table->string('source', 20)->default('UNIT')->after('quantity');
                });
            }

            if (! Schema::hasColumn('distributions', 'condition')) {
                Schema::table('distributions', function (Blueprint $table) {
                    $table->string('condition', 20)->nullable()->after('source');
                });
            }

            if (! Schema::hasColumn('distributions', 'status')) {
                Schema::table('distributions', function (Blueprint $table) {
                    $table->string('status', 20)->nullable()->after('condition');
                });
            }

            if (! Schema::hasColumn('distributions', 'reason')) {
                Schema::table('distributions', function (Blueprint $table) {
                    $table->string('reason', 255)->nullable()->after('status');
                });
            }

            $indexes = collect(Schema::getIndexes('distributions'))->pluck('name');

            if (! $indexes->contains('distributions_catalog_source_index')) {
                Schema::table('distributions', function (Blueprint $table) {
                    $table->index(['item_catalog_id', 'source'], 'distributions_catalog_source_index');
                });
            }
        }

        // Katalog pengadaan mode stok belum memiliki lokasi; ruangan ditentukan
        // saat distribusi. Unit inventaris tetap wajib memiliki room_id.
        if (Schema::hasTable('item_catalogs')) {
            Schema::table('item_catalogs', function (Blueprint $table) {
                $table->foreignId('room_id')->nullable()->change();
            });
        }

        if (! Schema::hasTable('asset_unit_status_logs')) {
            Schema::create('asset_unit_status_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_unit_id')->constrained('asset_units')->cascadeOnDelete();
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                $table->string('source', 20)->default('MANUAL');
                $table->text('reason')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['asset_unit_id', 'created_at'], 'asset_unit_status_logs_unit_time_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_unit_status_logs');

        if (Schema::hasTable('distributions')) {
            Schema::table('distributions', function (Blueprint $table) {
                $table->dropIndex('distributions_catalog_source_index');
                $table->dropColumn(['source', 'condition', 'status', 'reason']);
            });
        }

        Schema::dropIfExists('procurement_receipts');
    }
};
