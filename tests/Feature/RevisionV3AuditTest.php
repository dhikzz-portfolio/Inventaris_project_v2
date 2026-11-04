<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Condition;
use App\Enums\ItemStatus;
use App\Enums\Role;
use App\Enums\UnitStatus;
use App\Models\AcquisitionAllocation;
use App\Models\AssetUnit;
use App\Models\Building;
use App\Models\Category;
use App\Models\Event;
use App\Models\Item;
use App\Models\Room;
use App\Models\Specification;
use App\Models\User;
use App\Models\WriteOff;
use App\Services\AssetUnitService;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevisionV3AuditTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $ketua;

    private User $guru;

    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->ketua = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->guru = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
        $this->siswa = User::where('email', 'siswa.raka@gmail.com')->firstOrFail();
    }

    private function makeRoom(int $roomNo): Room
    {
        $building = Building::query()->firstOrFail();

        return Room::create([
            'building_id' => $building->id,
            'floor' => 9,
            'room_no' => $roomNo,
            'code' => sprintf('Z9-%02d', $roomNo),
            'name' => 'Ruang Audit '.$roomNo,
            'building_letter' => $building->code,
            'is_lab' => true,
        ]);
    }

    private function makeCatalogRecord(string $name, Room $room, string $brand, string $model): Item
    {
        return Item::create([
            'internal_id' => 'AU'.Str::upper(Str::random(7)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(100000, 999999),
            'name' => $name,
            'brand' => $brand,
            'model_type' => $model,
            'category_id' => Category::query()->firstOrFail()->id,
            'room_id' => $room->id,
            'acquisition_year' => 2024,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => ItemStatus::BAIK,
            'acquisition_status' => 'APPROVED',
        ]);
    }

    /**
     * @return array{0: Item, 1: array<int, AssetUnit>}
     */
    private function makeCatalogWithUnits(string $name, Room $room, int $quantity, string $brand = 'Futura', string $model = 'Tipe A'): array
    {
        $catalog = $this->makeCatalogRecord($name, $room, $brand, $model);
        $units = [];

        for ($i = 0; $i < $quantity; $i++) {
            $units[] = app(AssetUnitService::class)->create($catalog, $room);
        }

        return [$catalog, $units];
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->create('berita-acara-'.Str::random(6).'.pdf', 100, 'application/pdf');
    }

    public function test_backfill_only_maps_units_with_a_single_evidence_path_and_is_idempotent(): void
    {
        $room = $this->makeRoom(80);
        $catalog = $this->makeCatalogRecord('Katalog Ambigu', $room, 'Merek Netral', 'Tipe Netral');
        $specificationA = Specification::resolveFor($catalog, 'Merek A', 'Tipe A');
        $specificationB = Specification::resolveFor($catalog, 'Merek B', 'Tipe B');

        $unit = app(AssetUnitService::class)->create($catalog, $room);
        $unit->forceFill(['specification_id' => null])->save();

        // Merek/tipe katalog tidak lagi cocok dengan kandidat mana pun.
        $catalog->update(['brand' => 'Merek Tanpa Kecocokan', 'model_type' => 'Tipe Tanpa Kecocokan']);

        Artisan::call('inventory:backfill-specifications', ['--apply' => true]);
        $this->assertNull($unit->refresh()->specification_id, 'Dua kandidat tanpa bukti harus dibiarkan tidak dipetakan.');

        AcquisitionAllocation::create([
            'item_catalog_id' => $catalog->id,
            'specification_id' => $specificationB->id,
            'room_id' => $room->id,
            'quantity' => 1,
        ]);

        Artisan::call('inventory:backfill-specifications', ['--apply' => true]);
        $this->assertSame($specificationB->id, $unit->refresh()->specification_id);

        Artisan::call('inventory:backfill-specifications', ['--apply' => true]);
        $this->assertSame($specificationB->id, $unit->refresh()->specification_id);
        $this->assertNotSame($specificationA->id, $unit->specification_id);
    }

    public function test_backfill_maps_a_catalog_with_exactly_one_specification(): void
    {
        $room = $this->makeRoom(81);
        $catalog = $this->makeCatalogRecord('Katalog Tunggal', $room, 'Merek Tunggal', 'Tipe Tunggal');
        $specification = Specification::resolveFor($catalog, 'Merek Tunggal', 'Tipe Tunggal');

        $unit = app(AssetUnitService::class)->create($catalog, $room);
        $unit->forceFill(['specification_id' => null])->save();

        Artisan::call('inventory:backfill-specifications', [
            '--apply' => true,
            '--catalog' => $catalog->id,
        ]);

        $this->assertSame($specification->id, $unit->refresh()->specification_id);
        $this->assertNotEmpty(Storage::disk('local')->files('reports'));
    }

    public function test_unit_write_off_archives_only_selected_units(): void
    {
        Storage::fake('local');
        $room = $this->makeRoom(82);
        [$catalog, $units] = $this->makeCatalogWithUnits('Kursi Unit Uji', $room, 3);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$units[0]->id, $units[1]->id],
            'confirm_delete' => 1,
            'reason' => 'Rusak berat dan tidak dapat diperbaiki',
            'berita_acara' => $this->upload(),
        ])->assertRedirect(route('write-offs.index'))->assertSessionHasNoErrors();

        $writeOff = WriteOff::query()->where('mode', 'UNIT')->latest('id')->firstOrFail();
        $this->assertSame('APPROVED', $writeOff->status);
        $this->assertSame(2, $writeOff->units()->count());
        $this->assertSame(2, $writeOff->quantity);

        $this->assertSame(UnitStatus::DIHAPUS, $units[0]->refresh()->status);
        $this->assertSame(UnitStatus::DIHAPUS, $units[1]->refresh()->status);
        $this->assertSame(UnitStatus::AKTIF, $units[2]->refresh()->status);
        $this->assertSame(1, $catalog->refresh()->quantity);
        $this->assertDatabaseHas('write_off_units', ['write_off_id' => $writeOff->id, 'asset_unit_id' => $units[0]->id]);
    }

    public function test_catalog_write_off_requires_explicit_confirmation_and_keeps_catalog_history(): void
    {
        Storage::fake('local');
        $room = $this->makeRoom(83);
        [$catalog, $units] = $this->makeCatalogWithUnits('Meja Katalog Uji', $room, 2);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'CATALOG',
            'catalog_id' => $catalog->id,
            'confirm_delete' => 1,
            'reason' => 'Seluruh unit tidak layak pakai',
            'berita_acara' => $this->upload(),
        ])->assertSessionHasErrors('confirm_catalog');

        $this->assertSame(0, WriteOff::query()->where('item_id', $catalog->id)->count());

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'CATALOG',
            'catalog_id' => $catalog->id,
            'confirm_delete' => 1,
            'confirm_catalog' => 1,
            'reason' => 'Seluruh unit tidak layak pakai',
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $writeOff = WriteOff::query()->where('item_id', $catalog->id)->latest('id')->firstOrFail();
        $this->assertSame('CATALOG', $writeOff->mode);
        $this->assertSame(2, $writeOff->units()->count());
        $this->assertSame('APPROVED', $writeOff->status);

        $this->assertSame(UnitStatus::DIHAPUS, $units[0]->refresh()->status);
        $this->assertSame(UnitStatus::DIHAPUS, $units[1]->refresh()->status);
        $this->assertDatabaseHas('item_catalogs', ['id' => $catalog->id]);
        $this->assertDatabaseHas('write_offs', ['id' => $writeOff->id, 'status' => 'APPROVED']);
    }

    public function test_catalog_write_off_without_eligible_units_is_rejected(): void
    {
        Storage::fake('local');
        $room = $this->makeRoom(89);
        [$catalog, $units] = $this->makeCatalogWithUnits('Katalog Tidak Layak', $room, 1);
        $units[0]->update(['status' => UnitStatus::DIPINJAM]);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'CATALOG',
            'catalog_id' => $catalog->id,
            'confirm_delete' => 1,
            'confirm_catalog' => 1,
            'reason' => 'Seluruh unit sedang dipinjam',
            'berita_acara' => $this->upload(),
        ])->assertSessionHasErrors('catalog_id');

        $this->assertSame(0, WriteOff::query()->where('item_id', $catalog->id)->count());
    }

    public function test_on_loan_and_already_pending_units_are_rejected_atomically(): void
    {
        Storage::fake('local');
        $room = $this->makeRoom(84);
        [, $units] = $this->makeCatalogWithUnits('Proyektor Uji', $room, 2);

        $units[0]->update(['status' => UnitStatus::DIPINJAM]);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$units[0]->id],
            'confirm_delete' => 1,
            'reason' => 'Tidak layak',
            'berita_acara' => $this->upload(),
        ])->assertSessionHasErrors('unit_ids');

        $this->assertSame(0, WriteOff::query()->count(), 'Transaksi gagal harus rollback tanpa write-off parsial.');

        $units[0]->update(['status' => UnitStatus::AKTIF]);

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$units[0]->id],
            'confirm_delete' => 1,
            'reason' => 'Tidak layak',
            'berita_acara' => $this->upload(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->wakasek)->post(route('write-offs.store'), [
            'mode' => 'UNIT',
            'unit_ids' => [$units[0]->id],
            'confirm_delete' => 1,
            'reason' => 'Pengajuan ganda',
            'berita_acara' => $this->upload(),
        ])->assertSessionHasErrors('unit_ids');

        $this->assertSame(1, WriteOff::query()->count());
        $this->assertSame(1, $units[0]->writeOffUnits()->count());
    }

    public function test_write_off_filters_combine_and_reset_correctly(): void
    {
        $alfaRoom = $this->makeRoom(85);
        $betaRoom = $this->makeRoom(86);

        [$alfaCatalog, $alfaUnits] = $this->makeCatalogWithUnits('Kursi Alfa', $alfaRoom, 1, 'Merek Alfa', 'Tipe Alfa');
        [$betaCatalog, $betaUnits] = $this->makeCatalogWithUnits('Meja Beta', $betaRoom, 1, 'Merek Beta', 'Tipe Beta');

        $betaUnits[0]->update(['condition' => Condition::RUSAK_RINGAN]);

        $this->actingAs($this->wakasek)
            ->get(route('write-offs.index', ['brand' => 'Merek Beta']))
            ->assertOk()
            ->assertSee($betaUnits[0]->inventory_code)
            ->assertDontSee($alfaUnits[0]->inventory_code);

        $this->actingAs($this->wakasek)
            ->get(route('write-offs.index', [
                'brand' => 'Merek Beta',
                'condition' => Condition::RUSAK_RINGAN->value,
                'status' => UnitStatus::AKTIF->value,
                'building' => $betaRoom->buildingCode(),
                'floor' => 9,
            ]))
            ->assertOk()
            ->assertSee($betaUnits[0]->inventory_code)
            ->assertDontSee($alfaUnits[0]->inventory_code);

        $this->actingAs($this->wakasek)
            ->get(route('write-offs.index', ['search' => substr($alfaUnits[0]->inventory_code, 0, 8)]))
            ->assertOk()
            ->assertSee($alfaUnits[0]->inventory_code);

        $this->actingAs($this->wakasek)
            ->get(route('write-offs.index'))
            ->assertOk()
            ->assertSee($alfaUnits[0]->inventory_code)
            ->assertSee($betaUnits[0]->inventory_code);
    }

    public function test_password_change_rejects_same_password_mismatch_and_unauthenticated_requests(): void
    {
        $this->actingAs($this->siswa)->put(route('password.update'), [
            'current_password' => 'siswa123',
            'password' => 'siswa123',
            'password_confirmation' => 'siswa123',
        ])->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->actingAs($this->siswa)->put(route('password.update'), [
            'current_password' => 'siswa123',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'Berbeda123',
        ])->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('siswa123', $this->siswa->refresh()->password));

        auth()->logout();

        $this->put(route('password.update'), [
            'current_password' => 'siswa123',
            'password' => 'KataSandiBaru123',
            'password_confirmation' => 'KataSandiBaru123',
        ])->assertRedirect(route('login'));
    }

    public function test_password_endpoint_is_rate_limited(): void
    {
        Cache::flush();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($this->siswa)->put(route('password.update'), [
                'current_password' => 'salah-'.$attempt,
                'password' => 'KataSandiBaru'.$attempt,
                'password_confirmation' => 'KataSandiBaru'.$attempt,
            ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);
        }

        $this->actingAs($this->siswa)->put(route('password.update'), [
            'current_password' => 'salah-7',
            'password' => 'KataSandiBaru7',
            'password_confirmation' => 'KataSandiBaru7',
        ])->assertStatus(429);
    }

    public function test_super_admin_can_delete_an_event_created_by_someone_else_without_loans(): void
    {
        $event = Event::create([
            'name' => 'Kegiatan Milik Guru',
            'pic_name' => $this->guru->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_by' => $this->guru->id,
        ]);

        $this->actingAs($this->wakasek)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_user_who_created_events_cannot_be_deleted(): void
    {
        $owner = User::create([
            'name' => 'Pembuat Kegiatan',
            'email' => 'pembuat.kegiatan@gmail.com',
            'password' => 'password123',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::STAF->value,
        ]);

        Event::create([
            'name' => 'Kegiatan Relasi',
            'pic_name' => $owner->name,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($this->accountAdministrator())
            ->delete(route('users.destroy', $owner))
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_qr_release_1_and_2_are_compatible_and_unknown_versions_are_rejected(): void
    {
        $room = $this->makeRoom(87);
        [$catalog, $units] = $this->makeCatalogWithUnits('Kursi QR Uji', $room, 1);
        $unit = $units[0];
        $specification = $unit->specification;

        $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id.'&r=1')
            ->assertOk()
            ->assertSee($specification->brandLabel())
            ->assertSee($specification->modelLabel());

        // Payload rilis 2 membawa ID spesifikasi; nilai palsu tidak boleh dipercaya.
        $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id.'&r=2&s=999999')
            ->assertOk()
            ->assertSee($specification->brandLabel())
            ->assertSee($specification->modelLabel());

        $this->actingAs($this->wakasek)
            ->get('/scan?code='.urlencode($unit->inventory_code).'&u='.$unit->id.'&r=99')
            ->assertOk()
            ->assertSee('Versi QR tidak didukung');
    }

    public function test_label_pdf_uses_the_small_media_box_and_qr_is_square(): void
    {
        $room = $this->makeRoom(88);
        [, $units] = $this->makeCatalogWithUnits('Kursi Label Uji', $room, 1);
        $unit = $units[0];

        $pdf = app(LabelService::class)->printUnit($unit)->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+260(?:\.0+)?\s+120(?:\.0+)?\s*\]/',
            $pdf
        );

        $dataUrl = app(LabelService::class)->unitQrDataUrl($unit);
        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')));
        $image = imagecreatefromstring($binary);

        $this->assertNotFalse($image);
        $this->assertSame(imagesx($image), imagesy($image));
        $this->assertGreaterThan(100, imagesx($image));

        $this->actingAs($this->wakasek)
            ->get(route('units.label', $unit))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_report_page_exposes_consistent_action_buttons(): void
    {
        $this->actingAs($this->wakasek)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Pratinjau PDF')
            ->assertSee('Cetak')
            ->assertSee('Excel')
            ->assertSee('Impor Laporan')
            ->assertSee('Simpan ke Arsip');
    }
}
