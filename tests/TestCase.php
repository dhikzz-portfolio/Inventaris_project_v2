<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
