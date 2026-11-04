<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $editor;

    private User $operator;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@gmail.com')->firstOrFail();
        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->editor = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->operator = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->member = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
    }

    public function test_selain_admin_tidak_bisa_akses_master_data(): void
    {
        $this->actingAs($this->member)->get(route('masters.index'))->assertForbidden();
        $this->actingAs($this->operator)->get(route('masters.index'))->assertForbidden();
        $this->actingAs($this->editor)->get(route('masters.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('masters.index'))->assertForbidden();
    }

    public function test_admin_mengelola_seluruh_master_data(): void
    {
        $this->actingAs($this->admin)->get(route('masters.index'))->assertOk();

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
        $this->assertFalse(Schema::hasColumn('categories', 'code'));

        // Hapus kategori baru
        $category = Category::where('name', 'Perangkat Jaringan Nirkabel')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('masters.destroy-category', $category))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_hanya_super_admin_yang_dapat_mengubah_pengaturan_instansi(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        foreach ([$this->admin, $this->editor, $this->operator, $this->member] as $other) {
            $this->actingAs($other)->get(route('settings.edit'))->assertForbidden();
        }

        $logo = UploadedFile::fake()->image('logo_sekolah.png');

        $this->actingAs($this->superAdmin)->post(route('settings.update'), [
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

    public function test_hanya_super_admin_yang_dapat_mengelola_pengguna(): void
    {
        foreach ([$this->admin, $this->editor, $this->operator, $this->member] as $other) {
            $this->actingAs($other)->get(route('users.index'))->assertForbidden();
        }

        $this->actingAs($this->superAdmin)->get(route('users.index'))->assertOk();

        // Buat user baru (role Operator dengan kategori Laboran).
        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Laboran Baru',
            'email' => 'laboran.baru@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => Role::OPERATOR->value,
            'operator_category' => 'LABORAN',
            'major_id' => Major::first()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $newUser = User::where('email', 'laboran.baru@gmail.com')->firstOrFail();
        $this->assertTrue($newUser->hasRole(Role::OPERATOR->value));
        $this->assertSame('LABORAN', $newUser->operator_category?->value);

        // Ubah role menjadi Member.
        $this->actingAs($this->superAdmin)->put(route('users.update', $newUser), [
            'name' => 'Laboran Baru Updated',
            'email' => 'laboran.baru@gmail.com',
            'role' => Role::MEMBER->value,
            'borrower_type' => 'GURU',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(Role::MEMBER->value, $newUser->refresh()->role);
        $this->assertTrue($newUser->hasRole(Role::MEMBER->value));
        $this->assertNull($newUser->operator_category);

        // Hapus user
        $this->actingAs($this->superAdmin)->delete(route('users.destroy', $newUser))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $newUser->id]);
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->superAdmin))
            ->assertStatus(422);
    }
}
