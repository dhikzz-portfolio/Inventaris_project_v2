<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Major;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_dapat_dijalankan_ulang_tanpa_duplikasi_dan_tanpa_reset_password(): void
    {
        $this->seed();

        $counts = [
            'majors' => Major::count(),
            'catalogs' => Item::count(),
            'units' => AssetUnit::count(),
            'users' => User::count(),
            'loans' => Loan::count(),
        ];
        $passwordHash = User::where('email', 'wakasek@gmail.com')->value('password');

        $this->seed();

        $this->assertSame($counts, [
            'majors' => Major::count(),
            'catalogs' => Item::count(),
            'units' => AssetUnit::count(),
            'users' => User::count(),
            'loans' => Loan::count(),
        ]);
        $this->assertSame($passwordHash, User::where('email', 'wakasek@gmail.com')->value('password'));
    }

    public function test_lima_jurusan_resmi_dan_role_final_tersedia(): void
    {
        $this->seed();

        $this->assertSame(
            ['DKV', 'KLN', 'RPL', 'TKP', 'TP'],
            Major::orderBy('code')->pluck('code')->all()
        );

        foreach (Role::cases() as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role->value, 'guard_name' => 'web']);
        }
    }

    public function test_setiap_laboran_hanya_memiliki_cakupan_laboratorium_jurusannya(): void
    {
        $this->seed();

        $scopes = [
            'laboran.rpl' => ['H1-01', 'H1-02'],
            'laboran.dkv' => ['G2-01', 'G2-02', 'H2-02'],
            'laboran.kln' => ['H2-01'],
            'laboran.tp' => ['G1-01', 'G1-05'],
            'laboran.tkp' => ['H2-03', 'H2-04'],
        ];

        foreach ($scopes as $handle => $expectedCodes) {
            $user = User::where('email', $handle.'@gmail.com')->firstOrFail();
            $codes = $user->roomScopes()->join('rooms', 'rooms.id', '=', 'user_room_scopes.room_id')
                ->orderBy('rooms.code')
                ->pluck('rooms.code')
                ->all();

            $this->assertSame($expectedCodes, $codes);
            $this->assertTrue($user->isLaboran());
        }
    }
}
