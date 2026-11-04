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

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@gmail.com')->firstOrFail();
        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_super_admin_mengelola_akun_tanpa_akses_operasional(): void
    {
        $this->assertTrue($this->superAdmin->isSuperAdmin());
        $this->assertTrue($this->superAdmin->canManageUsers());
        $this->assertFalse($this->superAdmin->managesInventory());

        $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('users.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('roles.index'))->assertOk();
        $this->actingAs($this->superAdmin)->get(route('settings.edit'))->assertOk();

        // Super Admin tidak memiliki akses operasional inventaris.
        $this->actingAs($this->superAdmin)->get(route('catalogs.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('items.acquisitions'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('write-offs.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_super_admin_tidak_dapat_membuat_atau_menetapkan_role_super_admin_tambahan(): void
    {
        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Super Admin Palsu',
            'email' => 'super.palsu@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'super.palsu@gmail.com']);

        $target = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $this->actingAs($this->superAdmin)->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => Role::SUPER_ADMIN->value,
            'borrower_type' => 'GURU',
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::MEMBER->value, $target->refresh()->role);
    }

    public function test_akun_super_admin_tunggal_dilindungi_dari_penghapusan_dan_penonaktifan(): void
    {
        $this->actingAs($this->superAdmin)
            ->delete(route('users.destroy', $this->superAdmin))
            ->assertStatus(422);

        $this->actingAs($this->superAdmin)
            ->post(route('users.status', $this->superAdmin))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->superAdmin)->put(route('users.update', $this->superAdmin), [
            'name' => $this->superAdmin->name,
            'email' => $this->superAdmin->email,
            'role' => Role::MEMBER->value,
            'borrower_type' => 'GURU',
        ])->assertSessionHasErrors('role');

        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->isSuperAdmin());
        $this->assertTrue($this->superAdmin->isActive());
    }

    public function test_admin_operasional_tidak_memiliki_akses_administrasi_akun(): void
    {
        $this->assertTrue($this->admin->isAdmin());

        $this->actingAs($this->admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_akun_nonaktif_tidak_dapat_login_dan_sesi_berjalan_diputus(): void
    {
        $user = User::factory()->create([
            'email' => 'nonaktif@gmail.com',
            'role' => Role::OPERATOR->value,
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
        $target = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $this->actingAs($this->superAdmin)->put(route('users.update', $target), [
            'name' => 'Budi Santoso Baru',
            'email' => $target->email,
            'role' => Role::MEMBER->value,
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
