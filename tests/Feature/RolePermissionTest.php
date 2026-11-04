<?php

namespace Tests\Feature;

use App\Enums\OperatorCategory;
use App\Enums\Role;
use App\Models\Category;
use App\Models\PermissionChangeLog;
use App\Models\User;
use App\Support\RolePermissionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $editor;

    private User $operator;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@gmail.com')->firstOrFail();
        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->editor = User::where('email', 'ketualaboran@gmail.com')->firstOrFail();
        $this->operator = User::where('email', 'laboran.tp@gmail.com')->firstOrFail();
        $this->member = User::where('email', 'guru.budi@gmail.com')->firstOrFail();
    }

    public function test_super_admin_mengakses_administrasi_dan_ditolak_inventaris(): void
    {
        $this->as($this->superAdmin)->get(route('dashboard'))
            ->assertOk()->assertSee('Dashboard Super Admin');
        $this->as($this->superAdmin)->get(route('users.index'))->assertOk();
        $this->as($this->superAdmin)->get(route('roles.index'))->assertOk();
        $this->as($this->superAdmin)->get(route('settings.edit'))->assertOk();

        $this->as($this->superAdmin)->get(route('catalogs.index'))->assertForbidden();
        $this->as($this->superAdmin)->get(route('masters.index'))->assertForbidden();
        $this->as($this->superAdmin)->get(route('items.acquisitions'))->assertForbidden();
        $this->as($this->superAdmin)->get(route('write-offs.index'))->assertForbidden();
        $this->as($this->superAdmin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_admin_memiliki_seluruh_operasional_termasuk_master_data(): void
    {
        $this->as($this->admin)->get(route('masters.index'))->assertOk();
        $this->as($this->admin)->get(route('catalogs.index'))->assertOk();
        $this->as($this->admin)->get(route('items.acquisitions'))->assertOk();
        $this->as($this->admin)->get(route('write-offs.index'))->assertOk();
        $this->as($this->admin)->get(route('reports.index'))->assertOk();

        $this->as($this->admin)->post(route('masters.store-category'), [
            'name' => 'Kategori Uji Permission',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Kategori Uji Permission']);

        // Admin tidak boleh memperoleh fitur khusus Super Admin.
        $this->as($this->admin)->get(route('users.index'))->assertForbidden();
        $this->as($this->admin)->get(route('roles.index'))->assertForbidden();
        $this->as($this->admin)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_editor_validator_memantau_dan_memvalidasi_tanpa_persetujuan_akhir(): void
    {
        $this->assertTrue($this->editor->can('procurement.validate'));
        $this->assertFalse($this->editor->can('procurement.create'));
        $this->assertFalse($this->editor->can('procurement.approve'));
        $this->assertFalse($this->editor->can('master_jurusan.edit'));
        $this->assertFalse($this->editor->can('catalog.delete'));
        $this->assertFalse($this->editor->can('write_off.create'));

        $this->as($this->editor)->get(route('catalogs.index'))->assertOk();
        $this->as($this->editor)->get(route('items.acquisitions'))->assertOk();
        $this->as($this->editor)->get(route('items.create'))->assertForbidden();
        $this->as($this->editor)->get(route('masters.index'))->assertForbidden();
        $this->as($this->editor)->post(route('write-offs.store'), [])->assertForbidden();
    }

    public function test_operator_terbatas_pada_laboratorium_penugasannya(): void
    {
        $this->assertTrue($this->operator->can('procurement.create'));
        $this->assertFalse($this->operator->can('procurement.validate'));
        $this->assertFalse($this->operator->can('master_ruangan.edit'));
        $this->assertFalse($this->operator->can('write_off.create'));

        $this->as($this->operator)->get(route('catalogs.index'))->assertOk();
        $this->as($this->operator)->get(route('items.create'))->assertOk();
        $this->as($this->operator)->get(route('masters.index'))->assertForbidden();
    }

    public function test_member_hanya_memiliki_peminjaman_dan_kegiatan(): void
    {
        $this->assertTrue($this->member->can('loan.create'));
        $this->assertTrue($this->member->can('event.create'));
        $this->assertFalse($this->member->can('catalog.create'));
        $this->assertFalse($this->member->can('master_kategori.view'));
        $this->assertFalse($this->member->can('procurement.create'));

        $this->as($this->member)->get(route('loans.create'))->assertOk();
        $this->as($this->member)->get(route('events.index'))->assertOk();
        $this->as($this->member)->get(route('masters.index'))->assertForbidden();
        $this->as($this->member)->get(route('users.index'))->assertForbidden();
    }

    public function test_view_only_menyembunyikan_tombol_dan_menolak_endpoint_perubahan(): void
    {
        app(RolePermissionManager::class)->update(
            Role::ADMIN,
            ['catalog' => ['view']],
            $this->superAdmin,
            'Uji view only katalog',
        );

        $admin = $this->admin->fresh();

        $this->actingAs($admin)->get(route('catalogs.index'))
            ->assertOk()
            ->assertDontSee('Tambah Barang');

        $this->actingAs($admin)->get(route('items.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('masters.store-category'), ['name' => 'Tidak Boleh'])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Tidak Boleh']);
    }

    public function test_fitur_tersembunyi_tidak_muncul_di_menu_dan_tidak_dapat_diakses(): void
    {
        app(RolePermissionManager::class)->update(
            Role::ADMIN,
            ['catalog' => []],
            $this->superAdmin,
            'Sembunyikan katalog',
        );

        $admin = $this->admin->fresh();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Katalog Barang');

        $this->actingAs($admin)->get(route('catalogs.index'))->assertForbidden();
    }

    public function test_konfigurasi_custom_tidak_ditimpa_seeder(): void
    {
        app(RolePermissionManager::class)->update(
            Role::ADMIN,
            ['catalog' => ['view']],
            $this->superAdmin,
            'Uji persistensi',
        );

        $this->seed();

        $admin = $this->admin->fresh();
        $this->assertTrue($admin->can('catalog.view'));
        $this->assertFalse($admin->can('catalog.create'));
    }

    public function test_batasan_keamanan_menolak_admin_memperoleh_akses_akun(): void
    {
        try {
            app(RolePermissionManager::class)->update(
                Role::ADMIN,
                ['users' => ['view', 'create']],
                $this->superAdmin,
                'Percobaan eskalasi',
            );
            $this->fail('Konfigurasi yang melanggar batasan keamanan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permissions', $exception->errors());
        }

        $this->assertFalse($this->admin->fresh()->can('users.view'));
    }

    public function test_halaman_role_hanya_untuk_super_admin_dan_perubahan_diaudit(): void
    {
        $this->as($this->admin)->get(route('roles.index'))->assertForbidden();

        $this->as($this->superAdmin)->put(route('roles.update', Role::EDITOR_VALIDATOR->value), [
            'permissions' => [
                'catalog' => ['view'],
                'procurement' => ['view', 'validate'],
                'report' => ['view', 'export'],
            ],
            'reason' => 'Penyesuaian pengawasan laboratorium',
        ])->assertRedirect(route('roles.show', Role::EDITOR_VALIDATOR->value))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('permission_change_logs', [
            'role_name' => Role::EDITOR_VALIDATOR->value,
            'actor_id' => $this->superAdmin->id,
            'reason' => 'Penyesuaian pengawasan laboratorium',
        ]);

        $log = PermissionChangeLog::where('role_name', Role::EDITOR_VALIDATOR->value)->latest('id')->firstOrFail();
        $this->assertNotEmpty($log->before);
        $this->assertContains('catalog.view', $log->after);
    }

    public function test_super_admin_tidak_dapat_diedit_dan_tidak_dapat_dibuat_tambahan(): void
    {
        $this->as($this->superAdmin)
            ->put(route('roles.update', Role::SUPER_ADMIN->value), ['permissions' => []])
            ->assertForbidden();

        $this->as($this->superAdmin)->post(route('users.store'), [
            'name' => 'Super Admin Palsu',
            'email' => 'superadmin.palsu@gmail.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => Role::SUPER_ADMIN->value,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'superadmin.palsu@gmail.com']);
    }

    public function test_kategori_operator_disimpan_tanpa_membuat_role_terpisah(): void
    {
        $this->assertSame(OperatorCategory::LABORAN, $this->operator->operator_category);
        $this->assertSame(Role::OPERATOR->value, $this->operator->role);
    }

    /** Ganti pengguna aktif dengan aman meskipun middleware AuthenticateSession aktif. */
    private function as(User $user): self
    {
        $this->flushSession();
        $this->actingAs($user);

        return $this;
    }

    private function makeCategory(string $name = 'Kategori Uji'): Category
    {
        return Category::create(['name' => $name]);
    }
}
