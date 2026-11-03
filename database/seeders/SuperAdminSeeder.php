<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed akun awal Super Admin dari environment configuration.
     * Idempoten: bila akun sudah ada, lengkapi profil dan pastikan role tanpa mereset
     * password yang mungkin sudah diubah secara manual di sistem.
     */
    public function run(): void
    {
        $roleInput = strtoupper(trim((string) config('auth.super_admin.role', Role::SUPER_ADMIN->value)));
        $role = Role::tryFrom($roleInput) ?? Role::SUPER_ADMIN;

        SpatieRole::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

        $name = (string) config('auth.super_admin.name', 'Super Admin Pengguna');
        $degree = config('auth.super_admin.degree', 'S.Kom');
        $email = strtolower(trim((string) config('auth.super_admin.email', 'superadmin@gmail.com')));
        $password = (string) config('auth.super_admin.password', 'superadmin123');
        $birthDate = config('auth.super_admin.birth_date', '1980-01-01');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'degree' => $degree,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role->value,
                'birth_date' => $birthDate,
                'profile_completed' => true,
                'profile_completed_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name' => $name ?: $user->name,
                'degree' => $degree ?? $user->degree,
                'role' => $role->value,
                'birth_date' => $user->birth_date ?? $birthDate,
                'profile_completed' => true,
                'profile_completed_at' => $user->profile_completed_at ?? now(),
            ])->save();
        }

        $user->syncRoles([$role->value]);
    }
}
