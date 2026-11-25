<?php

namespace Tests\Feature;

use App\Enums\UnitStatus;
use App\Livewire\Distribution\StockDistributionForm;
use App\Models\AssetUnit;
use App\Models\AssetUnitStatusLog;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Distribution;
use App\Models\Item;
use App\Models\ProcurementReceipt;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private Category $category;

    private Room $labRpl;

    private Room $labDkv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
        $this->labRpl = Room::where('code', 'H1-01')->firstOrFail();
        $this->labDkv = Room::where('code', 'G2-02')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function procurement(array $overrides = []): Item
    {
        $payload = array_merge([
            'procurement_mode' => 'STOK',
            'name' => 'Kursi Stok '.uniqid(),
            'category_id' => $this->category->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 25,
            'unit' => 'unit',
        ], $overrides);

        $this->actingAs($this->wakasek)
            ->post(route('items.store'), $payload)
            ->assertSessionHasNoErrors();

        return Item::where('name', $payload['name'])->firstOrFail();
    }

    private function receive(Item $catalog, int $total): void
    {
        $this->actingAs($this->wakasek)
            ->post(route('items.acquisitions.receive', $catalog), [
                'received_total' => $total,
                'condition' => 'BAIK',
            ])
            ->assertSessionHasNoErrors();
    }

    /** @param list<array<string, mixed>> $rows */
    private function distribute(Item $catalog, array $rows)
    {
        return Livewire::actingAs($this->wakasek)
            ->test(StockDistributionForm::class)
            ->set('catalogId', (string) $catalog->id)
            ->set('rows', $rows);
    }

    public function test_pengadaan_mode_stok_hanya_mencatat_katalog_dan_jumlah(): void
    {
        $catalog = $this->procurement();

        $this->assertSame('DISETUJUI', $catalog->acquisition_status);
        $this->assertNull($catalog->room_id);
        $this->assertSame(0, $catalog->allocations()->count());
        $this->assertSame(0, $catalog->units()->count());
        $this->assertSame(0, $catalog->receivedStockQuantity());
    }

    public function test_penerimaan_membuat_stok_tanpa_unit_inventaris(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 20);

        $catalog->refresh();

        $this->assertSame('BARANG_DITERIMA', $catalog->acquisition_status);
        $this->assertSame(20, $catalog->acquisition_received_quantity);
        $this->assertSame(1, ProcurementReceipt::where('item_catalog_id', $catalog->id)->count());
        $this->assertSame(20, $catalog->receivedStockQuantity());
        $this->assertSame(20, $catalog->remainingStockQuantity());
        $this->assertSame(0, $catalog->units()->count());
    }

    public function test_penerimaan_melebihi_jumlah_pengadaan_ditolak(): void
    {
        $catalog = $this->procurement(['quantity' => 5]);

        $this->actingAs($this->wakasek)
            ->post(route('items.acquisitions.receive', $catalog), [
                'received_total' => 6,
                'condition' => 'BAIK',
            ])
            ->assertSessionHasErrors('received_total');

        $this->assertSame(0, ProcurementReceipt::count());
        $this->assertSame(0, $catalog->units()->count());
    }

    public function test_distribusi_membuat_unit_dengan_spesifikasi_per_alokasi(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 20);

        $component = $this->distribute($catalog, [
            [
                'room_id' => (string) $this->labRpl->id,
                'brand' => 'Futura',
                'model_type' => 'Kursi Plastik Tipe A',
                'quantity' => 10,
                'condition' => 'BAIK',
                'status' => 'AKTIF',
            ],
            [
                'room_id' => (string) $this->labDkv->id,
                'brand' => 'Chitose',
                'model_type' => 'Kursi Susun Premium',
                'quantity' => 5,
                'condition' => 'BAIK',
                'status' => 'AKTIF',
                'acquisition_year' => now()->year - 1,
                'funding_source' => 'Komite',
            ],
        ]);

        $component->call('submit')->assertHasNoErrors();

        $this->assertSame(2, Distribution::where('item_catalog_id', $catalog->id)->count());
        $this->assertSame(15, $catalog->units()->count());
        $this->assertSame(5, $catalog->fresh()->remainingStockQuantity());

        $futura = Specification::where('item_catalog_id', $catalog->id)->where('brand', 'Futura')->firstOrFail();
        $chitose = Specification::where('item_catalog_id', $catalog->id)->where('brand', 'Chitose')->firstOrFail();

        $this->assertSame(10, $futura->units()->where('room_id', $this->labRpl->id)->count());
        $this->assertSame(5, $chitose->units()->where('room_id', $this->labDkv->id)->count());
        $this->assertSame(now()->year - 1, $chitose->units()->first()->acquisition_year);
        $this->assertSame('Komite', $chitose->units()->first()->funding_source);

        // Stok lain tidak ikut terpakai dan distribusi tercatat sebagai sumber stok.
        $this->assertSame(5, $catalog->fresh()->remainingStockQuantity());
        $this->assertSame(Distribution::SOURCE_STOCK, Distribution::first()->source);
    }

    public function test_distribusi_bertahap_dan_sisa_stok_dapat_dipakai_kemudian(): void
    {
        $catalog = $this->procurement(['quantity' => 25]);
        $this->receive($catalog, 25);

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Standard',
            'quantity' => 10,
        ]])->call('submit')->assertHasNoErrors();

        $this->assertSame(15, $catalog->fresh()->remainingStockQuantity());

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labDkv->id,
            'brand' => 'Futura',
            'model_type' => 'Standard',
            'quantity' => 10,
        ]])->call('submit')->assertHasNoErrors();

        $this->assertSame(5, $catalog->fresh()->remainingStockQuantity());

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Standard',
            'quantity' => 5,
        ]])->call('submit')->assertHasNoErrors();

        $this->assertSame(0, $catalog->fresh()->remainingStockQuantity());
        $this->assertSame(25, $catalog->units()->count());
        $this->assertSame('INVENTARIS_TERCATAT', $catalog->fresh()->acquisition_status);
    }

    public function test_distribusi_melebihi_sisa_stok_ditolak(): void
    {
        $catalog = $this->procurement(['quantity' => 25]);
        $this->receive($catalog, 5);

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Standard',
            'quantity' => 6,
        ]])->call('submit')->assertHasErrors('rows');

        $this->assertSame(0, Distribution::count());
        $this->assertSame(0, $catalog->units()->count());
        $this->assertSame(5, $catalog->fresh()->remainingStockQuantity());
    }

    public function test_kondisi_rusak_saat_distribusi_tetap_menjadi_inventaris_dan_membuat_laporan(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 3);

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Retak',
            'quantity' => 3,
            'condition' => 'RUSAK_RINGAN',
            'note' => 'Kemasan penyok saat pengiriman',
        ]])->call('submit')->assertHasNoErrors();

        $units = $catalog->units()->get();

        $this->assertCount(3, $units);
        $this->assertTrue($units->every(fn (AssetUnit $unit) => $unit->condition->value === 'RUSAK_RINGAN'));
        $this->assertSame(3, DamageReport::whereIn('asset_unit_id', $units->pluck('id'))->count());

        // Barang rusak tidak mengurangi atau menghapus unit.
        $this->assertSame(3, $catalog->fresh()->units()->count());
    }

    public function test_status_hilang_wajib_alasan_dan_tercatat_di_riwayat(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 2);

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Hilang',
            'quantity' => 2,
            'status' => 'HILANG',
        ]])->call('submit')->assertHasErrors('rows.0.reason');

        $this->assertSame(0, $catalog->units()->count());
        $this->assertSame(2, $catalog->fresh()->remainingStockQuantity());

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Hilang',
            'quantity' => 2,
            'status' => 'HILANG',
            'reason' => 'Hilang saat perpindahan ruangan',
        ]])->call('submit')->assertHasNoErrors();

        $units = $catalog->units()->get();

        $this->assertTrue($units->every(fn (AssetUnit $unit) => $unit->status === UnitStatus::HILANG));
        $this->assertSame(2, AssetUnitStatusLog::whereIn('asset_unit_id', $units->pluck('id'))->count());
        $this->assertSame(
            'Hilang saat perpindahan ruangan',
            AssetUnitStatusLog::first()->reason,
        );
    }

    public function test_status_yang_membutuhkan_transaksi_lain_ditolak_saat_distribusi(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 2);

        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Dipinjam',
            'quantity' => 2,
            'status' => 'DIPINJAM',
            'reason' => 'Sudah dipinjam',
        ]])->call('submit')->assertHasErrors('rows.0.status');

        $this->assertSame(0, $catalog->units()->count());
    }

    public function test_perubahan_status_manual_wajib_alasan_dan_menyimpan_riwayat(): void
    {
        $catalog = $this->procurement();
        $this->receive($catalog, 1);
        $this->distribute($catalog, [[
            'room_id' => (string) $this->labRpl->id,
            'brand' => 'Futura',
            'model_type' => 'Manual',
            'quantity' => 1,
        ]])->call('submit')->assertHasNoErrors();

        $unit = $catalog->units()->firstOrFail();

        $this->actingAs($this->wakasek)
            ->patch(route('units.update', $unit), [
                'condition' => 'BAIK',
                'status' => 'HILANG',
            ])
            ->assertSessionHasErrors('status_reason');

        $this->assertSame(UnitStatus::AKTIF, $unit->fresh()->status);

        $this->actingAs($this->wakasek)
            ->patch(route('units.update', $unit), [
                'condition' => 'BAIK',
                'status' => 'HILANG',
                'status_reason' => 'Tidak ditemukan saat stock opname',
            ])
            ->assertSessionHasNoErrors();

        $unit->refresh();

        $this->assertSame(UnitStatus::HILANG, $unit->status);

        $log = AssetUnitStatusLog::where('asset_unit_id', $unit->id)->firstOrFail();
        $this->assertSame(AssetUnitStatusLog::SOURCE_MANUAL, $log->source);
        $this->assertSame('Tidak ditemukan saat stock opname', $log->reason);
    }

    public function test_pengadaan_stok_ke_katalog_tercatat_ditolak_agar_jumlah_tidak_hilang(): void
    {
        $existing = Item::where('name', 'Kursi Umum')->firstOrFail();

        $this->actingAs($this->wakasek)->post(route('items.store'), [
            'procurement_mode' => 'STOK',
            'catalog_id' => $existing->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 5,
            'unit' => 'unit',
        ])->assertSessionHasErrors('catalog_id');

        $this->assertSame(0, ProcurementReceipt::where('item_catalog_id', $existing->id)->count());
    }

    public function test_pengadaan_alokasi_lama_tetap_berjalan_dan_tidak_memakai_stok(): void
    {
        $payload = [
            'name' => 'Kursi Alokasi Lama '.uniqid(),
            'category_id' => $this->category->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 2,
            'unit' => 'unit',
            'allocations' => [['room_id' => $this->labRpl->id, 'quantity' => 2]],
        ];

        $this->actingAs($this->wakasek)->post(route('items.store'), $payload)->assertSessionHasNoErrors();

        $catalog = Item::where('name', $payload['name'])->firstOrFail();

        $this->assertSame(1, $catalog->allocations()->count());
        $this->assertSame(2, $catalog->allocations()->sum('quantity'));
        $this->assertSame(2, $catalog->units()->count());
        $this->assertSame(0, ProcurementReceipt::where('item_catalog_id', $catalog->id)->count());
        $this->assertSame(0, $catalog->remainingStockQuantity());
    }
}
