<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Major;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            SpatieRole::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        InstitutionSetting::updateOrCreate(['id' => 1], [
            'institution_name' => 'SMK BAGIMU NEGERIKU SEMARANG',
            'location_code' => '12.24.13.08.01.07.01.01',
            'address' => 'Jl. Palir Raya, Ngaliyan, Semarang, Jawa Tengah',
            'headmaster_name' => 'Daniel Setyo Wibowo',
        ]);

        // Lima jurusan resmi. Kode adalah identitas unik yang dipakai relasi.
        collect([
            'RPL' => 'Rekayasa Perangkat Lunak',
            'DKV' => 'Desain Komunikasi Visual',
            'KLN' => 'Kuliner',
            'TP' => 'Teknik Pengelasan',
            'TKP' => 'Teknik Konstruksi Perumahan',
        ])->each(fn (string $name, string $code) =>
            Major::updateOrCreate(['code' => $code], ['name' => $name])
        );

        // Data contoh lama (TKJ/MM) hanya dihapus bila benar-benar tidak dipakai.
        // Data historis yang masih memiliki relasi dibiarkan utuh.
        Major::query()
            ->whereIn('code', ['TKJ', 'MM'])
            ->whereDoesntHave('rooms')
            ->whereDoesntHave('users')
            ->delete();

        // Master gedung resmi A–G. `updateOrCreate` membuat seeder idempoten
        collect(range('A', 'G'))
            ->map(fn (string $code) => ['name' => 'Gedung '.$code, 'code' => $code])
            ->each(fn (array $building) =>
                Building::updateOrCreate(
                    ['code' => $building['code']],
                    $building + ['is_active' => true]
                )
            );

        foreach ([
            ['name' => 'Furnitur'],
            ['name' => 'Elektronik & Komputer'],
            ['name' => 'Alat Laboratorium'],
            ['name' => 'Alat Peraga / Kamera'],
            ['name' => 'Peralatan Kantor'],
        ] as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
