<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\InstitutionSetting;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $admin;

    private User $laboran;

    private User $peminjam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->admin = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->laboran = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->peminjam = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
    }

    public function test_peminjam_dan_laboran_tidak_bisa_akses_master_data(): void
    {
        $this->actingAs($this->peminjam)->get(route('masters.index'))->assertForbidden();
        $this->actingAs($this->laboran)->get(route('masters.index'))->assertForbidden();
    }

    public function test_admin_sarpras_dan_wakasek_dapat_mengakses_dan_menambah_master_data(): void
    {
        $this->actingAs($this->admin)->get(route('masters.index'))->assertOk();
        $this->actingAs($this->wakasek)->get(route('masters.index'))->assertOk();

        // Tambah Jurusan
        $this->actingAs($this->admin)->post(route('masters.store-major'), [
            'name' => 'Akuntansi dan Keuangan Lembaga',
            'code' => 'AKL',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('majors', ['name' => 'Akuntansi dan Keuangan Lembaga', 'code' => 'AKL']);

        $major = Major::where('code', 'AKL')->firstOrFail();

        // Tambah Ruangan (formulir sederhana: gedung dari master + lantai + nomor ruang)
        $building = Building::where('code', 'I')->firstOrFail();

        $this->actingAs($this->admin)->post(route('masters.store-room'), [
            'major_id' => $major->id,
            'building_id' => $building->id,
            'floor' => 2,
            'room_no' => 2,
            'name' => 'Lab Komputer Akuntansi',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rooms', [
            'code' => 'I2-02',
            'name' => 'Lab Komputer Akuntansi',
            'building_id' => $building->id,
        ]);

        // Tambah Kategori
        $this->actingAs($this->admin)->post(route('masters.store-category'), [
            'name' => 'Perangkat Jaringan Nirkabel',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['name' => 'Perangkat Jaringan Nirkabel']);
        // Kode klasifikasi aset lama sudah dihapus dari master kategori.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('categories', 'code'));
    }

    public function test_hanya_wakasek_yang_dapat_mengubah_pengaturan_instansi(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($this->laboran)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($this->peminjam)->get(route('settings.edit'))->assertForbidden();

        $logo = UploadedFile::fake()->image('logo_sekolah.png');

        $this->actingAs($this->wakasek)->post(route('settings.update'), [
            'institution_name' => 'SMK BAGIMU NEGERIKU DIGITAL',
            'location_code' => '12.24.13.08.01.07.01.99',
            'address' => 'Semarang Jawa Tengah',
            'headmaster_name' => 'Drs. Daniel Kepala Sekolah',
            'logo' => $logo,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = InstitutionSetting::current();
        $this->assertSame('SMK BAGIMU NEGERIKU DIGITAL', $setting->institution_name);
        $this->assertSame('12.24.13.08.01.07.01.99', $setting->location_code);
        $this->assertNotNull($setting->logo_path);
        Storage::disk('public')->assertExists($setting->logo_path);
    }

    public function test_hanya_wakasek_yang_dapat_mengelola_pengguna(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->laboran)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->peminjam)->get(route('users.index'))->assertForbidden();

        $this->actingAs($this->wakasek)->get(route('users.index'))->assertOk();

        // Buat user baru
        $this->actingAs($this->wakasek)->post(route('users.store'), [
            'name' => 'Laboran Baru',
            'email' => 'laboran.baru@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'LABORAN',
            'major_id' => Major::first()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $newUser = User::where('email', 'laboran.baru@gmail.com')->firstOrFail();
        $this->assertTrue($newUser->hasRole('LABORAN'));

        // Update user
        $this->actingAs($this->wakasek)->put(route('users.update', $newUser), [
            'name' => 'Laboran Baru Updated',
            'email' => 'laboran.baru@gmail.com',
            'role' => 'PEMINJAM',
            'major_id' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('PEMINJAM', $newUser->refresh()->role);
        $this->assertTrue($newUser->hasRole('PEMINJAM'));

        // Hapus user
        $this->actingAs($this->wakasek)->delete(route('users.destroy', $newUser))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $newUser->id]);
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $this->actingAs($this->wakasek)
            ->delete(route('users.destroy', $this->wakasek))
            ->assertStatus(422);
    }
}
