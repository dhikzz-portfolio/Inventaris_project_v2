<?php

namespace Tests\Feature;

use App\Enums\BorrowerType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementUiTest extends TestCase
{
    use RefreshDatabase;

    private User $wakasek;

    private User $accountAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->wakasek = User::where('email', 'wakasek@gmail.com')->firstOrFail();
        $this->accountAdmin = User::factory()->create([
            'name' => 'Admin Akun UI',
            'email' => 'admin.ui@gmail.com',
            'role' => Role::SUPER_ADMIN->value,
        ]);
        $this->accountAdmin->syncRoles([Role::SUPER_ADMIN->value]);
    }

    public function test_form_edit_pengguna_memakai_card_dan_radio_role(): void
    {
        $user = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $response = $this->actingAs($this->accountAdmin)
            ->get(route('users.index', ['edit' => $user->id]));

        $response->assertOk();
        $response->assertSee('Ubah Pengguna');
        $response->assertSee('value="'.$user->email.'"', false);
        $response->assertSee('name="role"', false);
        $response->assertSee('type="radio" name="role"', false);
    }

    public function test_super_admin_pengguna_dapat_memperbarui_profil_dan_role_pengguna(): void
    {
        $user = User::where('email', 'staf.tata@gmail.com')->firstOrFail();

        $this->actingAs($this->accountAdmin)->put(route('users.update', $user), [
            'name' => 'Staf Tata Usaha Baru',
            'degree' => 'S.Pd',
            'email' => 'tata.baru@gmail.com',
            'role' => Role::OPERATOR->value,
            'borrower_type' => BorrowerType::STAF->value,
            'room_ids' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Staf Tata Usaha Baru', $user->name);
        $this->assertSame('S.Pd', $user->degree);
        $this->assertSame('tata.baru@gmail.com', $user->email);
        $this->assertSame(Role::OPERATOR->value, $user->role);
        $this->assertNull($user->borrower_type);
        $this->assertTrue($user->hasRole(Role::OPERATOR->value));
    }

    public function test_super_admin_pengguna_tidak_dapat_menurunkan_role_akun_sendiri(): void
    {
        $this->actingAs($this->accountAdmin)->put(route('users.update', $this->accountAdmin), [
            'name' => $this->accountAdmin->name,
            'email' => $this->accountAdmin->email,
            'role' => Role::MEMBER->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(Role::SUPER_ADMIN->value, $this->accountAdmin->refresh()->role);

        // Wakasek Sarpras tidak memiliki akses administrasi akun.
        $this->actingAs($this->wakasek)->get(route('users.index'))->assertForbidden();
    }

    public function test_role_istimewa_tidak_dapat_ditetapkan_melalui_registrasi_maupun_profil(): void
    {
        $user = User::where('email', 'guru.budi@gmail.com')->firstOrFail();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => Role::SUPER_ADMIN->value,
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame(Role::MEMBER->value, $user->refresh()->role);
    }
}
