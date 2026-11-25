<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Registrasi mandiri sudah dinonaktifkan. Akun hanya dibuat oleh Super Admin
 * melalui halaman administrasi akun. Perlindungan profil tetap diuji di sini.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrasi_publik_tidak_tersedia_di_seluruh_endpoint(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.start'));

        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->post('/register/start')->assertNotFound();
    }

    public function test_halaman_login_tidak_menampilkan_tombol_daftar(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Daftar Akun')
            ->assertDontSee('/register', false);
    }

    public function test_super_admin_tetap_dapat_membuat_akun_pengguna(): void
    {
        $this->seed();
        $superAdmin = User::where('email', 'superadmin@gmail.com')->firstOrFail();

        $this->actingAs($superAdmin)->post(route('users.store'), [
            'name' => 'Akun Baru Administrasi',
            'email' => 'akun.baru@gmail.com',
            'password' => 'rahasia-aman-123',
            'password_confirmation' => 'rahasia-aman-123',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::GURU->value,
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'akun.baru@gmail.com')->firstOrFail();

        $this->assertSame(Role::MEMBER->value, $user->role);
        $this->assertSame(BorrowerType::GURU, $user->borrower_type);
        $this->assertTrue(Hash::check('rahasia-aman-123', $user->password));
        $this->assertNotSame('rahasia-aman-123', $user->password);
        $this->assertFalse($user->mustChangePassword());
        $this->assertTrue($user->hasRole(Role::MEMBER->value));
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
        $this->assertFalse(Schema::hasColumn('users', 'username'));
    }

    public function test_login_pertama_tidak_mewajibkan_penggantian_password(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'name' => 'Guru Login Pertama',
            'email' => 'guru.login@gmail.com',
            'password' => 'password-awal-123',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::GURU->value,
            'birth_date' => now()->subYears(35)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'guru.login@gmail.com',
            'password' => 'password-awal-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('wajib mengganti password', false);

        // Penggantian password sukarela dari profil tetap berfungsi.
        $this->put(route('password.update'), [
            'current_password' => 'password-awal-123',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('password-baru-456', $user->refresh()->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_profil_tidak_dapat_mengubah_role(): void
    {
        $user = User::create([
            'name' => 'Pengguna Biasa',
            'email' => 'biasa@gmail.com',
            'password' => 'password-uji',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::GURU->value,
            'birth_date' => now()->subYears(30)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Pengguna Biasa',
            'email' => 'biasa@gmail.com',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame(Role::MEMBER->value, $user->refresh()->role);
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
    }

    public function test_profil_tidak_dapat_mengubah_kategori_pengguna(): void
    {
        $major = Major::create(['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL']);

        $user = User::create([
            'name' => 'Pengguna Siswa',
            'email' => 'siswa.profil@gmail.com',
            'password' => 'password-uji',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::SISWA->value,
            'class_name' => 'X RPL 1',
            'major_id' => $major->id,
            'birth_date' => now()->subYears(16)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        // Request manual dari browser harus ditolak backend.
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Pengguna Siswa',
            'email' => 'siswa.profil@gmail.com',
            'class_name' => 'X RPL 1',
            'borrower_type' => BorrowerType::GURU->value,
        ])->assertSessionHasErrors('borrower_type');

        $this->assertSame(BorrowerType::SISWA, $user->refresh()->borrower_type);
    }

    public function test_profil_menolak_perubahan_email_ke_domain_selain_gmail(): void
    {
        $user = User::create([
            'name' => 'Akun Lama',
            'email' => 'guru@sekolah.id',
            'password' => 'password-uji',
            'role' => Role::MEMBER->value,
            'borrower_type' => BorrowerType::GURU->value,
            'birth_date' => now()->subYears(40)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Akun Lama',
            'email' => 'guru@yahoo.com',
        ])->assertSessionHasErrors('email');

        $this->assertSame('guru@sekolah.id', $user->refresh()->email);
    }
}
