<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Bootstrap akun awal Super Admin Pengguna.
 *
 * Password awal TIDAK ditanam di source code: diambil dari
 * BOOTSTRAP_USER_ADMIN_PASSWORD bila diisi, atau dibuat acak (atau diberikan
 * eksplisit lewat --password). Selalu dicetak ke output konsol sekali.
 * Akun wajib mengganti password pada login pertama.
 */
class BootstrapUserAdmin extends Command
{
    protected $signature = 'user:bootstrap-admin
                            {--email= : Email akun (atau setel BOOTSTRAP_USER_ADMIN_EMAIL)}
                            {--name= : Nama akun (opsional)}
                            {--password= : Password awal (opsional; default BOOTSTRAP_USER_ADMIN_PASSWORD, lalu acak)}
                            {--reset-password : Setel ulang password akun bootstrap yang sudah ada (wajib ganti lagi)}';

    protected $description = 'Buat satu akun awal Super Admin Pengguna (idempoten, password dari env/acak).';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: config('auth.bootstrap_user_admin.email'))));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Email belum valid/disetel. Isi BOOTSTRAP_USER_ADMIN_EMAIL di environment atau jalankan dengan --email=alamat@valid.');

            return self::FAILURE;
        }

        $configuredPassword = trim((string) config('auth.bootstrap_user_admin.password'));
        $passwordFromEnv = $this->option('password') === null && $configuredPassword !== '';
        $password = (string) ($this->option('password') ?: $configuredPassword ?: Str::password(20));

        if (mb_strlen($password) < 12) {
            $this->error('Password awal minimal 12 karakter. Perbaiki --password atau BOOTSTRAP_USER_ADMIN_PASSWORD.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            if (! $existing->isSuperAdmin()) {
                $this->error("Email {$email} sudah dipakai akun lain (role {$existing->role}). Gunakan email lain.");

                return self::FAILURE;
            }

            if (! $this->option('reset-password')) {
                $this->info("Akun Super Admin Pengguna sudah ada: {$existing->email}. Password tidak diubah.");
                $this->line('Gunakan --reset-password untuk menyetel ulang password dari BOOTSTRAP_USER_ADMIN_PASSWORD / --password.');

                return self::SUCCESS;
            }

            $existing->forceFill([
                'password' => $password,
                'must_change_password' => true,
                'password_changed_at' => null,
            ])->save();

            $this->reportCredentials($existing, $password, $passwordFromEnv, 'Password akun Super Admin disetel ulang.');

            return self::SUCCESS;
        }

        $name = trim((string) ($this->option('name') ?: config('auth.bootstrap_user_admin.name', 'Super Admin Pengguna')));

        SpatieRole::firstOrCreate([
            'name' => Role::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::SUPER_ADMIN->value,
            'is_active' => true,
            'must_change_password' => true,
            'profile_completed' => true,
            'profile_completed_at' => now(),
        ]);

        $user->syncRoles([Role::SUPER_ADMIN->value]);

        $this->reportCredentials($user, $password, $passwordFromEnv, 'Akun Super Admin berhasil dibuat.');

        return self::SUCCESS;
    }

    private function reportCredentials(User $user, string $password, bool $passwordFromEnv, string $heading): void
    {
        $this->newLine();
        $this->info($heading);
        $this->line('Email: '.$user->email);
        $this->line('Password awal (tidak masuk log): '.$password);

        if ($passwordFromEnv) {
            $this->warn('Sumber: BOOTSTRAP_USER_ADMIN_PASSWORD. Kosongkan variabel itu setelah login pertama.');
        }

        $this->line('Wajib ganti password pada login pertama: ya');
        $this->line('Halaman administrasi akun: '.url('/pengguna'));
        $this->newLine();
        $this->warn('Simpan password ini sekarang; nilai di .env hanya dibaca saat command dijalankan.');
    }
}
