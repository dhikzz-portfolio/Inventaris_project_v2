<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\AssetUnit;
use App\Models\AssetUnitCode;
use App\Models\Building;
use App\Models\Category;
use App\Models\DamageReport;
use App\Models\Item;
use App\Models\Major;
use App\Models\ProcurementReceipt;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penghapusan harus selalu memberi jawaban yang jelas: berhasil, atau ditolak
 * dengan pesan yang bisa ditindaklanjuti. Kesalahan foreign key tidak boleh
 * muncul sebagai halaman 500.
 */
class DeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_jurusan_tanpa_ruangan_dan_pengguna_dapat_dihapus(): void
    {
        $major = Major::create(['name' => 'Jurusan Uji Hapus', 'code' => 'UJI']);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-major', $major))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($major);
    }

    public function test_kategori_tanpa_barang_dapat_dihapus(): void
    {
        $category = Category::create(['name' => 'Kategori Uji Hapus']);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-category', $category))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($category);
    }

    public function test_gedung_tanpa_ruangan_dapat_dihapus(): void
    {
        $building = Building::create(['name' => 'Gedung Uji Hapus', 'code' => 'Y', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-building', $building))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($building);
    }

    public function test_ruangan_kosong_dapat_dihapus(): void
    {
        $room = $this->makeEmptyRoom(94);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-room', $room))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($room);
    }

    public function test_ruangan_dengan_penugasan_laboran_tidak_dapat_dihapus(): void
    {
        $room = $this->makeEmptyRoom(91);

        UserRoomScope::create([
            'user_id' => User::where('email', 'laboran.tp@gmail.com')->firstOrFail()->id,
            'room_id' => $room->id,
            'assigned_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-room', $room))
            ->assertStatus(422);

        $this->assertModelExists($room);
    }

    public function test_ruangan_dengan_riwayat_kode_unit_tidak_dapat_dihapus(): void
    {
        $room = $this->makeEmptyRoom(92);
        $unit = AssetUnit::query()->firstOrFail();

        AssetUnitCode::create([
            'asset_unit_id' => $unit->id,
            'inventory_code' => 'ZZ-9092',
            'room_id' => $room->id,
            'unit_no' => 90,
            'issued_at' => now()->subYear(),
            'revoked_at' => now()->subMonth(),
            'reason' => 'MUTASI',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('masters.destroy-room', $room))
            ->assertStatus(422);

        $this->assertModelExists($room);
    }

    public function test_pengguna_dengan_penugasan_ruangan_dapat_dihapus_dan_penugasannya_dilepas(): void
    {
        $operator = $this->makeStaffUser('operator.hapus@gmail.com');
        $room = $this->makeEmptyRoom(93);

        UserRoomScope::create([
            'user_id' => $operator->id,
            'room_id' => $room->id,
            'assigned_by' => $this->admin->id,
        ]);

        $this->actingAs($this->accountAdministrator())
            ->delete(route('users.destroy', $operator))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $operator->id]);
        $this->assertDatabaseMissing('user_room_scopes', ['user_id' => $operator->id]);
    }

    public function test_pengguna_dengan_laporan_kerusakan_tidak_dapat_dihapus(): void
    {
        $reporter = $this->makeStaffUser('pelapor.rusak@gmail.com');
        $unit = AssetUnit::query()->firstOrFail();

        DamageReport::create([
            'asset_unit_id' => $unit->id,
            'reported_by' => $reporter->id,
            'classification' => 'RUSAK_RINGAN',
            'previous_condition' => 'BAIK',
            'reported_condition' => 'RUSAK_RINGAN',
            'description' => 'Lampu indikator mati.',
            'status' => 'DILAPORKAN',
        ]);

        $this->actingAs($this->accountAdministrator())
            ->delete(route('users.destroy', $reporter))
            ->assertStatus(422);

        $this->assertModelExists($reporter);
        $this->assertDatabaseHas('damage_reports', ['reported_by' => $reporter->id]);
    }

    public function test_pengguna_pembuat_arsip_laporan_tidak_dapat_dihapus(): void
    {
        $generator = $this->makeStaffUser('pembuat.laporan@gmail.com');

        Report::create([
            'period' => '2026-10',
            'scope' => 'SEMUA',
            'generated_by' => $generator->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($this->accountAdministrator())
            ->delete(route('users.destroy', $generator))
            ->assertStatus(422);

        $this->assertModelExists($generator);
        $this->assertDatabaseHas('reports', ['generated_by' => $generator->id]);
    }

    public function test_katalog_dengan_penerimaan_stok_dinonaktifkan_bukan_dihapus(): void
    {
        $catalog = $this->makeCatalogWithoutUnits('Barang Stok Belum Didistribusikan');

        ProcurementReceipt::create([
            'item_catalog_id' => $catalog->id,
            'quantity' => 3,
            'condition' => 'BAIK',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('catalogs.destroy', $catalog))
            ->assertRedirect(route('catalogs.index'))
            ->assertSessionHas('success');

        $this->assertModelExists($catalog);
        $this->assertNotNull($catalog->refresh()->archived_at);
        $this->assertDatabaseHas('procurement_receipts', ['item_catalog_id' => $catalog->id]);
    }

    private function makeEmptyRoom(int $roomNo): Room
    {
        $building = Building::query()->orderBy('code')->firstOrFail();

        return Room::create([
            'building_id' => $building->id,
            'building_letter' => $building->code,
            'floor' => 9,
            'room_no' => $roomNo,
            'code' => sprintf('%s9-%02d', $building->code, $roomNo),
            'name' => 'Ruang Uji Hapus '.$roomNo,
            'is_lab' => true,
        ]);
    }

    private function makeStaffUser(string $email): User
    {
        return User::create([
            'name' => 'Akun Uji Hapus',
            'email' => $email,
            'password' => 'password123',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::STAF->value,
            'work_unit' => 'Tata Usaha',
        ]);
    }

    private function makeCatalogWithoutUnits(string $name): Item
    {
        $room = Room::query()->firstOrFail();

        return Item::create([
            'internal_id' => 'DEL-'.strtoupper(substr(md5($name), 0, 6)),
            'location_code' => '12.24.13.08.01.07.01.01',
            'register_no' => (string) random_int(1000, 9999),
            'name' => $name,
            'category_id' => Category::query()->firstOrFail()->id,
            'room_id' => $room->id,
            'acquisition_year' => 2026,
            'funding_source' => 'BOS',
            'quantity' => 0,
            'unit' => 'unit',
            'status' => 'BAIK',
            'acquisition_status' => 'APPROVED',
        ]);
    }
}
