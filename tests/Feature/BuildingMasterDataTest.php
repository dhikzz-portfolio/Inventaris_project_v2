<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Building;
use App\Models\Category;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\InventoryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master data lokasi: gedung → lantai → nomor ruangan → unit inventaris.
 *
 * Identitas gedung menjadi sumber kebenaran generator kode lokasi
 * (`C1-03`) dan kode inventaris (`C1-03-21`).
 */
class BuildingMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('username', 'wakasek')->firstOrFail();
        $this->admin = User::where('username', 'ketua.laboran')->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    public function test_gedung_dapat_dibuat_dan_dikelola_terpisah(): void
    {
        $this->actingAs($this->admin)
            ->post(route('masters.store-building'), [
                'name' => 'Gedung C',
                'code' => 'c',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $building = Building::where('code', 'C')->firstOrFail();

        $this->assertSame('Gedung C', $building->name);
        $this->assertTrue($building->is_active);
        $this->assertSame(0, $building->rooms()->count());

        // Halaman master menampilkan gedung sebagai entitas tersendiri.
        $this->actingAs($this->admin)
            ->get(route('masters.index'))
            ->assertOk()
            ->assertSee('Gedung C')
            ->assertSee('Master Gedung');
    }

    public function test_kode_gedung_tidak_boleh_bertabrakan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('masters.store-building'), [
                'name' => 'Gedung C',
                'code' => 'C',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('masters.store-building'), [
                'name' => 'Gedung C Bisa',
                'code' => 'C',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_dropdown_gedung_mengambil_data_dari_master_gedung(): void
    {
        $this->actingAs($this->admin)
            ->post(route('masters.store-building'), [
                'name' => 'Gedung C',
                'code' => 'C',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('masters.index'))
            ->assertOk()
            ->assertSee('Gedung C (C)');
    }

    public function test_formulir_ruangan_hanya_meminta_gedung_lantai_dan_nomor_ruangan(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C']);

        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'building_id' => $building->id,
                'floor' => 1,
                'room_no' => 3,
                'name' => 'Lab IPA',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $room = Room::where('building_id', $building->id)->firstOrFail();

        $this->assertSame('C1-03', $room->code);
        $this->assertSame('C', $room->building_letter);
        $this->assertSame(1, $room->floor);
        $this->assertSame(3, $room->room_no);
        $this->assertSame('Gedung C', $room->building->name);
    }

    public function test_ruangan_wajib_memilih_gedung_dan_kombinasi_tidak_boleh_terduplikasi(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C']);

        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'floor' => 1,
                'room_no' => 3,
            ])
            ->assertSessionHasErrors('building_id');

        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'building_id' => $building->id,
                'floor' => 1,
                'room_no' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Kombinasi gedung + lantai + nomor ruangan yang sama tidak boleh diulang.
        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'building_id' => $building->id,
                'floor' => 1,
                'room_no' => 3,
            ])
            ->assertSessionHasErrors('room_no');
    }

    public function test_gedung_nonaktif_tidak_dapat_dipakai_untuk_lokasi_baru(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'building_id' => $building->id,
                'floor' => 1,
                'room_no' => 3,
            ])
            ->assertSessionHasErrors('building_id');
    }

    public function test_kode_lokasi_dan_kode_unit_dibentuk_otomatis_dari_master_gedung(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C']);

        $this->actingAs($this->admin)
            ->post(route('masters.store-room'), [
                'building_id' => $building->id,
                'floor' => 1,
                'room_no' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $room = Room::where('building_id', $building->id)->firstOrFail();

        $this->assertSame('C1-03', $room->locationCode());
        $this->assertSame('C1', $room->codePrefix());

        // Pengadaan oleh Wakasek menerbitkan unit fisik dengan kode otomatis.
        $this->actingAs($this->wakasek)
            ->post(route('items.store'), [
                'name' => 'Mikroskop',
                'category_id' => $this->category->id,
                'room_id' => $room->id,
                'acquisition_year' => 2024,
                'funding_source' => 'BOS',
                'quantity' => 2,
                'unit' => 'unit',
                'location_code' => '12.24.13.08.01.07.01.01',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $codes = AssetUnit::where('room_id', $room->id)->orderBy('inventory_code')->pluck('inventory_code')->all();

        $this->assertSame(['C1-03-01', 'C1-03-02'], $codes);
    }

    public function test_kode_unit_inventaris_tetap_unik(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C']);
        $room = Room::create([
            'building_id' => $building->id,
            'building_letter' => 'C',
            'floor' => 1,
            'room_no' => 3,
            'code' => 'C1-03',
            'name' => 'Lab IPA',
        ]);

        $catalog = Item::create([
            'internal_id' => 'GEDUNG-C-01',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.15.02.01',
            'register_no' => '7001',
            'name' => 'Mikroskop',
            'category_id' => $this->category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);

        $service = app(AssetUnitService::class);
        $codes = [];

        for ($i = 0; $i < 3; $i++) {
            $codes[] = $service->create($catalog, $room, [], $this->wakasek)->inventory_code;
        }

        $this->assertSame(['C1-03-01', 'C1-03-02', 'C1-03-03'], $codes);
        $this->assertCount(3, array_unique($codes));

        $this->assertSame(
            3,
            AssetUnitCode::where('room_id', $room->id)->whereNull('revoked_at')->count()
        );
    }

    public function test_ruangan_dan_unit_lama_tetap_terhubung_setelah_migrasi(): void
    {
        // Data seeder: setiap ruangan harus tertaut ke master gedung.
        $this->assertSame(0, Room::whereNull('building_id')->count());
        $this->assertSame(1, Building::where('code', 'G')->count());

        $room = Room::where('code', 'G1-01')->firstOrFail();

        $this->assertNotNull($room->building);
        $this->assertSame('G', $room->building->code);
        $this->assertSame('G1-01', $room->locationCode());

        // Kode inventaris lama tidak berubah: prefix tetap mengikuti master gedung.
        $unit = AssetUnit::where('room_id', $room->id)->orderBy('inventory_code')->firstOrFail();
        $this->assertStringStartsWith('G1-01-', $unit->inventory_code);

        // Riwayat penugasan/lokasi tetap tertaut ke ruangan yang benar.
        $this->assertSame($room->id, $unit->room_id);
        $this->assertDatabaseHas('asset_unit_codes', [
            'asset_unit_id' => $unit->id,
            'room_id' => $room->id,
        ]);
    }

    public function test_perubahan_lokasi_tidak_merusak_riwayat(): void
    {
        $roomAsal = Room::where('code', 'G1-01')->firstOrFail();
        $roomTujuan = Room::where('code', 'H1-01')->firstOrFail();

        $unit = AssetUnit::where('room_id', $roomAsal->id)->orderBy('inventory_code')->firstOrFail();
        $oldCode = $unit->inventory_code;

        $moved = app(InventoryCodeService::class)->move($unit, $roomTujuan, $this->wakasek, 'Uji mutasi');

        $this->assertSame($roomTujuan->id, $moved->room_id);
        $this->assertStringStartsWith('H1-01-', $moved->inventory_code);
        $this->assertNotSame($oldCode, $moved->inventory_code);

        // Kode lama tetap dapat ditelusuri, ditandai dicabut, dan riwayatnya tersimpan.
        $this->assertDatabaseHas('asset_unit_codes', [
            'asset_unit_id' => $unit->id,
            'inventory_code' => $oldCode,
        ]);
        $this->assertNotNull(AssetUnitCode::where('inventory_code', $oldCode)->firstOrFail()->revoked_at);
        $this->assertDatabaseHas('asset_unit_movements', [
            'asset_unit_id' => $unit->id,
            'from_room_id' => $roomAsal->id,
            'to_room_id' => $roomTujuan->id,
            'old_code' => $oldCode,
        ]);
    }

    public function test_kode_gedung_tidak_dapat_diubah_saat_sudah_ada_unit_inventaris(): void
    {
        $building = Building::create(['name' => 'Gedung C', 'code' => 'C']);
        $room = Room::create([
            'building_id' => $building->id,
            'building_letter' => 'C',
            'floor' => 1,
            'room_no' => 3,
            'code' => 'C1-03',
            'name' => 'Lab IPA',
        ]);

        $catalog = Item::create([
            'internal_id' => 'GEDUNG-C-02',
            'location_code' => '12.24.13.08.01.07.01.01',
            'asset_code' => '01.01.15.02.01',
            'register_no' => '7002',
            'name' => 'Pipet',
            'category_id' => $this->category->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);

        app(AssetUnitService::class)->create($catalog, $room, [], $this->wakasek);

        $this->actingAs($this->admin)
            ->put(route('masters.update-building', $building), [
                'name' => 'Gedung C',
                'code' => 'D',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame('C', $building->refresh()->code);
    }

    public function test_ruangan_yang_dipakai_unit_tidak_dapat_dihapus(): void
    {
        $room = Room::whereHas('units')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-room', $room))
            ->assertStatus(422);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_gedung_yang_memiliki_ruangan_tidak_dapat_dihapus(): void
    {
        $building = Building::whereHas('rooms')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-building', $building))
            ->assertStatus(422);

        $this->assertDatabaseHas('buildings', ['id' => $building->id]);
    }
}
