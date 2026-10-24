<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Category;
use App\Models\InstitutionSetting;
use App\Models\Major;
use App\Models\Room;
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
        $majors = collect([
            'RPL' => 'Rekayasa Perangkat Lunak',
            'DKV' => 'Desain Komunikasi Visual',
            'KLN' => 'Kuliner',
            'TP' => 'Teknik Pengelasan',
            'TKP' => 'Teknik Konstruksi Perumahan',
        ])->mapWithKeys(fn (string $name, string $code) => [
            $code => Major::updateOrCreate(['code' => $code], ['name' => $name]),
        ]);

        // Data contoh lama (TKJ/MM) hanya dihapus bila benar-benar tidak dipakai.
        // Data historis yang masih memiliki relasi dibiarkan utuh.
        Major::query()
            ->whereIn('code', ['TKJ', 'MM'])
            ->whereDoesntHave('rooms')
            ->whereDoesntHave('users')
            ->delete();

        $buildings = collect([
            ['name' => 'Gedung G', 'code' => 'G'],
            ['name' => 'Gedung H', 'code' => 'H'],
            ['name' => 'Gedung I', 'code' => 'I'],
        ])->mapWithKeys(fn (array $building) => [
            $building['code'] => Building::updateOrCreate(
                ['code' => $building['code']],
                $building + ['is_active' => true]
            ),
        ]);

        // Kode ruangan dipertahankan dari data demo agar kode inventaris yang
        // sudah terbit tidak berubah. Nama laboratorium mengikuti lima jurusan.
        $rooms = [
            ['TP', 'G', 1, 1, true, 'Lab Teknik Pengelasan 1'],
            ['TP', 'G', 1, 5, true, 'Lab Teknik Pengelasan 2'],
            [null, 'G', 1, 3, false, 'Ruang Guru'],
            ['DKV', 'G', 2, 1, true, 'Lab Desain Komunikasi Visual 1'],
            ['DKV', 'G', 2, 2, true, 'Lab Desain Komunikasi Visual 2'],
            ['RPL', 'H', 1, 1, true, 'Lab Rekayasa Perangkat Lunak 1'],
            ['RPL', 'H', 1, 2, true, 'Lab Rekayasa Perangkat Lunak 2'],
            ['KLN', 'H', 2, 1, true, 'Lab Kuliner 1'],
            ['DKV', 'H', 2, 2, true, 'Studio Multimedia'],
            ['TKP', 'H', 2, 3, true, 'Lab Teknik Konstruksi Perumahan 1'],
            ['TKP', 'H', 2, 4, true, 'Lab Teknik Konstruksi Perumahan 2'],
            ['RPL', 'I', 1, 1, false, 'Ruang Kelas X RPL'],
            ['DKV', 'I', 1, 2, false, 'Ruang Kelas XI DKV'],
            ['KLN', 'I', 1, 3, false, 'Ruang Kelas XII Kuliner'],
            ['TP', 'I', 1, 4, false, 'Ruang Kelas X Teknik Pengelasan'],
            ['TKP', 'I', 1, 5, false, 'Ruang Kelas XI Teknik Konstruksi Perumahan'],
            [null, 'I', 1, 6, false, 'Ruang Tata Usaha'],
            [null, 'I', 1, 7, false, 'Gudang Sarana Prasarana'],
        ];

        foreach ($rooms as [$majorCode, $buildingCode, $floor, $roomNo, $isLab, $name]) {
            /** @var Building $building */
            $building = $buildings[$buildingCode];
            $major = $majorCode !== null ? $majors[$majorCode] : null;

            Room::updateOrCreate(
                ['building_id' => $building->id, 'floor' => $floor, 'room_no' => $roomNo],
                [
                    'major_id' => $major?->id,
                    'building_letter' => $building->code,
                    'floor' => $floor,
                    'room_no' => $roomNo,
                    'is_lab' => $isLab,
                    'code' => sprintf('%s%d-%02d', $building->code, $floor, $roomNo),
                    'name' => $name,
                ]
            );
        }

        foreach ([
            ['name' => 'Furnitur', 'code' => '01.01.09.01.01'],
            ['name' => 'Elektronik & Komputer', 'code' => '01.01.11.04.01'],
            ['name' => 'Alat Laboratorium', 'code' => '01.01.15.02.01'],
            ['name' => 'Alat Peraga / Kamera', 'code' => '01.01.16.03.01'],
            ['name' => 'Peralatan Kantor', 'code' => '01.01.11.04.02'],
        ] as $category) {
            Category::updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
