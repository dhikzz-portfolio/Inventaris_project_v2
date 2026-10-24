<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Models\WriteOff;
use App\Services\AssetUnitService;
use App\Services\IdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovementAndWriteOffTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private Item $item;

    private Room $room1;

    private Room $room2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->admin = User::where('username', 'ketua.laboran')->firstOrFail();

        $rooms = Room::all();
        $this->room1 = $rooms[0];
        $this->room2 = $rooms[1];

        $category = Category::firstOrFail();

        $this->item = Item::create([
            'internal_id' => 'MOV-G1-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.11.04.01',
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

    public function test_mutasi_lokasi_memperbarui_ruangan_dan_id_internal_dalam_transaksi(): void
    {
        $oldInternalId = $this->item->internal_id;

        $response = $this->actingAs($this->admin)->post(route('movements.store'), [
            'item_id' => $this->item->id,
            'to_room_id' => $this->room2->id,
            'note' => 'Pemindahan untuk pemeliharaan server',
        ]);

        $response->assertRedirect(route('movements.index'));
        $response->assertSessionHas('success');

        $this->item->refresh();
        $this->assertSame($this->room2->id, $this->item->room_id);
        $this->assertNotSame($oldInternalId, $this->item->internal_id);
        $this->assertStringStartsWith(IdGenerator::roomPrefix($this->room2), $this->item->internal_id);

        $this->assertDatabaseHas('item_movements', [
            'item_id' => $this->item->id,
            'from_room_id' => $this->room1->id,
            'to_room_id' => $this->room2->id,
            'moved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'mutasi',
            'subject_id' => $this->item->id,
        ]);
    }

    public function test_mutasi_ke_ruangan_yang_sama_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('movements.store'), [
            'item_id' => $this->item->id,
            'to_room_id' => $this->room1->id, // ruangan yang sama
        ])->assertStatus(422);
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
        $this->assertSame('PENDING', $writeOff->status);
        Storage::disk('local')->assertExists($writeOff->berita_acara_path);

        // Approval oleh Wakasek
        $this->actingAs($this->wakasek)->post(route('write-offs.approve', $writeOff))
            ->assertRedirect();

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

        $this->actingAs($this->wakasek)->post(route('write-offs.approve', $writeOff))
            ->assertRedirect();

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

    public function test_ketua_laboran_tidak_dapat_mengajukan_penghapusan_barang_di_luar_laboratorium(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $gudang = Room::where('is_lab', false)->firstOrFail();
        $category = Category::firstOrFail();

        $nonLabItem = Item::create([
            'internal_id' => 'WH-G3-01-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => $category->code,
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

        $this->actingAs($this->admin)->post(route('write-offs.store'), [
            'item_id' => $nonLabItem->id,
            'quantity' => 1,
            'reason' => 'Di luar kewenangan',
            'berita_acara' => $ba,
        ])->assertNotFound();

        $this->assertDatabaseMissing('write_offs', ['item_id' => $nonLabItem->id]);
    }
}
