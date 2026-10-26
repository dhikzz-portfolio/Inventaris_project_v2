<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Andhika Wicaksana',
            'degree' => 'S.Kom',
            'email' => 'andhika@gmail.com',
            'username' => 'andhika.w',
            'borrower_type' => BorrowerType::GURU->value,
            'password' => 'rahasia-aman-123',
            'password_confirmation' => 'rahasia-aman-123',
        ], $overrides);
    }

    public function test_halaman_registrasi_dapat_dibuka(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Kategori pengguna')
            ->assertSee('gmail.com', false);
    }

    public function test_registrasi_gmail_berhasil_dan_role_selalu_peminjam(): void
    {
        $response = $this->post(route('register'), $this->payload([
            // Percobaan eskalasi hak istimewa dari browser harus diabaikan.
            'role' => Role::SUPER_ADMIN->value,
            'is_admin' => true,
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('username', 'andhika.w')->firstOrFail();

        $this->assertSame(Role::PEMINJAM->value, $user->role);
        $this->assertSame('andhika@gmail.com', $user->email);
        $this->assertSame('S.Kom', $user->degree);
        $this->assertSame(BorrowerType::GURU, $user->borrower_type);
        $this->assertTrue(Hash::check('rahasia-aman-123', $user->password));
        $this->assertNotSame('rahasia-aman-123', $user->password);
        $this->assertTrue($user->hasRole(Role::PEMINJAM->value));
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
        $this->assertSame('Andhika Wicaksana, S.Kom', $user->displayName());
    }

    public function test_domain_gmail_diperiksa_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->post(route('register'), $this->payload([
            'email' => 'Pengguna@GMAIL.com',
            'username' => 'pengguna.kapital',
        ]))->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'username' => 'pengguna.kapital',
            'email' => 'pengguna@gmail.com',
        ]);
    }

    public function test_email_selain_gmail_ditolak_backend(): void
    {
        $this->post(route('register'), $this->payload([
            'email' => 'pengguna@yahoo.com',
            'username' => 'bukan.gmail',
        ]))->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['username' => 'bukan.gmail']);
        $this->assertGuest();
    }

    public function test_email_dan_username_duplikat_ditolak(): void
    {
        User::create([
            'name' => 'Akun Lama',
            'email' => 'lama@gmail.com',
            'username' => 'akun.lama',
            'password' => 'password-lama',
            'role' => Role::PEMINJAM->value,
        ]);

        $this->post(route('register'), $this->payload([
            'email' => 'lama@gmail.com',
            'username' => 'akun.lama',
        ]))->assertSessionHasErrors(['email', 'username']);
    }

    public function test_siswa_wajib_mengisi_kelas(): void
    {
        $this->post(route('register'), $this->payload([
            'username' => 'siswa.baru',
            'borrower_type' => BorrowerType::SISWA->value,
            'degree' => null,
            'class_name' => '',
        ]))->assertSessionHasErrors('class_name');
    }

    public function test_sapaan_dashboard_memakai_nama_lengkap_dan_gelar(): void
    {
        $this->post(route('register'), $this->payload());

        $this->followingRedirects()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang', false)
            ->assertSee('Andhika Wicaksana, S.Kom', false);
    }

    public function test_sapaan_dashboard_memakai_username_bila_nama_kosong(): void
    {
        $user = User::create([
            'name' => '',
            'username' => 'tanpa.nama',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('tanpa.nama', false);
    }

    public function test_profil_tidak_dapat_mengubah_role(): void
    {
        $user = User::create([
            'name' => 'Pengguna Biasa',
            'username' => 'pengguna.biasa',
            'email' => 'biasa@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Pengguna Biasa',
            'email' => 'biasa@gmail.com',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame(Role::PEMINJAM->value, $user->refresh()->role);
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
    }
}
