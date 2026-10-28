<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\UnitStatus;
use App\Livewire\Catalog\CatalogUnits;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\AssetUnitCode as UnitCodeModel;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use App\Services\AssetUnitService;
use App\Services\InventoryBackfillService;
use App\Services\InventoryCodeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InventoryCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $ketuaLaboran;

    private User $laboranTkj;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketuaLaboran = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->laboranTkj = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->category = Category::firstOrFail();
    }

    public function test_ac_01_dan_02_dua_puluh_tiga_kursi_menjadi_satu_katalog_dengan_23_unit(): void
    {
        $room = $this->makeRoom('Z', 9, 9, false, 'Ruang Uji Kursi');
        $catalog = $this->makeCatalog('Kursi Uji', $room);
        $service = app(AssetUnitService::class);

        for ($i = 0; $i < 23; $i++) {
            $service->create($catalog, $room, [], $this->superAdmin);
        }

        $catalog->refresh();

        $this->assertSame(23, $catalog->units()->count());
        $this->assertSame(23, $catalog->quantity);
        $this->assertSame(1, Item::where('name', 'Kursi Uji')->count());
        $this->assertSame(23, $catalog->units()->distinct()->count('inventory_code'));

        $codes = $catalog->units()->orderBy('unit_no')->pluck('inventory_code')->all();
        $type = $catalog->item_type_code;
        $this->assertSame("Z9-09-{$type}-01", $codes[0]);
        $this->assertSame("Z9-09-{$type}-23", $codes[22]);
    }

    public function test_halaman_katalog_dapat_diakses_dan_menghormati_scope(): void
    {
        $this->actingAs($this->superAdmin)->get(route('catalogs.index'))->assertOk()->assertSee('Katalog Barang');
        $this->actingAs($this->laboranTkj)->get(route('catalogs.index'))->assertOk();

        $labRplCatalog = AssetUnit::whereHas('room', fn ($q) => $q->where('code', 'H1-01'))->firstOrFail()->catalog;
        $this->actingAs($this->laboranTkj)->get(route('catalogs.show', $labRplCatalog))->assertNotFound();
    }

    public function test_ac_03_kode_interpretasi_dan_validasi_format(): void
    {
        $service = app(InventoryCodeService::class);
        $room = $this->makeRoom('G', 1, 2, true, 'Lab Format', 'G1-92');

        $this->assertSame('G1-02-012-12', $service->format($room, 12, '012'));
        $this->assertTrue($service->isValidFormat('G1-02-012-12'));
        $this->assertTrue($service->isValidFormat('F1-03-015-01'));
        $this->assertFalse($service->isValidFormat('g1-02-012-12'));
        $this->assertFalse($service->isValidFormat('G1/02/12'));
        $this->assertFalse($service->isValidFormat('G1-2-12'));
        $this->assertFalse($service->isValidFormat('G1-02-12'));

        $catalog = $this->makeCatalog('Kursi Format', $room);
        $unit = app(AssetUnitService::class)->create($catalog, $room, [], $this->superAdmin);

        $expected = 'G1-02-'.$catalog->item_type_code.'-01';
        $this->assertSame($expected, $unit->inventory_code);
        $this->assertSame($unit->id, $service->resolve($expected)?->id);
        $this->assertNull($service->resolve('G1-02-099-99'));
    }

    public function test_ac_04_dan_05_nomor_unik_dan_tidak_bentrok_lintas_jenis_barang(): void
    {
        $room = $this->makeRoom('Y', 8, 8, false, 'Ruang Campur');
        $chairs = $this->makeCatalog('Kursi Campur', $room);
        $tables = $this->makeCatalog('Meja Campur', $room);
        $service = app(AssetUnitService::class);

        $codes = [];
        foreach ([$chairs, $chairs, $tables, $tables, $chairs] as $catalog) {
            $codes[] = $service->create($catalog, $room, [], $this->superAdmin)->inventory_code;
        }

        // Nomor unit bersifat per-ruangan (urut lintas katalog), kode jenis
        // barang membedakan identitas antar katalog.
        $this->assertSame(
            [
                'Y8-08-'.$chairs->item_type_code.'-01',
                'Y8-08-'.$chairs->item_type_code.'-02',
                'Y8-08-'.$tables->item_type_code.'-03',
                'Y8-08-'.$tables->item_type_code.'-04',
                'Y8-08-'.$chairs->item_type_code.'-05',
            ],
            $codes
        );
        $this->assertCount(5, array_unique($codes));

        // Kode yang sama persis dengan unit pertama harus ditolak unique index.
        $this->expectException(QueryException::class);
        AssetUnitCode::create([
            'asset_unit_id' => $chairs->units()->first()->id,
            'inventory_code' => 'Y8-08-'.$chairs->item_type_code.'-01',
            'room_id' => $room->id,
            'unit_no' => 6,
            'issued_at' => now(),
            'reason' => 'PENEMPATAN',
        ]);
    }

    public function test_ac_06_detail_katalog_hanya_menampilkan_unit_kursi(): void
    {
        $room = $this->makeRoom('X', 7, 7, false, 'Ruang Detail');
        $chairs = $this->makeCatalog('Kursi Detail', $room);
        $tables = $this->makeCatalog('Meja Detail', $room);
        $service = app(AssetUnitService::class);

        $chairCode = $service->create($chairs, $room, [], $this->superAdmin)->inventory_code;
        $tableCode = $service->create($tables, $room, [], $this->superAdmin)->inventory_code;

        $this->actingAs($this->superAdmin)
            ->get(route('catalogs.show', $chairs))
            ->assertOk()
            ->assertSee($chairCode)
            ->assertDontSee($tableCode);
    }

    public function test_ac_07_dan_08_pencarian_kode_dan_filter_lokasi(): void
    {
        $room = $this->makeRoom('F', 1, 3, true, 'Lab Pencarian');
        $catalog = $this->makeCatalog('Kursi Cari', $room);
        $service = app(AssetUnitService::class);

        for ($i = 0; $i < 15; $i++) {
            $service->create($catalog, $room, [], $this->superAdmin);
        }

        $type = $catalog->item_type_code;

        Livewire::actingAs($this->superAdmin)
            ->test(CatalogUnits::class, ['catalogId' => $catalog->id])
            ->set('search', "F1-03-{$type}-15")
            ->assertSee("F1-03-{$type}-15")
            ->assertDontSee("F1-03-{$type}-14");

        Livewire::actingAs($this->superAdmin)
            ->test(CatalogUnits::class, ['catalogId' => $catalog->id])
            ->set('search', 'F1-03')
            ->assertSee("F1-03-{$type}-01")
            ->set('building', 'F')
            ->set('floor', '1')
            ->set('room', (string) $room->id)
            ->assertSee("F1-03-{$type}-01");

        Livewire::actingAs($this->superAdmin)
            ->test(CatalogUnits::class, ['catalogId' => $catalog->id])
            ->set('search', 'kursi')
            ->assertSee('Format kode tidak valid');
    }

    public function test_ac_10_scope_katalog_pencarian_dan_url_langsung(): void
    {
        $labTkj = Room::where('code', 'G1-01')->firstOrFail();
        $labRpl = Room::where('code', 'H1-01')->firstOrFail();
        $general = Room::where('code', 'G1-03')->firstOrFail();

        $labUnit = AssetUnit::where('room_id', $labTkj->id)->firstOrFail();
        $otherLabUnit = AssetUnit::where('room_id', $labRpl->id)->firstOrFail();
        $generalUnit = AssetUnit::where('room_id', $general->id)->firstOrFail();

        $this->assertTrue(AssetUnit::visibleTo($this->laboranTkj)->whereKey($labUnit->id)->exists());
        $this->assertFalse(AssetUnit::visibleTo($this->laboranTkj)->whereKey($otherLabUnit->id)->exists());
        $this->assertFalse(AssetUnit::visibleTo($this->laboranTkj)->whereKey($generalUnit->id)->exists());

        $this->assertTrue(AssetUnit::visibleTo($this->ketuaLaboran)->whereKey($otherLabUnit->id)->exists());
        $this->assertFalse(AssetUnit::visibleTo($this->ketuaLaboran)->whereKey($generalUnit->id)->exists());
        $this->assertTrue(AssetUnit::visibleTo($this->superAdmin)->whereKey($generalUnit->id)->exists());

        $this->actingAs($this->laboranTkj)->get(route('units.show', $otherLabUnit))->assertNotFound();
        $this->actingAs($this->ketuaLaboran)->get(route('units.show', $generalUnit))->assertNotFound();
        $this->actingAs($this->superAdmin)->get(route('units.show', $generalUnit))->assertOk();
    }

    public function test_ac_11_mutasi_menerbitkan_kode_baru_dan_menyimpan_kode_lama(): void
    {
        $from = $this->makeRoom('W', 6, 6, true, 'Lab Asal');
        $to = $this->makeRoom('V', 5, 5, true, 'Lab Tujuan');
        $catalog = $this->makeCatalog('Proyektor Pindah', $from);
        $service = app(AssetUnitService::class);

        $unit = $service->create($catalog, $from, [], $this->superAdmin);
        $oldCode = $unit->inventory_code;

        $moved = app(InventoryCodeService::class)->move($unit, $to, $this->superAdmin, 'Uji mutasi');

        $expectedNew = 'V5-05-'.$catalog->item_type_code.'-01';
        $this->assertSame($expectedNew, $moved->inventory_code);
        $this->assertNotSame($oldCode, $moved->inventory_code);
        $this->assertNull($moved->last_label_printed_at);
        $this->assertDatabaseHas('asset_unit_codes', ['inventory_code' => $oldCode, 'asset_unit_id' => $unit->id]);
        $this->assertNotNull(AssetUnitCode::where('inventory_code', $oldCode)->firstOrFail()->revoked_at);
        $this->assertDatabaseHas('asset_unit_movements', [
            'asset_unit_id' => $unit->id,
            'old_code' => $oldCode,
            'new_code' => $expectedNew,
        ]);
        $this->assertSame($unit->id, app(InventoryCodeService::class)->resolve($oldCode)?->id);
    }

    public function test_route_mutasi_unit_menerbitkan_kode_baru(): void
    {
        $from = Room::where('code', 'G1-01')->firstOrFail();
        $to = Room::where('code', 'H1-01')->firstOrFail();
        $unit = AssetUnit::where('room_id', $from->id)->firstOrFail();
        $oldCode = $unit->inventory_code;

        $this->actingAs($this->superAdmin)
            ->post(route('units.move', $unit), ['room_id' => $to->id, 'note' => 'Uji route mutasi'])
            ->assertRedirect(route('units.show', $unit->fresh()))
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertSame($to->id, $unit->room_id);
        $this->assertStringStartsWith('H1-01-', $unit->inventory_code);
        $this->assertNotSame($oldCode, $unit->inventory_code);
    }

    public function test_scope_laboratorium_dapat_ditetapkan_lewat_form_pengguna(): void
    {
        $lab = Room::where('code', 'G1-01')->firstOrFail();

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Laboran Baru',
            'email' => 'laboran.baru@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'LABORAN',
            'room_ids' => [$lab->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'laboran.baru@gmail.com')->firstOrFail();
        $this->assertDatabaseHas('user_room_scopes', ['user_id' => $user->id, 'room_id' => $lab->id]);
        $this->assertTrue(AssetUnit::visibleTo($user)->where('room_id', $lab->id)->exists());
        $this->assertFalse(AssetUnit::visibleTo($user)->where('room_id', '!=', $lab->id)->exists());

        // Perubahan cakupan akses wajib tercatat di audit log.
        $otherLab = Room::where('code', 'G2-02')->firstOrFail();

        $this->actingAs($this->superAdmin)->put(route('users.update', $user), [
            'name' => 'Laboran Baru',
            'email' => 'laboran.baru@gmail.com',
            'role' => 'LABORAN',
            'room_ids' => [$otherLab->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $activity = Activity::query()
            ->where('description', 'like', 'Mengubah pengguna Laboran Baru%')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame([$lab->id], $activity->properties['akses_ruangan_sebelum']);
        $this->assertSame([$otherLab->id], $activity->properties['akses_ruangan_sesudah']);
    }

    public function test_label_unit_menghasilkan_pdf_dan_mencatat_waktu_cetak(): void
    {
        $unit = AssetUnit::whereHas('room', fn ($q) => $q->where('code', 'G1-01'))->firstOrFail();

        $response = $this->actingAs($this->superAdmin)->get(route('units.label', $unit));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotNull($unit->refresh()->last_label_printed_at);
    }

    public function test_backfill_memecah_baris_batch_menjadi_unit_dengan_kondisi_terpetakan(): void
    {
        $room = $this->makeRoom('T', 3, 3, false, 'Ruang Backfill');
        $catalog = $this->makeCatalog('Barang Backfill', $room);
        $catalog->update(['quantity' => 4, 'qty_damaged' => 1, 'qty_lost' => 1]);

        $created = app(InventoryBackfillService::class)->backfillUnits();

        $this->assertSame(4, $created);
        $this->assertSame(4, $catalog->units()->count());
        $this->assertSame(1, $catalog->units()->where('status', 'HILANG')->count());
        $this->assertSame(1, $catalog->units()->where('condition', 'RUSAK_BERAT')->count());
        $this->assertSame(2, $catalog->units()->where('status', 'AKTIF')->where('condition', 'BAIK')->count());
        $this->assertSame(4, UnitCodeModel::where('room_id', $room->id)->count());
        $this->assertSame('T3-03-'.$catalog->item_type_code.'-01', $catalog->units()->orderBy('unit_no')->first()->inventory_code);
    }

    public function test_kapasitas_99_dan_kode_tidak_dapat_dipakai_ulang(): void
    {
        $room = $this->makeRoom('U', 4, 4, false, 'Ruang Penuh');
        $catalog = $this->makeCatalog('Barang Penuh', $room);
        $unit = app(AssetUnitService::class)->create($catalog, $room, [], $this->superAdmin);

        AssetUnitCode::create([
            'asset_unit_id' => $unit->id,
            'inventory_code' => 'U4-04-99',
            'room_id' => $room->id,
            'unit_no' => 99,
            'issued_at' => now(),
            'reason' => 'PENEMPATAN',
        ]);

        $this->expectException(ValidationException::class);
        app(AssetUnitService::class)->create($catalog, $room, [], $this->superAdmin);
    }

    public function test_perintah_verify_codes_lulus_pada_data_seeder(): void
    {
        $this->artisan('inventory:verify-codes')
            ->expectsOutputToContain('konsisten')
            ->assertSuccessful();
    }

    public function test_laboran_dapat_mengubah_kondisi_unit_dalam_cakupan_dan_tercatat_audit(): void
    {
        $unit = AssetUnit::whereHas('room', fn ($q) => $q->where('code', 'G1-01'))->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->patch(route('units.update', $unit), [
                'condition' => Condition::RUSAK_RINGAN->value,
                'notes' => 'Lensa lecet',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertSame(Condition::RUSAK_RINGAN, $unit->condition);
        $this->assertSame('Lensa lecet', $unit->notes);
        $this->assertSame(UnitStatus::AKTIF, $unit->status);

        $activity = Activity::query()
            ->where('subject_type', AssetUnit::class)
            ->where('subject_id', $unit->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Memperbarui kondisi unit '.$unit->inventory_code, $activity->description);
    }

    public function test_laboran_tidak_dapat_mengubah_unit_di_luar_cakupan(): void
    {
        $unit = AssetUnit::whereHas('room', fn ($q) => $q->where('code', 'H2-02'))->firstOrFail();

        $this->actingAs($this->laboranTkj)
            ->patch(route('units.update', $unit), ['condition' => Condition::RUSAK_BERAT->value])
            ->assertNotFound();

        $this->assertNotSame(Condition::RUSAK_BERAT, $unit->refresh()->condition);
    }

    public function test_status_unit_hanya_dapat_diubah_pengelola(): void
    {
        $unit = AssetUnit::whereHas('room', fn ($q) => $q->where('code', 'G1-01'))->firstOrFail();

        // Laboran hanya boleh mengubah kondisi; status yang dikirim diabaikan.
        $this->actingAs($this->laboranTkj)
            ->patch(route('units.update', $unit), [
                'condition' => Condition::BAIK->value,
                'status' => UnitStatus::HILANG->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UnitStatus::AKTIF, $unit->refresh()->status);

        $this->actingAs($this->superAdmin)
            ->patch(route('units.update', $unit), [
                'condition' => Condition::BAIK->value,
                'status' => UnitStatus::HILANG->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UnitStatus::HILANG, $unit->refresh()->status);
    }

    public function test_perintah_verify_codes_mendeteksi_ledger_tidak_sinkron(): void
    {
        $unit = AssetUnit::query()->firstOrFail();

        AssetUnitCode::query()
            ->where('asset_unit_id', $unit->id)
            ->whereNull('revoked_at')
            ->update(['inventory_code' => 'Z9-99-99']);

        $this->artisan('inventory:verify-codes')
            ->expectsOutputToContain('masalah integritas')
            ->assertFailed();
    }

    private function makeRoom(string $letter, int $floor, int $roomNo, bool $isLab, string $name, ?string $code = null): Room
    {
        $building = Building::firstOrCreate(
            ['code' => $letter],
            ['name' => 'Gedung '.$letter]
        );

        return Room::create([
            'major_id' => null,
            'building_id' => $building->id,
            'building_letter' => $letter,
            'floor' => $floor,
            'room_no' => $roomNo,
            'is_lab' => $isLab,
            'code' => $code ?? $letter.$floor.'-'.str_pad((string) $roomNo, 2, '0', STR_PAD_LEFT),
            'name' => $name,
        ]);
    }

    private function makeCatalog(string $name, Room $room): Item
    {
        return Item::create([
            'internal_id' => 'T-'.Str::lower(Str::random(10)),
            'location_code' => InstitutionSetting::current()->location_code,
            'register_no' => (string) random_int(1000, 9999),
            'name' => $name,
            'category_id' => $this->category->id,
            'room_id' => $room->id,
            'acquisition_year' => now()->year,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);
    }
}
