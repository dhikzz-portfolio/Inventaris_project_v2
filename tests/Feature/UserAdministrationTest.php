<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    private function makeAccountAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin Akun Sekolah',
            'email' => 'admin.akun@gmail.com',
            'role' => Role::SUPER_ADMIN_PENGGUNA->value,
        ]);
        $admin->syncRoles([Role::SUPER_ADMIN_PENGGUNA->value]);

        return $admin;
    }

    public function test_super_admin_pengguna_mengelola_akun_tanpa_akses_operasional(): void
    {
        $accountAdmin = $this->makeAccountAdmin();

        $this->assertTrue($accountAdmin->isSuperAdminPengguna());
        $this->assertTrue($accountAdmin->canManageUsers());
        $this->assertFalse($accountAdmin->isSuperAdmin());
        $this->assertFalse($accountAdmin->managesInventory());

        $this->actingAs($accountAdmin)->get(route('dashboard'))->assertOk();
        $this->actingAs($accountAdmin)->get(route('users.index'))->assertOk();
        $this->actingAs($accountAdmin)->get(route('catalogs.index'))->assertForbidden();
        $this->actingAs($accountAdmin)->get(route('items.acquisitions'))->assertForbidden();
        $this->actingAs($accountAdmin)->get(route('write-offs.index'))->assertForbidden();
        $this->actingAs($accountAdmin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_super_admin_pengguna_tidak_dapat_menetapkan_role_super_admin(): void
    {
        $accountAdmin = $this->makeAccountAdmin();

        $this->actingAs($accountAdmin)->post(route('users.store'), [
            'name' => 'Super Admin Palsu',
            'email' => 'super.palsu@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'super.palsu@gmail.com']);

        $target = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $this->actingAs($accountAdmin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => Role::SUPER_ADMIN->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::PEMINJAM->value, $target->refresh()->role);
    }

    public function test_super_admin_utama_tidak_dapat_dikelola_super_admin_pengguna(): void
    {
        $accountAdmin = $this->makeAccountAdmin();

        // Wakasek Sarpras tidak memperoleh pengecualian untuk administrasi akun.
        $this->actingAs($this->wakasek)->get(route('users.index'))->assertForbidden();

        // Super Admin Pengguna tidak dapat mengubah akun Super Admin utama.
        $this->actingAs($accountAdmin)->put(route('users.update', $this->wakasek), [
            'name' => 'Diubah Paksa',
            'email' => $this->wakasek->email,
            'role' => Role::SUPER_ADMIN->value,
        ])->assertForbidden();

        $this->actingAs($accountAdmin)->delete(route('users.destroy', $this->wakasek))->assertForbidden();

        $this->assertSame(Role::SUPER_ADMIN->value, $this->wakasek->refresh()->role);
        $this->assertTrue($this->wakasek->isActive());
    }

    public function test_akun_nonaktif_tidak_dapat_login_dan_sesi_berjalan_diputus(): void
    {
        $user = User::factory()->create([
            'email' => 'nonaktif@gmail.com',
            'role' => Role::LABORAN->value,
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => 'nonaktif@gmail.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        // Sesi yang sudah berjalan juga dihentikan middleware.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_perubahan_akun_dicatat_dengan_nilai_sebelum_dan_sesudah(): void
    {
        $accountAdmin = $this->makeAccountAdmin();
        $target = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $this->actingAs($accountAdmin)->put(route('users.update', $target), [
            'name' => 'Budi Santoso Baru',
            'email' => $target->email,
            'role' => Role::PEMINJAM->value,
            'borrower_type' => 'GURU',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = Activity::where('log_name', 'pengguna')
            ->where('subject_id', $target->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('UBAH', $log->properties['aksi']);
        $this->assertSame('Budi Santoso Baru', $log->properties['sesudah']['name']);
        $this->assertArrayHasKey('sebelum', $log->properties->toArray());
        $this->assertArrayNotHasKey('password', $log->properties['sebelum']);
        $this->assertArrayNotHasKey('password', $log->properties['sesudah']);
    }
}
