<?php

namespace Database\Factories;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::PEMINJAM->value,
            // Profil contoh sudah lengkap agar tes tidak dialihkan ke
            // halaman pelengkapan profil.
            'borrower_type' => BorrowerType::GURU->value,
            'birth_date' => fake()->dateTimeBetween('-50 years', '-25 years')->format('Y-m-d'),
            'profile_completed' => true,
            'profile_completed_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
