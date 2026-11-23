<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Support\LiveDataVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LiveRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'wakasek@gmail.com')->firstOrFail();
    }

    public function test_endpoint_versi_data_hanya_bisa_diakses_pengguna_terautentikasi(): void
    {
        $this->get(route('live.version'))->assertRedirect(route('login'));
    }

    public function test_versi_data_berubah_saat_data_berubah(): void
    {
        $first = $this->actingAs($this->admin)
            ->getJson(route('live.version'))
            ->assertOk()
            ->json('version');

        $this->assertIsString($first);

        // Cache sidik jari berumur pendek, jadi tunggu kedaluwarsa sekaligus
        // memastikan updated_at bergerak ke detik berikutnya.
        $this->travel(6)->seconds();
        Cache::forget(LiveDataVersion::CACHE_KEY);

        Room::firstOrFail()->update(['name' => 'Ruang Serbaguna Live']);

        $second = $this->actingAs($this->admin)
            ->getJson(route('live.version'))
            ->assertOk()
            ->json('version');

        $this->assertNotSame($first, $second);
    }

    public function test_halaman_terautentikasi_memasang_pemantau_refresh_otomatis(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-live-refresh', false)
            ->assertSee('data-version-url="'.route('live.version').'"', false)
            ->assertSee('Jeda pembaruan otomatis');
    }

    public function test_halaman_tamu_tidak_memasang_pemantau_refresh_otomatis(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('data-live-refresh', false);
    }
}
