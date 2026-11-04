<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Lingkungan pengembangan memakai SEED_DEMO_DATA=false agar server
        // tidak terisi data contoh. Tes tetap membutuhkan akun demo semua
        // role, jadi seeding demo dipaksa aktif hanya di test.
        config(['auth.seed_demo_data' => true]);

        // Pengaman: RefreshDatabase akan menghapus seluruh tabel.
        // Tolak berjalan jika config ter-cache sehingga MySQL/dev DB yang dipakai.
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException(
                'Test dibatalkan: koneksi database bukan SQLite in-memory. '.
                'Jalankan `php artisan optimize:clear` lalu ulangi `php artisan test`.'
            );
        }
    }

    /** Super Admin Pengguna terpisah dari Wakasek; hanya role ini yang mengelola akun. */
    protected function accountAdministrator(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin Akun Tes',
            'email' => 'admin.akun.'.uniqid().'@example.test',
            'role' => Role::SUPER_ADMIN->value,
        ]);
        $admin->syncRoles([Role::SUPER_ADMIN->value]);

        return $admin;
    }

    /**
     * Satu tes kadang berpindah pengguna pada sesi yang sama, sedangkan
     * AuthenticateSession menyimpan hash password per sesi. Hapus hash saat
     * berpindah pengguna agar perilaku tes sama dengan login terpisah.
     */
    public function be(UserContract $user, $guard = null)
    {
        $this->app['session']->forget(
            'password_hash_'.($guard ?? config('auth.defaults.guard', 'web'))
        );

        return parent::be($user, $guard);
    }
}
