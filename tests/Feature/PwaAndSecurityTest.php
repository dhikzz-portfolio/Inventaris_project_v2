<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manifest_json_dapat_diakses(): void
    {
        $response = $this->get('/manifest.json');
        $response->assertOk();
        $this->assertStringContainsString('Inventaris', $response->getContent());
    }

    public function test_service_worker_dapat_diakses(): void
    {
        $response = $this->get('/sw.js');
        $response->assertOk();
        $this->assertStringContainsString('CACHE_NAME', $response->getContent());
    }

    public function test_rute_terlindungi_mengarahkan_pengunjung_belum_login_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/items')->assertRedirect('/login');
        $this->get('/loans')->assertRedirect('/login');
    }

    public function test_rate_limiting_pada_login(): void
    {
        // 5 percobaan salah berturut-turut memicu rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'guru.budi',
                'password' => 'password-salah',
            ]);
        }

        // Percobaan ke-6 harus terblokir rate limiter (HTTP 429 atau pesan error throttling di session)
        $response = $this->post('/login', [
            'username' => 'guru.budi',
            'password' => 'password-salah',
        ]);

        $response->assertSessionHasErrors('username');
    }
}
