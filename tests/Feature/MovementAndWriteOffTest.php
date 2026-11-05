<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\AssetUnitMovement;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use App\Services\AssetUnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovementAndWriteOffTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private User $editor;

    private Item $item;

    private Room $room1;

    private Room $room2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->admin = $this->wakasek;
        $this->editor = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();

        $rooms = Room::all();
        $this->room1 = $rooms[0];
        $this->room2 = $rooms[1];

        $category = Category::firstOrFail();

        $this->item = Item::create([
            'internal_id' => 'MOV-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '6601',
            'name' => 'Server Rack Unit',
            'category_id' => $category->id,
            'room_id' => $this->room1->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    public function test_halaman_pindah_barang_menampilkan_pencarian_dan_filter_lanjutan(): void
    {
        $response = $this->actingAs($this->admin)->get(route('movements.index'));

        $response->assertOk()
            ->assertSee('Filter Unit')
            ->assertSee('Cari kode unit atau nama barang')
            ->assertSee('Filter')
            ->assertSeeInOrder(['id="movement-search"', 'id="movement-filter-panel"'], false)
            ->assertSee('movement-catalog', false)
            ->assertSee('Terapkan filter');
    }

    public function test_daftar_pindah_barang_dapat_dicari_berdasarkan_nama_barang_dan_kode_unit(): void
    {
        $unit = app(AssetUnitService::class)->create($this->item, $this->room1);

        $nameResponse = $this->actingAs($this->admin)
            ->get(route('movements.index', ['search' => 'Server Rack']));

        $nameResponse->assertOk()
            ->assertViewHas('units', function ($units) use ($unit): bool {
                return $units->getCollection()->contains('id', $unit->id);
            });

        $codeResponse = $this->actingAs($this->admin)
            ->get(route('movements.index', ['search' => $unit->inventory_code]));

        $codeResponse->assertOk()
            ->assertViewHas('units', function ($units) use ($unit): bool {
                return $units->getCollection()->contains('id', $unit->id);
            });
    }

    public function test_write_off_form_submits_selected_units_from_responsive_views(): void
    {
        $response = $this->actingAs($this->admin)->get(route('write-offs.index'));

        $response->assertOk()
            ->assertSee('x-for="unitId in selected"', false)
            ->assertSee('type="hidden" name="unit_ids[]" :value="unitId"', false);
    }

    public function test_mutasi_multi_unit_memperbarui_lokasi_kode_dan_riwayat(): void
    {
        $service = app(AssetUnitService::class);
        $first = $service->create($this->item, $this->room1);
        $second = $service->create($this->item, $this->room1);

        $oldCodes = [$first->inventory_code, $second->inventory_code];

        $response = $this->actingAs($this->admin)->post(route('movements.store'), [
            'unit_ids' => [$first->id, $second->id],
            'to_room_id' => $this->room2->id,
            'note' => 'Pemindahan untuk pemeliharaan server',
        ]);

        $response->assertRedirect(route('movements.index'));
        $response->assertSessionHas('success');

        foreach ([$first, $second] as $index => $unit) {
            $unit->refresh();
            $this->assertSame($this->room2->id, $unit->room_id);
            $this->assertNotSame($oldCodes[$index], $unit->inventory_code);
            $this->assertStringStartsWith($this->room2->buildingCode().(int) $this->room2->floor, $unit->inventory_code);

            $this->assertDatabaseHas('asset_unit_movements', [
                'asset_unit_id' => $unit->id,
                'from_room_id' => $this->room1->id,
                'to_room_id' => $this->room2->id,
                'moved_by' => $this->admin->id,
                'old_code' => $oldCodes[$index],
                'new_code' => $unit->inventory_code,
            ]);
        }

        $this->assertDatabaseHas('activity_log', ['log_name' => 'mutasi']);
    }

    public function test_mutasi_ke_ruangan_yang_sama_ditolak(): void
    {
        $unit = app(AssetUnitService::class)->create($this->item, $this->room1);

        $this->actingAs($this->admin)->post(route('movements.store'), [
            'unit_ids' => [$unit->id],
            'to_room_id' => $this->room1->id, // ruangan yang sama
        ])->assertSessionHasErrors('unit_ids');

        $this->assertSame($this->room1->id, $unit->refresh()->room_id);
    }

    public function test_write_off_lengkap_dengan_berita_acara_approval_dan_audit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $ba = UploadedFile::fake()->create('Berita_Acara_Kerusakan.pdf', 200, 'application/pdf');

        // Pengajuan oleh Admin Sarpras
        $this->actingAs($this->admin)->post(route('write-offs.store'), [
            'item_id' => $this->item->id,
            'quantity' => 1,
            'reason' => 'Komponen terbakar korsleting listrik fatal',
            'berita_acara' => $ba,
        ])->assertRedirect(route('write-offs.index'));

        $writeOff = WriteOff::where('item_id', $this->item->id)->firstOrFail();
        // Admin adalah pemegang kewenangan akhir, jadi pengajuannya langsung sah.
        $this->assertSame('APPROVED', $writeOff->status);
        Storage::disk('local')->assertExists($writeOff->berita_acara_path);

        $this->assertSame('APPROVED', $writeOff->refresh()->status);
        $this->assertSame(ItemStatus::DIHAPUS, $this->item->refresh()->status);
        $this->assertSame(0, $this->item->quantity);

        // Data barang tetap tersimpan di database (tidak di-hard-delete)
        $this->assertDatabaseHas('item_catalogs', ['id' => $this->item->id]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'penghapusan',
            'subject_id' => $writeOff->id,
        ]);
    }

    public function test_persetujuan_penghapusan_mengarsipkan_unit_fisik_dan_sinkron_dengan_katalog(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $units = app(AssetUnitService::class);
        foreach (range(1, 3) as $ignored) {
            $units->create($this->item, $this->room1);
        }

        $this->assertSame(3, $this->item->refresh()->quantity);

        $ba = UploadedFile::fake()->create('Berita_Acara.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin)->post(route('write-offs.store'), [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'reason' => 'Rusak berat tidak dapat diperbaiki',
            'berita_acara' => $ba,
        ])->assertRedirect(route('write-offs.index'));

        $writeOff = WriteOff::where('item_id', $this->item->id)->firstOrFail();
        $this->assertSame('APPROVED', $writeOff->status);

        // Unit fisik benar-benar diarsipkan, bukan sekadar angka katalog dikurangi.
        $archived = AssetUnit::where('item_catalog_id', $this->item->id)
            ->where('status', UnitStatus::DIHAPUS->value)
            ->get();

        $this->assertCount(2, $archived);
        $this->assertTrue($archived->every(fn ($unit) => $unit->archived_at !== null));

        $remaining = AssetUnit::where('item_catalog_id', $this->item->id)
            ->where('status', '!=', UnitStatus::DIHAPUS->value)
            ->count();

        $this->assertSame(1, $remaining);
        $this->assertSame(1, $this->item->refresh()->quantity);
        $this->assertSame(ItemStatus::BAIK, $this->item->status);
    }

    public function test_editor_tidak_dapat_mengajukan_penghapusan_inventaris(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $gudang = Room::where('is_lab', false)->firstOrFail();
        $category = Category::firstOrFail();

        $nonLabItem = Item::create([
            'internal_id' => 'WH-G3-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => '6699',
            'name' => 'Meja Gudang Non Lab',
            'category_id' => $category->id,
            'room_id' => $gudang->id,
            'acquisition_year' => 2020,
            'funding_source' => 'BOS',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);

        $ba = UploadedFile::fake()->create('Berita_Acara.pdf', 100, 'application/pdf');

        $this->actingAs($this->editor)->post(route('write-offs.store'), [
            'item_id' => $nonLabItem->id,
            'quantity' => 1,
            'reason' => 'Di luar kewenangan',
            'berita_acara' => $ba,
        ])->assertForbidden();

        $this->assertDatabaseMissing('write_offs', ['item_id' => $nonLabItem->id]);
    }

    public function test_satu_transaksi_mutasi_dapat_memiliki_beberapa_lokasi_tujuan(): void
    {
        $service = app(AssetUnitService::class);
        $first = $service->create($this->item, $this->room1);
        $second = $service->create($this->item, $this->room1);
        $third = $service->create($this->item, $this->room1);

        $room3 = Room::query()
            ->where('is_lab', true)
            ->whereNotIn('id', [$this->room1->id, $this->room2->id])
            ->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('movements.store'), [
            'destinations' => [
                ['room_id' => $this->room2->id, 'unit_ids' => [$first->id, $second->id]],
                ['room_id' => $room3->id, 'unit_ids' => [$third->id]],
            ],
            'note' => 'Satu transaksi dua tujuan',
        ]);

        $response->assertRedirect(route('movements.index'));
        $response->assertSessionHas('success');

        $this->assertSame($this->room2->id, $first->refresh()->room_id);
        $this->assertSame($this->room2->id, $second->refresh()->room_id);
        $this->assertSame($room3->id, $third->refresh()->room_id);

        $movements = AssetUnitMovement::query()
            ->whereIn('asset_unit_id', [$first->id, $second->id, $third->id])
            ->get();

        $this->assertCount(3, $movements);
        $this->assertNotNull($movements->first()->movement_no);
        $this->assertCount(1, $movements->pluck('movement_no')->unique());

        $this->assertDatabaseHas('asset_unit_movements', [
            'asset_unit_id' => $third->id,
            'from_room_id' => $this->room1->id,
            'to_room_id' => $room3->id,
            'movement_no' => $movements->first()->movement_no,
        ]);
    }

    public function test_unit_yang_sama_tidak_boleh_masuk_dua_lokasi_tujuan(): void
    {
        $unit = app(AssetUnitService::class)->create($this->item, $this->room1);

        $room3 = Room::query()
            ->where('is_lab', true)
            ->whereNotIn('id', [$this->room1->id, $this->room2->id])
            ->firstOrFail();

        $this->actingAs($this->admin)->post(route('movements.store'), [
            'destinations' => [
                ['room_id' => $this->room2->id, 'unit_ids' => [$unit->id]],
                ['room_id' => $room3->id, 'unit_ids' => [$unit->id]],
            ],
        ])->assertSessionHasErrors('destinations');

        $this->assertSame($this->room1->id, $unit->refresh()->room_id);
        $this->assertDatabaseMissing('asset_unit_movements', ['asset_unit_id' => $unit->id]);
    }
}
