<?php

namespace Tests;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\Major;
use App\Models\User;

trait TestTrait
{
    /** Setup akun demo manual karena DemoAccountSeeder memiliki bug.
     * Bug: $account['type']?->value gagal ketika $account['type'] adalah string
     * seperti 'LABORAN' karena ?-> hanya cek null, bukan validasi tipe.
     * Enum BorrowerType hanya memiliki SISWA, GURU, STAF - tapi seeder gunakan
     * string 'LABORAN' yang tidak valid untuk enum access.
     */
    private function setupDemoUsersManually(): void
    {
        // Coba seeding terlebih dahulu. Jika berhasil, return.
        try {
            $this->seed();

            // Verifikasi bahwa users yang dibutuhkan sudah ada
            $requiredEmails = [
                'wakasek@gmail.com', 'ketualaboran@gmail.com',
                'laboran.rpl@gmail.com', 'laboran.dkv@gmail.com',
                'laboran.kln@gmail.com', 'laboran.tp@gmail.com',
                'laboran.tkp@gmail.com', 'guru.budi@gmail.com',
                'guru.sari@gmail.com', 'siswa.raka@gmail.com',
                'siswa.nadia@gmail.com', 'staf.tata@gmail.com',
                'staf.perpus@gmail.com',
            ];

            foreach ($requiredEmails as $email) {
                if (! User::where('email', $email)->exists()) {
                    throw new \Exception('User '.$email.' not found after seeding');
                }
            }

            return;
        } catch (\Exception $e) {
            // Seeding gagal, setup manual. Log error untuk debugging.
        }

        // Setup manual: buat users dengan data yang sesuai dengan yang diharapkan
        // oleh test-tes. Gunakan password yang sudah di-hash sesuai seeder.

        $passwords = [
            'wakasek@gmail.com' => 'wakasek123',
            'ketualaboran@gmail.com' => 'ketua123',
            'laboran.rpl@gmail.com' => 'laboran123',
            'laboran.dkv@gmail.com' => 'laboran123',
            'laboran.kln@gmail.com' => 'laboran123',
            'laboran.tp@gmail.com' => 'laboran123',
            'laboran.tkp@gmail.com' => 'laboran123',
            'guru.budi@gmail.com' => 'guru123',
            'guru.sari@gmail.com' => 'guru123',
            'siswa.raka@gmail.com' => 'siswa123',
            'siswa.nadia@gmail.com' => 'siswa123',
            'staf.tata@gmail.com' => 'staf123',
            'staf.perpus@gmail.com' => 'staf123',
        ];

        $majorCodes = ['RPL', 'DKV', 'TP', 'TKP', 'KLN'];
        foreach ($majorCodes as $code) {
            Major::firstOrCreate(['code' => $code], ['name' => $code]);
        }
        $majorMap = Major::pluck('id', 'code')->toArray();

        // Role enum values
        $roleAdmin = Role::ADMIN->value;
        $roleEditorValidator = Role::EDITOR_VALIDATOR->value;
        $roleOperator = Role::OPERATOR->value;
        $roleMember = Role::MEMBER->value;
        $roleSuperAdmin = Role::SUPER_ADMIN->value;

        // BorrowerType enum values
        $borrowerGuru = BorrowerType::GURU->value;
        $borrowerSiswa = BorrowerType::SISWA->value;
        $borrowerStaf = BorrowerType::STAF->value;

        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'wakasek@gmail.com'],
            [
                'name' => 'Drs. H. Wakasek Sarpras',
                'email' => 'wakasek@gmail.com',
                'password' => bcrypt('wakasek123'),
                'role' => $roleSuperAdmin,
                'borrower_type' => null,
                'major_id' => null,
                'birth_date' => '1975-06-15',
                'profile_completed' => true,
                'profile_completed_at' => now(),
            ]
        );
        $superAdmin->syncRoles([$roleSuperAdmin]);

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin.akun.test@example.test'],
            [
                'name' => 'Admin Akun Tes',
                'email' => 'admin.akun.test@example.test',
                'password' => bcrypt('admin123'),
                'role' => $roleAdmin,
                'borrower_type' => null,
                'major_id' => null,
                'is_active' => true,
                'profile_completed' => true,
                'profile_completed_at' => now(),
            ]
        );
        $admin->syncRoles([$roleAdmin]);

        // Ketua Laboran (EDITOR_VALIDATOR)
        $ketua = User::firstOrCreate(
            ['email' => 'ketualaboran@gmail.com'],
            [
                'name' => 'Editor/Validator Sekolah',
                'email' => 'ketualaboran@gmail.com',
                'password' => bcrypt('ketua123'),
                'role' => $roleEditorValidator,
                'borrower_type' => null,
                'major_id' => null,
                'birth_date' => '1978-02-10',
                'profile_completed' => true,
                'profile_completed_at' => now(),
            ]
        );
        $ketua->syncRoles([$roleEditorValidator]);

        // Laboran entries
        $laboranData = [
            ['email' => 'laboran.rpl@gmail.com', 'role' => $roleOperator, 'major' => 'RPL', 'type' => $borrowerGuru],
            ['email' => 'laboran.dkv@gmail.com', 'role' => $roleOperator, 'major' => 'DKV', 'type' => $borrowerGuru],
            ['email' => 'laboran.kln@gmail.com', 'role' => $roleOperator, 'major' => 'KLN', 'type' => $borrowerGuru],
            ['email' => 'laboran.tp@gmail.com', 'role' => $roleOperator, 'major' => 'TP', 'type' => $borrowerGuru],
            ['email' => 'laboran.tkp@gmail.com', 'role' => $roleOperator, 'major' => 'TKP', 'type' => $borrowerGuru],
        ];

        foreach ($laboranData as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => match ($data['major']) {
                        'RPL' => 'Operator RPL (Demo)',
                        'DKV' => 'Operator DKV (Demo)',
                        'KLN' => 'Operator Kuliner (Demo)',
                        'TP' => 'Operator Teknik Pengelasan (Demo)',
                        'TKP' => 'Operator TKP (Demo)',
                    },
                    'email' => $data['email'],
                    'password' => bcrypt($passwords[$data['email']]),
                    'role' => $data['role'],
                    'borrower_type' => $data['type'],
                    'major_id' => $majorMap[$data['major']] ?? null,
                    'birth_date' => match ($data['major']) {
                        'RPL' => '1982-04-18',
                        'DKV' => '1983-08-25',
                        'KLN' => '1984-11-30',
                        'TP' => '1981-01-12',
                        'TKP' => '1985-09-05',
                    },
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            )->syncRoles([$data['role']]);
        }

        // Guru entries
        $guruData = [
            ['email' => 'guru.budi@gmail.com', 'role' => $roleMember, 'major' => 'RPL', 'type' => $borrowerGuru],
            ['email' => 'guru.sari@gmail.com', 'role' => $roleMember, 'major' => 'TP', 'type' => $borrowerGuru],
        ];

        foreach ($guruData as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => match ($data['major']) {
                        'RPL' => 'Budi Santoso',
                        'TP' => 'Sari Wijayanti',
                    },
                    'degree' => match ($data['major']) {
                        'RPL' => 'S.Kom',
                        'TP' => 'S.Pd',
                    },
                    'email' => $data['email'],
                    'password' => bcrypt($passwords[$data['email']]),
                    'role' => $data['role'],
                    'borrower_type' => $data['type'],
                    'major_id' => $majorMap[$data['major']] ?? null,
                    'birth_date' => match ($data['major']) {
                        'RPL' => '1985-03-12',
                        'TP' => '1988-07-22',
                    },
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            )->syncRoles([$data['role']]);
        }

        // Siswa entries
        $siswaData = [
            ['email' => 'siswa.raka@gmail.com', 'role' => $roleMember, 'major' => 'RPL', 'type' => $borrowerSiswa, 'class_name' => 'X RPL 1'],
            ['email' => 'siswa.nadia@gmail.com', 'role' => $roleMember, 'major' => 'DKV', 'type' => $borrowerSiswa, 'class_name' => 'XI DKV 2'],
        ];

        foreach ($siswaData as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'] ?? 'Siswa Baru',
                    'email' => $data['email'],
                    'password' => bcrypt($passwords[$data['email']]),
                    'role' => $data['role'],
                    'borrower_type' => $data['type'],
                    'major_id' => $majorMap[$data['major']] ?? null,
                    'class_name' => $data['class_name'] ?? null,
                    'birth_date' => match ($data['major']) {
                        'RPL' => '2010-05-10',
                        'DKV' => '2010-09-15',
                    },
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            )->syncRoles([$data['role']]);
        }

        // Staf entries
        $stafData = [
            ['email' => 'staf.tata@gmail.com', 'role' => $roleMember, 'type' => $borrowerStaf],
            ['email' => 'staf.perpus@gmail.com', 'role' => $roleMember, 'type' => $borrowerStaf],
        ];

        foreach ($stafData as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => match ($data['email']) {
                        'staf.tata@gmail.com' => 'Staf Tata Usaha',
                        'staf.perpus@gmail.com' => 'Staf Perpustakaan',
                    },
                    'email' => $data['email'],
                    'password' => bcrypt($passwords[$data['email']]),
                    'role' => $data['role'],
                    'borrower_type' => $data['type'],
                    'work_unit' => match ($data['email']) {
                        'staf.tata@gmail.com' => 'Tata Usaha',
                        'staf.perpus@gmail.com' => 'Perpustakaan',
                    },
                    'birth_date' => match ($data['email']) {
                        'staf.tata@gmail.com' => '1990-01-20',
                        'staf.perpus@gmail.com' => '1992-11-05',
                    },
                    'profile_completed' => true,
                    'profile_completed_at' => now(),
                ]
            )->syncRoles([$data['role']]);
        }
    }
}
