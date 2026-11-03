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
     * Seed akun awal Super Admin / Wakasek Sarpras dari environment configuration.
     * Idempoten: bila akun sudah ada, lengkapi profil dan pastikan role tanpa mereset
     * password yang mungkin sudah diubah secara manual di sistem.
     */
    public function run(): void
    {
        SpatieRole::firstOrCreate(['name' => Role::SUPER_ADMIN->value, 'guard_name' => 'web']);

        $name = (string) config('auth.super_admin.name', 'Wakasek Sarpras');
        $degree = config('auth.super_admin.degree', 'S.Kom');
        $email = strtolower(trim((string) config('auth.super_admin.email', 'wakasek@gmail.com')));
        $password = (string) config('auth.super_admin.password', 'wakasek123');
        $birthDate = config('auth.super_admin.birth_date', '1975-06-15');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'degree' => $degree,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => Role::SUPER_ADMIN->value,
                'birth_date' => $birthDate,
                'profile_completed' => true,
                'profile_completed_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name' => $name ?: $user->name,
                'degree' => $degree ?? $user->degree,
                'role' => Role::SUPER_ADMIN->value,
                'birth_date' => $user->birth_date ?? $birthDate,
                'profile_completed' => true,
                'profile_completed_at' => $user->profile_completed_at ?? now(),
            ])->save();
        }

        $user->syncRoles([Role::SUPER_ADMIN->value]);
    }
}
