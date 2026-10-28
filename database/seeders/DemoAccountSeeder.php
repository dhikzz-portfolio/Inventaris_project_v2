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
            ['name' => 'Drs. H. Wakasek Sarpras', 'role' => Role::SUPER_ADMIN, 'major' => null, 'type' => null, 'password' => 'wakasek123', 'email' => 'wakasek@gmail.com', 'birth_date' => '1975-06-15'],
            ['name' => 'Ketua Laboran Sekolah', 'role' => Role::KETUA_LABORAN, 'major' => null, 'type' => null, 'password' => 'ketua123', 'email' => 'ketualaboran@gmail.com', 'birth_date' => '1978-02-10'],
            ['name' => 'Laboran RPL (Demo)', 'role' => Role::LABORAN, 'major' => 'RPL', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.rpl@gmail.com', 'birth_date' => '1982-04-18'],
            ['name' => 'Laboran DKV (Demo)', 'role' => Role::LABORAN, 'major' => 'DKV', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.dkv@gmail.com', 'birth_date' => '1983-08-25'],
            ['name' => 'Laboran Kuliner (Demo)', 'role' => Role::LABORAN, 'major' => 'KLN', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.kln@gmail.com', 'birth_date' => '1984-11-30'],
            ['name' => 'Laboran Teknik Pengelasan (Demo)', 'role' => Role::LABORAN, 'major' => 'TP', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.tp@gmail.com', 'birth_date' => '1981-01-12'],
            ['name' => 'Laboran TKP (Demo)', 'role' => Role::LABORAN, 'major' => 'TKP', 'type' => null, 'password' => 'laboran123', 'email' => 'laboran.tkp@gmail.com', 'birth_date' => '1985-09-05'],
            // Nama & gelar disimpan terpisah: displayName() membentuk "Budi Santoso, S.Kom".
            ['name' => 'Budi Santoso', 'degree' => 'S.Kom', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::GURU, 'password' => 'guru123', 'email' => 'guru.budi@gmail.com', 'birth_date' => '1985-03-12'],
            ['name' => 'Sari Wijayanti', 'degree' => 'S.Pd', 'role' => Role::PEMINJAM, 'major' => 'TP', 'type' => BorrowerType::GURU, 'password' => 'guru123', 'email' => 'guru.sari@gmail.com', 'birth_date' => '1988-07-22'],
            ['name' => 'Raka Pratama', 'role' => Role::PEMINJAM, 'major' => 'RPL', 'type' => BorrowerType::SISWA, 'password' => 'siswa123', 'email' => 'siswa.raka@gmail.com', 'birth_date' => '2010-05-10', 'class_name' => 'X RPL 1'],
            ['name' => 'Nadia Putri', 'role' => Role::PEMINJAM, 'major' => 'DKV', 'type' => BorrowerType::SISWA, 'password' => 'siswa123', 'email' => 'siswa.nadia@gmail.com', 'birth_date' => '2010-09-15', 'class_name' => 'XI DKV 2'],
            ['name' => 'Staf Tata Usaha', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123', 'email' => 'staf.tata@gmail.com', 'birth_date' => '1990-01-20', 'work_unit' => 'Tata Usaha'],
            ['name' => 'Staf Perpustakaan', 'role' => Role::PEMINJAM, 'major' => null, 'type' => BorrowerType::STAF, 'password' => 'staf123', 'email' => 'staf.perpus@gmail.com', 'birth_date' => '1992-11-05', 'work_unit' => 'Perpustakaan'],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $email = strtolower((string) $account['email']);
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $account['name'],
                    'degree' => $account['degree'] ?? null,
                    'email' => $email,
                    'password' => Hash::make($account['password']),
                    'role' => $account['role']->value,
                    'borrower_type' => $account['type']?->value,
                    'major_id' => $account['major'] !== null ? $majors[$account['major']] : null,
                    'birth_date' => $account['birth_date'] ?? null,
                    'class_name' => $account['class_name'] ?? null,
                    'work_unit' => $account['work_unit'] ?? null,
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            );

            // Lengkapi akun lama yang kekurangan data profil (idempoten):
            // tidak mereset password dan tidak menimpa data yang sudah ada.
            $missing = [];

            foreach (['birth_date', 'class_name', 'work_unit'] as $field) {
                if (blank($user->{$field}) && filled($account[$field] ?? null)) {
                    $missing[$field] = $account[$field];
                }
            }

            // Nama & gelar dipisah agar tidak ada dua sumber data nama resmi.
            // Data lama yang menyatukan "Budi Santoso, S.Kom" dipisah sekali;
            // run berikutnya tidak menggandakan gelar.
            $degree = $account['degree'] ?? null;

            if ($degree !== null) {
                if (str_contains((string) $user->name, ', '.$degree)) {
                    $missing['name'] = trim(str_replace(', '.$degree, '', (string) $user->name));
                    $missing['degree'] = $degree;
                } elseif (blank($user->degree)) {
                    $missing['degree'] = $degree;
                }
            }

            if (! $user->profile_completed) {
                $missing['profile_completed'] = true;
            }
            if ($user->profile_completed_at === null) {
                $missing['profile_completed_at'] = now();
            }

            if ($missing !== []) {
                $user->forceFill($missing)->save();
            }

            $user->syncRoles([$account['role']->value]);
        }

        $wakasek = User::where('email', 'wakasek@gmail.com')->first();
        $scopes = [
            'laboran.rpl' => ['H1-01', 'H1-02'],
            'laboran.dkv' => ['G2-01', 'G2-02', 'H2-02'],
            'laboran.kln' => ['H2-01'],
            'laboran.tp' => ['G1-01', 'G1-05'],
            'laboran.tkp' => ['H2-03', 'H2-04'],
        ];

        foreach ($scopes as $email => $codes) {
            $laboran = User::where('email', $email.'@gmail.com')->first();

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
