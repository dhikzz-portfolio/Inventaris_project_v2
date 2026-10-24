<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder dipisah menjadi empat bagian agar aman dijalankan ulang:
     * master data, akun demo, inventaris demo, dan transaksi demo.
     *
     * Seeder ini tidak menjalankan `migrate:fresh`, tidak menghapus transaksi,
     * dan tidak mereset password akun yang sudah ada.
     */
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            DemoAccountSeeder::class,
            DemoInventorySeeder::class,
            DemoLoanSeeder::class,
        ]);
    }
}
