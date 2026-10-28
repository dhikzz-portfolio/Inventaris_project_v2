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
            ['username' => 'wakasek', 'name' => 'Drs. H. Wakasek Sarpras', 'role' => Role::SUPER_ADMIN, 'major' => null, 'type' => null, 'password' => 'wakasek123', 'email' => 'wakasek@gmail.com'],
            ['username' => 'ketua.laboran', 'name' => 'Ketua Laboran Sekolah', 'role' => Role::KETUA_LABORAN, 'major' => null, 'type' => null, 'password' => 'ketua123', 'email' => 'ketualaboran@gmail.com'],
            ['username' => 'laboran.rpl', 'name' => 'Laboran RPL (Demo)', 'role' => Role::LABORAN, 'major' => 'RPL', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.rpl@gmail.com'],
            ['username' => 'laboran.dkv', 'name' => 'Laboran DKV (Demo)', 'role' => Role::LABORAN, 'major' => 'DKV', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.dkv@gmail.com'],
            ['username' => 'laboran.kln', 'name' => 'Laboran Kuliner (Demo)', 'role' => Role::LABORAN, 'major' => 'KLN', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.kln@gmail.com'],
            ['username' => 'laboran.tp', 'name' => 'Laboran Teknik Pengelasan (Demo)', 'role' => Role::LABORAN, 'major' => 'TP', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.tp@gmail.com'],
            ['username' => 'laboran.tkp', 'name' => 'Laboran TKP (Demo)', 'role' => Role::LABORAN, 'major' => 'TKP', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.tkp@gmail.com'],
            ['username' => 'guru.budi', 'name' => 'Budi Santoso, S.Kom', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::GURU, 'password' => 'guru123', 'email' => 'guru.budi@gmail.com'],
            ['username' => 'guru.sari', 'name' => 'Sari Wijayanti, S.Pd', 'role' => Role::PEMINJAM, 'major' => 'TP', 'type' => BorrowerType::GURU, 'password' => 'guru123', 'email' => 'guru.sari@gmail.com'],
            ['username' => 'siswa.raka', 'name' => 'Raka Pratama', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::SISWA, 'password' => 'siswa123', 'email' => 'siswa.raka@gmail.com'],
            ['username' => 'siswa.nadia', 'name' => 'Nadia Putri', 'role' => Role::PEMINJAM, 'major' => 'DKV', 'type' => BorrowerType::SISWA, 'password' => 'siswa123', 'email' => 'siswa.nadia@gmail.com'],
            ['username' => 'staf.tata', 'name' => 'Staf Tata Usaha', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123', 'email' => 'staf.tata@gmail.com'],
            ['username' => 'staf.perpus', 'name' => 'Staf Perpustakaan', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123', 'email' => 'staf.perpus@gmail.com'],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $email = $account['email'] ?? ($account['username'].'.demo@sekolah.local');
            $user = User::firstOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'email' => strtolower($email),
                    'password' => Hash::make($account['password']),
                    'role' => $account['role']->value,
                    'borrower_type' => $account['type']?->value,
                    'major_id' => $account['major'] !== null ? $majors[$account['major']] : null,
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            );

            if (! $user->email) {
                $user->email = strtolower($email);
                $user->profile_completed = true;
                $user->profile_completed_at = $user->profile_completed_at ?: now();
                $user->save();
            } elseif (! $user->profile_completed) {
                $user->profile_completed = true;
                $user->profile_completed_at = now();
                $user->save();
            }

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
