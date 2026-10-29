<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const REGISTRATION_TOKEN = 'uji-token-registrasi';

    /**
     * Sesi registrasi harus dibuat lewat POST /register/start (server-side
     * token). Tes memakai sesi langsung agar tidak terkena rate limit.
     */
    private function withRegistrationSession(): static
    {
        return $this->withSession([
            'registration_token' => self::REGISTRATION_TOKEN,
            'registration_token_expires_at' => now()->addMinutes(10)->timestamp,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Andhika Wicaksana',
            'birth_date' => now()->subYears(30)->format('Y-m-d'),
            'email' => 'andhika@gmail.com',
            'password' => 'rahasia-aman-123',
            'password_confirmation' => 'rahasia-aman-123',
            'registration_token' => self::REGISTRATION_TOKEN,
        ], $overrides);
    }

    public function test_halaman_registrasi_dapat_dibuka(): void
    {
        $this->withRegistrationSession()
            ->get(route('register', ['token' => self::REGISTRATION_TOKEN]))
            ->assertOk()
            ->assertSee('Nama lengkap')
            ->assertSee('gmail.com', false);
    }

    public function test_halaman_registrasi_tanpa_token_ditolak(): void
    {
        $this->get(route('register'))->assertForbidden();
    }

    public function test_registrasi_tidak_membutuhkan_username_dan_role_selalu_peminjam(): void
    {
        $response = $this->withRegistrationSession()->post(route('register'), $this->payload([
            // Percobaan eskalasi hak istimewa dari browser harus diabaikan.
            'role' => Role::SUPER_ADMIN->value,
            'is_admin' => true,
            'username' => 'hacker',
        ]));

        $response->assertRedirect(route('profile.complete'));
        $this->assertAuthenticated();

        $user = User::where('email', 'andhika@gmail.com')->firstOrFail();

        $this->assertSame(Role::PEMINJAM->value, $user->role);
        $this->assertSame(BorrowerType::GURU, $user->borrower_type);
        $this->assertTrue(Hash::check('rahasia-aman-123', $user->password));
        $this->assertNotSame('rahasia-aman-123', $user->password);
        $this->assertTrue($user->hasRole(Role::PEMINJAM->value));
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
        $this->assertFalse(Schema::hasColumn('users', 'username'));
    }

    public function test_kategori_pengguna_ditentukan_usia_bukan_input_browser(): void
    {
        // Usia di bawah 20 tahun otomatis menjadi Siswa.
        $this->withRegistrationSession()->post(route('register'), $this->payload([
            'email' => 'siswa.baru@gmail.com',
            'birth_date' => now()->subYears(16)->format('Y-m-d'),
            'borrower_type' => BorrowerType::GURU->value, // dipaksa dari browser, harus diabaikan
        ]))->assertRedirect(route('profile.complete'));

        $user = User::where('email', 'siswa.baru@gmail.com')->firstOrFail();
        $this->assertSame(BorrowerType::SISWA, $user->borrower_type);

        // Siswa wajib melengkapi kelas & jurusan saat pelengkapan profil.
        $this->post(route('profile.complete.store'), ['name' => 'Siswa Baru'])
            ->assertSessionHasErrors(['class_name', 'major_id']);
    }

    public function test_domain_gmail_diperiksa_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->withRegistrationSession()->post(route('register'), $this->payload([
            'email' => 'Pengguna@GMAIL.com',
        ]))->assertRedirect(route('profile.complete'));

        $this->assertDatabaseHas('users', [
            'email' => 'pengguna@gmail.com',
        ]);
    }

    public function test_email_selain_gmail_ditolak_backend(): void
    {
        $this->withRegistrationSession()->post(route('register'), $this->payload([
            'email' => 'pengguna@yahoo.com',
        ]))->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'pengguna@yahoo.com']);
        $this->assertGuest();
    }

    public function test_email_duplikat_ditolak(): void
    {
        User::create([
            'name' => 'Akun Lama',
            'email' => 'lama@gmail.com',
            'password' => 'password-lama',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::GURU->value,
        ]);

        $this->withRegistrationSession()->post(route('register'), $this->payload([
            'email' => 'lama@gmail.com',
        ]))->assertSessionHasErrors('email');
    }

    public function test_sapaan_dashboard_memakai_nama_lengkap_dan_gelar(): void
    {
        $this->withRegistrationSession()->post(route('register'), $this->payload());
        $this->assertAuthenticated();

        $user = User::where('email', 'andhika@gmail.com')->firstOrFail();

        // Lengkapi profil (wajib sebelum masuk dashboard).
        $this->post(route('profile.complete.store'), ['name' => 'Andhika Wicaksana'])
            ->assertSessionHasNoErrors();

        // Tambahkan gelar lewat edit profil (field terpisah dari nama).
        $this->patch(route('profile.update'), [
            'name' => 'Andhika Wicaksana',
            'degree' => 'S.Kom',
            'email' => 'andhika@gmail.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Andhika Wicaksana, S.Kom', $user->refresh()->displayName());

        $this->followingRedirects()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang', false)
            ->assertSee('Andhika Wicaksana, S.Kom', false);
    }

    public function test_profil_tidak_dapat_mengubah_role(): void
    {
        $user = User::create([
            'name' => 'Pengguna Biasa',
            'email' => 'biasa@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
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

        $this->assertSame(Role::PEMINJAM->value, $user->refresh()->role);
        $this->assertFalse($user->hasRole(Role::SUPER_ADMIN->value));
    }

    public function test_profil_tidak_dapat_mengubah_kategori_pengguna(): void
    {
        $major = Major::create(['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL']);

        $user = User::create([
            'name' => 'Pengguna Siswa',
            'email' => 'siswa.profil@gmail.com',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
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

    public function test_profil_akun_lama_dapat_menyimpan_email_non_gmail_yang_tidak_diubah(): void
    {
        $user = User::create([
            'name' => 'Akun Lama',
            'email' => 'guru@sekolah.id',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
            'borrower_type' => BorrowerType::GURU->value,
            'birth_date' => now()->subYears(40)->toDateString(),
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Nama Baru Akun Lama',
            'email' => 'guru@sekolah.id',
        ])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();

        $this->assertSame('Nama Baru Akun Lama', $user->refresh()->name);
        $this->assertSame('guru@sekolah.id', $user->email);
    }

    public function test_profil_menolak_perubahan_email_ke_domain_selain_gmail(): void
    {
        $user = User::create([
            'name' => 'Akun Lama',
            'email' => 'guru@sekolah.id',
            'password' => 'password-uji',
            'role' => Role::PEMINJAM->value,
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
