<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Menjalankan seeder aplikasi. Data lokasi (gedung H/I dan daftar ruangan)
     * tidak lagi dibuat oleh MasterDataSeeder, sehingga test menyiapkannya
     * sendiri agar DemoInventorySeeder dan cakupan laboran tetap dapat diuji.
     */
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder'): void
    {
        if ($class !== 'Database\\Seeders\\DatabaseSeeder') {
            Artisan::call('db:seed', ['--class' => $class]);

            return;
        }

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MasterDataSeeder']);
        $this->seedTestRooms();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SuperAdminSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoAccountSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoInventorySeeder']);
    }

    /** Membuat gedung historis H/I dan ruangan yang dipakai data demo. */
    private function seedTestRooms(): void
    {
        $buildings = collect(['H', 'I'])
            ->mapWithKeys(fn (string $code) => [
                $code => Building::updateOrCreate(
                    ['code' => $code],
                    ['name' => 'Gedung '.$code, 'is_active' => true]
                ),
            ]);

        $buildings = $buildings->merge(
            Building::whereIn('code', ['G', 'H', 'I'])->get()->keyBy('code')
        );

        $majors = Major::query()->pluck('id', 'code');

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

            Room::updateOrCreate(
                ['building_id' => $building->id, 'floor' => $floor, 'room_no' => $roomNo],
                [
                    'major_id' => $majorCode !== null ? $majors[$majorCode] : null,
                    'building_letter' => $building->code,
                    'floor' => $floor,
                    'room_no' => $roomNo,
                    'is_lab' => $isLab,
                    'code' => sprintf('%s%d-%02d', $building->code, $floor, $roomNo),
                    'name' => $name,
                ]
            );
        }
    }

    /** Akun Super Admin tunggal untuk pengujian administrasi akun. */
    protected function accountAdministrator(): User
    {
        $user = User::where('email', 'superadmin@gmail.com')->firstOrFail();
        $user->syncRoles([Role::SUPER_ADMIN->value]);

        return $user;
    }

    /**
     * Berganti pengguna dalam satu tes tidak boleh menyisakan hash password
     * pengguna sebelumnya pada sesi (AuthenticateSession akan memutus sesi).
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        // Pertahankan sesi bila masih pengguna yang sama (mis. alur pratinjau →
        // konfirmasi), tetapi putuskan sesi saat berganti akun agar hash password
        // pengguna sebelumnya tidak tersisa pada AuthenticateSession.
        if (auth()->id() !== $user->getAuthIdentifier()) {
            $this->flushSession();
        }

        return parent::actingAs($user, $guard);
    }
}
