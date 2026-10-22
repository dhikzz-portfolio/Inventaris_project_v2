<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotifyApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_halaman_notifikasi_hanya_menampilkan_notifikasi_pengguna_sendiri(): void
    {
        NotifyApp::to($this->user, NotificationType::PERSETUJUAN, 'Notifikasi saya', '/loans/1');
        NotifyApp::to(User::factory()->create(), NotificationType::TERLAMBAT, 'Notifikasi orang lain', '/loans/2');

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifikasi saya')
            ->assertDontSee('Notifikasi orang lain');
    }

    public function test_menandai_notifikasi_dibaca_mengubah_status_dan_redirect_ke_tautan(): void
    {
        $notification = NotifyApp::to($this->user, NotificationType::PENGEMBALIAN, 'Pengembalian selesai', route('dashboard'));

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifikasi_tanpa_tautan_dapat_ditandai_dibaca_dan_kembali_ke_daftar(): void
    {
        $notification = NotifyApp::to($this->user, NotificationType::LAINNYA, 'Pemberitahuan tanpa tautan');

        $response = $this->actingAs($this->user)->get(route('notifications.index'));
        $response->assertOk()->assertSee('Tandai sudah dibaca');

        $this->actingAs($this->user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_pengguna_tidak_dapat_menandai_notifikasi_pengguna_lain(): void
    {
        $notification = NotifyApp::to(User::factory()->create(), NotificationType::PERSETUJUAN, 'Milik orang lain');

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_menandai_semua_notifikasi_dibaca(): void
    {
        NotifyApp::to($this->user, NotificationType::TERLAMBAT, 'Satu');
        NotifyApp::to($this->user, NotificationType::PENGEMBALIAN, 'Dua');

        $this->actingAs($this->user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_jumlah_belum_dibaca_pada_halaman_notifikasi(): void
    {
        NotifyApp::to($this->user, NotificationType::TERLAMBAT, 'Belum dibaca');
        $read = NotifyApp::to($this->user, NotificationType::PENGEMBALIAN, 'Sudah dibaca');
        $read->update(['read_at' => now()]);

        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertOk()->assertViewHas('unreadCount', 1);
    }

    public function test_notifikasi_yang_sudah_dibaca_tidak_mempunyai_tombol_tandai(): void
    {
        $notification = NotifyApp::to($this->user, NotificationType::LAINNYA, 'Sudah pernah dibaca');
        $notification->update(['read_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Tandai sudah dibaca')
            ->assertDontSee('Buka detail');
    }
}
