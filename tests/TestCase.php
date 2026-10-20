<?php

namespace Tests;

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
}
