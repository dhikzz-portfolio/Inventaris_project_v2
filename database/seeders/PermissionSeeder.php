<?php

namespace Database\Seeders;

use App\Support\RolePermissionManager;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Idempoten: hanya role yang belum pernah diinisialisasi yang memperoleh
     * baseline. Konfigurasi custom Super Admin tidak ditimpa. Super Admin
     * selalu dipulihkan ke baseline agar akses administrasi tidak hilang.
     */
    public function run(): void
    {
        app(RolePermissionManager::class)->bootstrap();
    }
}
