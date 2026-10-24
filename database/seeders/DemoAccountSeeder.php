<?php

namespace Database\Seeders;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\Major;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Akun demo memakai identitas fiktif. Password hanya ditulis saat akun
     * pertama kali dibuat; seeder tidak mereset password akun yang sudah ada.
     */
    public function run(): void
    {
        $majors = Major::query()->pluck('id', 'code');

        $accounts = [
            ['username' => 'wakasek', 'name' => 'Drs. H. Wakasek Sarpras', 'role' => Role::SUPER_ADMIN, 'major' => null, 'type' => null, 'password' => 'wakasek123'],
            ['username' => 'ketua.laboran', 'name' => 'Ketua Laboran Sekolah', 'role' => Role::KETUA_LABORAN, 'major' => null, 'type' => null, 'password' => 'ketua123'],
            ['username' => 'laboran.rpl', 'name' => 'Laboran RPL (Demo)', 'role' => Role::LABORAN, 'major' => 'RPL', 'type' => null, 'password' => 'laboran123'],
            ['username' => 'laboran.dkv', 'name' => 'Laboran DKV (Demo)', 'role' => Role::LABORAN, 'major' => 'DKV', 'type' => null, 'password' => 'laboran123'],
            ['username' => 'laboran.kln', 'name' => 'Laboran Kuliner (Demo)', 'role' => Role::LABORAN, 'major' => 'KLN', 'type' => null, 'password' => 'laboran123'],
            ['username' => 'laboran.tp', 'name' => 'Laboran Teknik Pengelasan (Demo)', 'role' => Role::LABORAN, 'major' => 'TP', 'type' => null, 'password' => 'laboran123'],
            ['username' => 'laboran.tkp', 'name' => 'Laboran TKP (Demo)', 'role' => Role::LABORAN, 'major' => 'TKP', 'type' => null, 'password' => 'laboran123'],
            ['username' => 'guru.budi', 'name' => 'Budi Santoso, S.Kom (Demo)', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::GURU, 'password' => 'guru123'],
            ['username' => 'guru.sari', 'name' => 'Sari Wijayanti, S.Pd (Demo)', 'role' => Role::PEMINJAM, 'major' => 'TP', 'type' => BorrowerType::GURU, 'password' => 'guru123'],
            ['username' => 'siswa.raka', 'name' => 'Raka Pratama (Demo Siswa)', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::SISWA, 'password' => 'siswa123'],
            ['username' => 'siswa.nadia', 'name' => 'Nadia Putri (Demo Siswa)', 'role' => Role::PEMINJAM, 'major' => 'DKV', 'type' => BorrowerType::SISWA, 'password' => 'siswa123'],
            ['username' => 'staf.tata', 'name' => 'Staf Tata Usaha (Demo)', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123'],
            ['username' => 'staf.perpus', 'name' => 'Staf Perpustakaan (Demo)', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123'],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $user = User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role']->value,
                    'borrower_type' => $account['type']?->value,
                    'major_id' => $account['major'] !== null ? $majors[$account['major']] : null,
                ]
            );

            $user->syncRoles([$account['role']->value]);
        }

        $wakasek = User::where('username', 'wakasek')->first();
        $scopes = [
            'laboran.rpl' => ['H1-01', 'H1-02'],
            'laboran.dkv' => ['G2-01', 'G2-02', 'H2-02'],
            'laboran.kln' => ['H2-01'],
            'laboran.tp' => ['G1-01', 'G1-05'],
            'laboran.tkp' => ['H2-03', 'H2-04'],
        ];

        foreach ($scopes as $username => $codes) {
            $laboran = User::where('username', $username)->first();

            if ($laboran === null) {
                continue;
            }

            $rooms = Room::whereIn('code', $codes)->pluck('id');

            foreach ($rooms as $roomId) {
                UserRoomScope::firstOrCreate(
                    ['user_id' => $laboran->id, 'room_id' => $roomId],
                    ['assigned_by' => $wakasek?->id]
                );
            }
        }
    }
}
