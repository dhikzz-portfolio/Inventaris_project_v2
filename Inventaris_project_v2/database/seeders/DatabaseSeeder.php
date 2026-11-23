<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder aplikasi.
     *
     * Di environment production (atau saat demo data dinonaktifkan),
     * hanya MasterDataSeeder dan SuperAdminSeeder yang dijalankan.
     * Akun demo, inventaris demo, dan transaksi demo hanya dijalankan
     * pada environment non-production atau bila SEED_DEMO_DATA=true.
     */
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
        ]);

        $seedDemoConfig = config('auth.seed_demo_data');
        $shouldSeedDemo = $seedDemoConfig !== null && $seedDemoConfig !== ''
            ? filter_var($seedDemoConfig, FILTER_VALIDATE_BOOLEAN)
            : ! app()->environment('production');

        if ($shouldSeedDemo) {
            $this->call([
                DemoAccountSeeder::class,
                DemoInventorySeeder::class,
                DemoLoanSeeder::class,
            ]);
        }
    }
}
